<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Event\BeforeEntityTypeExport;
use Drupal\cms_content_sync\Plugin\rest\resource\EntityResource;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\cms_content_sync\SyncCore\DataCondition;
use Drupal\cms_content_sync\SyncCore\ParentCondition;
use Drupal\cms_content_sync\SyncCore\Storage\ApiStorage;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionStorage;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionSynchronizationStorage;
use Drupal\cms_content_sync\SyncCore\Storage\EntityTypeStorage;
use Drupal\cms_content_sync\SyncCore\Storage\InstanceStorage;
use Drupal\cms_content_sync\SyncCore\Storage\PreviewEntityStorage;
use Drupal\cms_content_sync\SyncCore\Storage\RemoteStorageStorage;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\user\Entity\User;
use Drupal\cms_content_sync\Form\PoolForm;

/**
 * Class SyncCoreFlowExport used to export the Synchronization config to the Sync
 * Core backend.
 */
class SyncCoreFlowExport extends SyncCoreExport {

  /**
   * @var \Drupal\cms_content_sync\Entity\Flow
   */
  protected $flow;

  /**
   * Sync Core Config constructor.
   *
   * @param \Drupal\cms_content_sync\Entity\Flow $flow
   *   The flow this exporter is used for.
   */
  public function __construct(Flow $flow) {
    parent::__construct();

    $this->flow = $flow;
  }

  /**
   * Get a list of all Sync Core connections as resource URLs.
   *
   * @return string[]
   */
  protected function getConnectionUrls() {
    $pools = Pool::getAll();
    $urls = [];

    foreach ($this->flow->getEntityTypeConfig() as $id => $type) {
      $entity_type_name = $type['entity_type_name'];
      $bundle_name = $type['bundle_name'];
      $version = $type['version'];

      if ($type['handler'] == Flow::HANDLER_IGNORE) {
        continue;
      }

      $entity_type_pools = [];
      foreach ($type['import_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }
        $entity_type_pools[$pool_id]['import'] = $state;
      }

      foreach ($type['export_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }
        $entity_type_pools[$pool_id]['export'] = $state;
      }

      foreach ($entity_type_pools as $pool_id => $definition) {
        $pool = $pools[$pool_id];
        $export = $definition['export'];
        $import = $definition['import'];

        if ($export == Pool::POOL_USAGE_FORBID && $import == Pool::POOL_USAGE_FORBID) {
          continue;
        }

        $url     = $pool->getBackendUrl();
        $api     = $pool->id;
        $site_id = $pool->getSiteId();

        $local_connection_id = ConnectionStorage::getExternalConnectionId($api, $site_id, $entity_type_name, $bundle_name);
        $urls[] = $url . '/api_unify-api_unify-connection-0_1/' . $local_connection_id;
      }
    }

    return $urls;
  }

  /**
   * Get a list of all Sync Core connection synchronizations as resource URLs.
   *
   * @param array $filters
   *   Additional filters for the synchronization URLs to return.
   *   - entity_type_id: Only return synchronizations for this entity type.
   *    - bundle:         Only return synchronizations for this bundle.
   *    - flow_export:    Only return synchronizations with this export setting.
   *    - flow_import:    Only return synchronizations with this import setting.
   *    - export_pools:   Only return synchronizations with this pool export setting.
   *    - import_pools:   Only return synchronizations with this pool import setting.
   *
   * @return string[]
   */
  protected function getConnectionSynchronizationUrls($filters) {
    $pools = Pool::getAll();
    $urls = [];

    foreach ($this->flow->getEntityTypeConfig() as $id => $type) {
      $entity_type_name = $type['entity_type_name'];
      if (!empty($filters['entity_type_id']) && $filters['entity_type_id'] != $entity_type_name) {
        continue;
      }

      $bundle_name = $type['bundle_name'];
      if (!empty($filters['bundle']) && $filters['bundle'] != $bundle_name) {
        continue;
      }

      $version = $type['version'];

      if ($type['handler'] == Flow::HANDLER_IGNORE) {
        continue;
      }

      if (!empty($filters['flow_export']) && !in_array($type['export'], $filters['flow_export'])) {
        continue;
      }

      if (!empty($filters['flow_import']) && !in_array($type['import'], $filters['flow_import'])) {
        continue;
      }

      $entity_type_pools = [];
      foreach ($type['import_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }
        $entity_type_pools[$pool_id]['import'] = $state;
      }
      foreach ($type['export_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }
        $entity_type_pools[$pool_id]['export'] = $state;
      }

      foreach ($entity_type_pools as $pool_id => $definition) {
        $pool = $pools[$pool_id];
        $export = $definition['export'];
        $import = $definition['import'];

        if (!empty($filters['pool_import']) && !in_array($import, $filters['pool_import'])) {
          continue;
        }

        if (!empty($filters['pool_export']) && !in_array($export, $filters['pool_export'])) {
          continue;
        }

        $url     = $pool->getBackendUrl();
        $api     = $pool->id;
        $site_id = $pool->getSiteId();

        $local_connection_id = ConnectionStorage::getExternalConnectionId($api, $site_id, $entity_type_name, $bundle_name);
        $sync_id = ConnectionSynchronizationStorage::getExternalConnectionSynchronizationId($local_connection_id, !empty($filters['flow_export']) && !empty($filters['pool_export']));
        $urls[] = $url . '/api_unify-api_unify-connection_synchronisation-0_1/' . $sync_id;
      }
    }

    return $urls;
  }

  /**
   * Kindly ask the Sync Core to pull an individual entity, e.g. when the
   * override checkbox is removed.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle
   *   The bundle.
   * @param string $shared_entity_id
   *   The entity UUID.
   *
   * @return array
   */
  public function pull($entity_type_id, $bundle, $shared_entity_id) {
    $urls = array_merge($this->getConnectionSynchronizationUrls(
      [
        'flow_import'     => [ImportIntent::IMPORT_AUTOMATICALLY, ImportIntent::IMPORT_AS_DEPENDENCY],
        'pool_import'     => [Pool::POOL_USAGE_FORCE],
        'entity_type_id'  => $entity_type_id,
        'bundle'          => $bundle,
      ]
    ), $this->getConnectionSynchronizationUrls(
      [
        'flow_import'     => [ImportIntent::IMPORT_AS_DEPENDENCY, ImportIntent::IMPORT_MANUALLY],
        'pool_import'     => [Pool::POOL_USAGE_ALLOW],
        'entity_type_id'  => $entity_type_id,
        'bundle'          => $bundle,
      ]
    ));

    $result = [];

    $type = $this->flow->getEntityTypeConfig($entity_type_id, $bundle);
    $manual = $type['import'] == ImportIntent::IMPORT_MANUALLY;

    foreach ($urls as $url) {
      try {
        $response = $this->client->request(
          'post',
          $url . '/clone/' . $shared_entity_id . '?manual=' . ($manual ? 'true' : 'false'),
          ['body' => '', 'http_errors' => FALSE]
        );

        if ($response->getStatusCode() != 200) {
          $result[$url] = FALSE;
        }
        elseif (!($body = json_decode($response->getBody(), TRUE))) {
          $result[$url] = FALSE;
        }
        else {
          $result[$url] = $body;
        }
      }
      catch (\Exception $e) {
        $result[$url] = FALSE;
      }
    }

    return $result;
  }

  /**
   * Kindly ask the Sync Core to pull all entities that are auto imported or
   * exported.
   *
   * @param bool $export
   *   Whether to push (TRUE) or pull (FALSE).
   *
   * @param bool $force
   *
   * @return array An associative array with the synchronization URLs as keys.
   *   The value is FALSE for failed requests. Otherwise it's an associative
   *   array with the following keys:
   *   - id:     ID for this process. If none is given, no entities had to be synchronized.
   *   - total:  Total number of entities to be synchronized, if any.
   */
  public function startSync($export, $force = FALSE) {
    $urls = $this->getConnectionSynchronizationUrls(
      [
        'flow_export' => $export ? [ExportIntent::EXPORT_FORCED] : NULL,
        'pool_export' => $export ? [Pool::POOL_USAGE_FORCE, Pool::POOL_USAGE_ALLOW] : NULL,
        'flow_import' => !$export ? [ImportIntent::IMPORT_AUTOMATICALLY] : NULL,
        'pool_import' => !$export ? [Pool::POOL_USAGE_FORCE] : NULL,
      ]
    );

    $result = [];

    foreach ($urls as $url) {
      try {
        $response = $this->client->request(
          'post',
          $url . '/synchronize?update_all=true' . ($force ? '&force=true' : ''),
          ['body' => '']
        );

        if ($response->getStatusCode() != 200) {
          $result[$url] = FALSE;
        }
        elseif (!($body = json_decode($response->getBody(), TRUE))) {
          $result[$url] = FALSE;
        }
        else {
          $result[$url] = $body;
        }
      }
      catch (\Exception $e) {
        $result[$url] = FALSE;
      }
    }

    return $result;
  }

  /**
   * Kindly ask the Sync Core to login to this site.
   */
  public function login() {
    $urls = $this->getConnectionUrls();

    $result = [];

    foreach ($urls as $url) {
      try {
        $response = $this->client->request(
          'post',
          $url . '/login',
          ['body' => '']
        );

        $result[$url] = $response->getStatusCode() == 200 && ($body = json_decode($response->getBody(), TRUE)) && $body['success'];
      }
      catch (\Exception $e) {
        $result[$url] = FALSE;
      }
    }

    return $result;
  }

  /**
   * Create all entity types, connections and synchronizations as required.
   *
   * TODO: Provide static function to export all Flows at once as right now overlapping Pools usage between Flows will
   * TODO: Result in multiple CREATE requests for the same configuration, slowing the export down a little.
   *
   * @throws \Exception If the user profile for import is not available.
   */
  public function prepareBatch() {
    $remote_storages = [];

    $operations = [];

    $this->addStorages($remote_storages, $operations);

    foreach (Flow::getAll() as $id => $flow) {
      if ($this->flow->id === $id) {
        continue;
      }

      $sub = new SyncCoreFlowExport($flow);
      $sub->addStorages($remote_storages, $operations, TRUE);
    }

    $pools = Pool::getAll();
    foreach ($remote_storages as $pool_id => $storage) {
      $pool         = $pools[$pool_id];
      $operations[] = [
        $pool->getBackendUrl() . '/api_unify-api_unify-remote_storage-0_1', [
          'json' => $storage,
        ],
      ];
    }

    return $operations;
  }

  /**
   * Create all entity types, connections and synchronizations as required.
   *
   * @param $remote_storages
   * @param $operations
   * @param bool $extend_only
   *
   * @throws \Exception If the user profile for import is not available.
   */
  public function addStorages(&$remote_storages, &$operations, $extend_only = FALSE) {
    // Ignore disabled flows at export.
    if (!$this->flow->get('status')) {
      return;
    }

    $export_url = EntityResource::getBaseUrl();
    $enable_preview = static::isPreviewEnabled();

    $cms_content_sync_disable_optimization = boolval(\Drupal::config('cms_content_sync.debug')
      ->get('cms_content_sync_disable_optimization'));

    $user = User::load(CMS_CONTENT_SYNC_USER_ID);
    // During the installation from an existing config for some reason CMS_CONTENT_SYNC_USER_ID is not set right after the installation of the module, so we've to double check that...
    if (is_null(CMS_CONTENT_SYNC_USER_ID)) {
      $user = User::load(\Drupal::service('keyvalue.database')
        ->get('cms_content_sync_user')
        ->get('uid'));
    }

    if (!$user) {
      throw new \Exception(
        t("CMS Content Sync User not found. Encrypted data can't be saved")
      );
    }

    $userData = \Drupal::service('user.data');
    $loginData = $userData->get('cms_content_sync', $user->id(), 'sync_data');

    if (!$loginData) {
      throw new \Exception(t("No credentials for sync user found."));
    }

    $encryption_profile = EncryptionProfile::load(cms_content_sync_PROFILE_NAME);

    foreach ($loginData as $key => $value) {
      $loginData[$key] = \Drupal::service('encryption')
        ->decrypt($value, $encryption_profile);
    }

    $entity_types = $this->flow->sync_entities;

    $pools = Pool::getAll();

    $this->remove(TRUE);

    $export_pools = [];

    foreach ($this->flow->getEntityTypeConfig() as $id => $type) {
      $entity_type_name = $type['entity_type_name'];
      $bundle_name      = $type['bundle_name'];
      $version          = $type['version'];

      if ($type['handler'] == Flow::HANDLER_IGNORE) {
        continue;
      }
      $handler = $this->flow->getEntityTypeHandler($type);

      $entity_type_pools = [];
      foreach ($type['import_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }

        if ($type['import'] == ImportIntent::IMPORT_DISABLED) {
          $entity_type_pools[$pool_id]['import'] = Pool::POOL_USAGE_FORBID;
          continue;
        }

        $entity_type_pools[$pool_id]['import'] = $state;
      }
      foreach ($type['export_pools'] as $pool_id => $state) {
        if (!isset($entity_type_pools[$pool_id])) {
          $entity_type_pools[$pool_id] = [];
        }

        if ($type['export'] == ExportIntent::EXPORT_DISABLED) {
          $entity_type_pools[$pool_id]['export'] = Pool::POOL_USAGE_FORBID;
          continue;
        }

        $entity_type_pools[$pool_id]['export'] = $state;
      }

      foreach ($entity_type_pools as $pool_id => $definition) {
        $pool   = $pools[$pool_id];
        $export = $definition['export'];
        $import = $definition['import'];

        if ((!$export || $export == Pool::POOL_USAGE_FORBID) && (!$import || $import == Pool::POOL_USAGE_FORBID)) {
          continue;
        }

        if (!in_array($pool, $export_pools)) {
          $export_pools[] = $pool;
        }

        $url     = $pool->getBackendUrl();
        $api     = $pool->id;
        $site_id = $pool->getSiteId();

        if ($extend_only && !isset($remote_storages[$api])) {
          continue;
        }

        if (strlen($site_id) > PoolForm::siteIdMaxLength) {
          throw new \Exception(t('The site id of pool ' . $pool_id . ' is having more then ' . PoolForm::siteIdMaxLength . ' characters. This is not allowed due to backend limitations and will result in an exception when it is trying to be exported.'));
        }

        if ($pool->getAuthenticationType() == Pool::AUTHENTICATION_TYPE_BASIC_AUTH) {
          $authentication = [
            'type' => 'basic_auth',
            'username' => $loginData['userName'],
            'password' => $loginData['userPass'],
            'base_url' => $export_url,
          ];
        }
        else {
          $authentication = [
            'type' => 'drupal8_services',
            'username' => $loginData['userName'],
            'password' => $loginData['userPass'],
            'base_url' => $export_url,
          ];
        }

        $entity_type_id = EntityTypeStorage::getExternalEntityTypeId($api, $entity_type_name, $bundle_name, $version);
        $entity_type = [
          'id' => $entity_type_id,
          'name_space' => $entity_type_name,
          'name' => $bundle_name,
          'version' => $version,
          'base_class' => "api-unify/services/drupal/v0.1/models/base.model",
          'custom' => TRUE,
          'new_properties' => [
            'source' => [
              'type' => 'reference',
              'default_value' => NULL,
              'connection_identifiers' => [
                [
                  'properties' => [
                    'id' => 'source_connection_id',
                  ],
                ],
              ],
              'model_identifiers' => [
                [
                  'properties' => [
                    'id' => 'source_id',
                  ],
                ],
              ],
              'multiple' => FALSE,
            ],
            'source_id' => [
              'type' => 'id',
              'default_value' => NULL,
            ],
            'source_connection_id' => [
              'type' => 'id',
              'default_value' => NULL,
            ],
            'preview' => [
              'type' => 'string',
              'default_value' => NULL,
            ],
            'url' => [
              'type' => 'string',
              'default_value' => NULL,
            ],
            'apiu_translation' => [
              'type' => 'object',
              'default_value' => NULL,
            ],
            'metadata' => [
              'type' => 'object',
              'default_value' => NULL,
            ],
            'embed_entities' => [
              'type' => 'object',
              'default_value' => NULL,
              'multiple' => TRUE,
            ],
            'menu_items' => [
              'type' => 'object',
              'default_value' => NULL,
              'multiple' => TRUE,
            ],
            'title' => [
              'type' => 'string',
              'default_value' => NULL,
            ],
            'created' => [
              'type' => 'int',
              'default_value' => NULL,
            ],
            'changed' => [
              'type' => 'int',
              'default_value' => NULL,
            ],
            'uuid' => [
              'type' => 'string',
              'default_value' => NULL,
            ],
          ],
          'new_property_lists' => [
            'list' => [
              '_resource_url' => 'value',
              '_resource_connection_id' => 'value',
              'id' => 'value',
            ],
            'reference' => [
              '_resource_url' => 'value',
              '_resource_connection_id' => 'value',
              'id' => 'value',
            ],
            'details' => [
              '_resource_url' => 'value',
              '_resource_connection_id' => 'value',
              'id' => 'value',
              'source' => 'reference',
              'apiu_translation' => 'value',
              'metadata' => 'value',
              'embed_entities' => 'value',
              'title' => 'value',
              'created' => 'value',
              'changed' => 'value',
              'uuid' => 'value',
              'url' => 'value',
              'menu_items' => 'value',
            ],
            'database' => [
              'id' => 'value',
              'source_id' => 'value',
              'source_connection_id' => 'value',
              'preview' => 'value',
              'url' => 'value',
              'apiu_translation' => 'value',
              'metadata' => 'value',
              'embed_entities' => 'value',
              'title' => 'value',
              'created' => 'value',
              'changed' => 'value',
              'uuid' => 'value',
              'menu_items' => 'value',
            ],
            'modifiable' => [
              'title' => 'value',
              'preview' => 'value',
              'url' => 'value',
              'apiu_translation' => 'value',
              'metadata' => 'value',
              'embed_entities' => 'value',
              'menu_items' => 'value',
            ],
            'required' => [
              'uuid' => 'value',
            ],
          ],
          'api_id' => $api . '-' . ApiStorage::CUSTOM_API_VERSION,
        ];

        $import_condition = [];

        if (EntityHandlerPluginManager::isEntityTypeFieldable($entity_type_name)) {

          $entityFieldManager = \Drupal::service('entity_field.manager');
          /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
          $fields = $entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name);

          $forbidden = $handler->getForbiddenFields();

          foreach ($fields as $key => $field) {
            if (!isset($entity_types[$id . '-' . $key])) {
              continue;
            }

            if (in_array($key, $forbidden)) {
              continue;
            }

            if (isset($entity_type['new_properties'][$key])) {
              continue;
            }

            if (!empty($entity_types[$id . '-' . $key]['handler_settings']['subscribe_only_to'])) {
              $allowed = [];

              foreach ($entity_types[$id . '-' . $key]['handler_settings']['subscribe_only_to'] as $ref) {
                $allowed[] = $ref['uuid'];
              }

              $import_condition[] = DataCondition::create($key . '.*.' . SyncIntent::UUID_KEY, DataCondition::IS_IN, $allowed);
            }

            $entity_type['new_properties'][$key] = [
              'type' => 'object',
              'default_value' => NULL,
              'multiple' => TRUE,
            ];

            $entity_type['new_property_lists']['details'][$key] = 'value';
            $entity_type['new_property_lists']['database'][$key] = 'value';

            if ($field->isRequired()) {
              $entity_type['new_property_lists']['required'][$key] = 'value';
            }

            if (!$field->isReadOnly() || $key == 'menu_link' || $field->getType() == 'path') {
              $entity_type['new_property_lists']['modifiable'][$key] = 'value';
            }
          }
        }

        if (count($import_condition) > 1) {
          $import_condition = ParentCondition::create(ParentCondition::MATCH_ALL, $import_condition);
        }
        elseif (count($import_condition)) {
          $import_condition = $import_condition[0];
        }
        else {
          $import_condition = NULL;
        }

        // TODO entity types should also contain the entity type handler in their machine name, preventing the following potential errors:
        // - Different flows may use different entity type handlers, resulting in different entity type definitions for the same entity type
        // - Changing the entity type handler must change the entity type definition which will not work if we don't update the machine name.
        // => Alternatively, force the same handler to be used at all sites.
        $handler->updateEntityTypeDefinition($entity_type);

        // Dispatch EntityTypeExport event to give other modules the possibility
        // to adjust the entity type definition and add custom fields.
        \Drupal::service('event_dispatcher')->dispatch(BeforeEntityTypeExport::EVENT_NAME, new BeforeEntityTypeExport($entity_type_name, $bundle_name, $entity_type));

        if ($extend_only) {
          $exists = FALSE;
          foreach ($operations as $operation) {
            if (substr($operation[0], -36) == '/api_unify-api_unify-entity_type-0_1' && $operation[1]['json']['id'] == $entity_type['id']) {
              $exists = TRUE;
              break;
            }
          }
          if ($exists) {
            continue;
          }
        }

        // Create the entity type.
        $operations[] = [
          $url . '/api_unify-api_unify-entity_type-0_1', [
            'json' => $entity_type,
          ],
        ];

        $pool_connection_id = ConnectionStorage::getExternalConnectionId($api, InstanceStorage::POOL_SITE_ID, $entity_type_name, $bundle_name);
        // Create the pool connection entity for this entity type.
        $operations[] = [
          $url . '/api_unify-api_unify-connection-0_1', [
            'json' => [
              'id' => $pool_connection_id,
              'name' => 'Drupal pool connection for ' . $entity_type_name . '-' . $bundle_name . '-' . $version,
              'hash' => ConnectionStorage::getExternalConnectionPath($api, InstanceStorage::POOL_SITE_ID, $entity_type_name, $bundle_name),
              'usage' => 'EXTERNAL',
              'status' => 'READY',
              'options' => [
                'update_all' => $cms_content_sync_disable_optimization,
              ],
              'entity_type_id' => $entity_type_id,
            ],
          ],
        ];

        // Create a synchronization from the pool to the preview connection.
        if ($enable_preview) {
          $operations[] = [$url . '/api_unify-api_unify-connection_synchronisation-0_1', [
            'json' => [
              'id' => $pool_connection_id . '--to--preview',
              'name' => 'Synchronization Pool ' . $entity_type_name . '-' . $bundle_name . ' -> Preview',
              'options' => [
                'create_entities' => TRUE,
                'update_entities' => TRUE,
                'delete_entities' => TRUE,
                'update_none_when_loading' => TRUE,
                'exclude_reference_properties' => [
                  'pSource',
                ],
              ],
              'status' => 'READY',
              'source_connection_id' => $pool_connection_id,
              'destination_connection_id' => PreviewEntityStorage::ID,
            ],
          ],
          ];
        }

        if (isset($remote_storages[$api])) {
          $remote_storages[$api]['entity_type_ids'][] = $entity_type_id;
        }
        else {
          $remote_storages[$api] = [
            'id' => RemoteStorageStorage::getStorageId($api, $site_id),
            'name' => 'Drupal connection on ' . $site_id . ' for ' . $api,
            'status' => 'READY',
            'instance_id' => $site_id,
            'api_id' => $api,
            'entity_type_ids' => [$entity_type_id],
            'connection_id_pattern' => ConnectionStorage::getExternalConnectionId('[api.id]', '[instance.id]', '[entity_type.name_space]', '[entity_type.name]'),
            'connection_name_pattern' => 'Drupal connection on [instance.id] for [entity_type.name_space].[entity_type.name]:[entity_type.version]',
            'connection_path_pattern' => ConnectionStorage::getExternalConnectionPath('[api.id]', '[instance.id]', '[entity_type.name_space]', '[entity_type.name]'),
            'connection_options' => [
              'list_url' => EntityResource::getInternalUrl('[api.id]', '[entity_type.name_space]', '[entity_type.name]', '[entity_type.version]'),
              'item_url' => EntityResource::getInternalUrl('[api.id]', '[entity_type.name_space]', '[entity_type.name]', '[entity_type.version]', '[id]'),
              'authentication' => $authentication,
              'update_all' => $cms_content_sync_disable_optimization,
            ],
          ];
        }

        $local_connection_id = ConnectionStorage::getExternalConnectionId($api, $site_id, $entity_type_name, $bundle_name);

        // Create a synchronization from the pool to the local connection.
        if (!$extend_only && $import != Pool::POOL_USAGE_FORBID && $type['import'] != ImportIntent::IMPORT_DISABLED) {
          $operations[] = [
            $url . '/api_unify-api_unify-connection_synchronisation-0_1', [
              'json' => [
                'id' => ConnectionSynchronizationStorage::getExternalConnectionSynchronizationId($local_connection_id, FALSE),
                'name' => 'Synchronization for ' . $entity_type_name . '/' . $bundle_name . '/' . $version . ' from Pool -> ' . $site_id,
                'options' => [
                  'dependency_connection_id' => ConnectionStorage::DEPENDENCY_CONNECTION_ID,
                  'create_entities' => $type['import'] != ImportIntent::IMPORT_MANUALLY,
                  'force_updates' => $cms_content_sync_disable_optimization,
                  'update_entities' => TRUE,
                  'delete_entities' => boolval($type['import_deletion_settings']['import_deletion']),
                  'dependent_entities_only' => $type['import'] == ImportIntent::IMPORT_AS_DEPENDENCY,
                  'update_none_when_loading' => TRUE,
                  'condition' => $import_condition ? $import_condition->toArray() : NULL,
                  'exclude_reference_properties' => [
                    'pSource',
                  ],
                ],
                'status' => 'READY',
                'source_connection_id' => $pool_connection_id,
                'destination_connection_id' => $local_connection_id,
              ],
            ],
          ];
        }
        if (!$extend_only && $export != Pool::POOL_USAGE_FORBID && $type['export'] != ExportIntent::EXPORT_DISABLED) {
          $operations[] = [
            $url . '/api_unify-api_unify-connection_synchronisation-0_1', [
              'json' => [
                'id' => ConnectionSynchronizationStorage::getExternalConnectionSynchronizationId($local_connection_id, TRUE),
                'name' => 'Synchronization for ' . $entity_type_name . '/' . $bundle_name . '/' . $version . ' from ' . $site_id . ' -> Pool',
                'options' => [
                  'dependency_connection_id' => ConnectionStorage::POOL_DEPENDENCY_CONNECTION_ID,
                  // As entities will only be sent to Sync Core if the sync config
                  // allows it, the synchronization entity doesn't need to filter
                  // any further
                  // 'create_entities' => TRUE,
                  // 'update_entities' => TRUE,
                  // 'delete_entities' => TRUE,
                  // 'dependent_entities_only'  => FALSE,.
                  'create_entities' => TRUE,
                  'update_entities' => TRUE,
                  'delete_entities' => boolval($type['export_deletion_settings']['export_deletion']),
                  'force_updates' => $cms_content_sync_disable_optimization,
                  'dependent_entities_only' => $export != Pool::POOL_USAGE_FORBID && $type['export'] == ExportIntent::EXPORT_AS_DEPENDENCY,
                  'update_none_when_loading' => TRUE,
                  'exclude_reference_properties' => [
                    'pSource',
                  ],
                ],
                'status' => 'READY',
                'source_connection_id' => $local_connection_id,
                'destination_connection_id' => $pool_connection_id,
              ],
            ],
          ];
        }
      }
    }

    if ($extend_only) {
      return;
    }

    // Always export required pools as well to prevent any potential issues
    // TODO: Optimize when called via Drush to not send the same pool requests for multiple Flows.
    $pool_operations = [];
    foreach ($export_pools as $pool) {
      $exporter = new SyncCorePoolExport($pool);

      $pool_operations = array_merge(
        $pool_operations,
        $exporter->prepareBatch(!!count($pool_operations))
      );
    }

    $operations = array_merge($pool_operations, $operations);
  }

  /**
   * Delete the synchronizations from this connection.
   */
  public function remove($removedOnly = TRUE) {
    // @TODO Refactor for pool changes
    return TRUE;
  }

}
