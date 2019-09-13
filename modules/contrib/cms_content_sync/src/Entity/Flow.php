<?php

namespace Drupal\cms_content_sync\Entity;

use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\cms_content_sync\SyncCoreFlowExport;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Drupal\Core\Entity\EntityInterface;

/**
 * Defines the "CMS Content Sync - Flow" entity.
 *
 * @ConfigEntityType(
 *   id = "cms_content_sync_flow",
 *   label = @Translation("CMS Content Sync - Flow"),
 *   handlers = {
 *     "list_builder" = "Drupal\cms_content_sync\Controller\FlowListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cms_content_sync\Form\FlowForm",
 *       "edit" = "Drupal\cms_content_sync\Form\FlowForm",
 *       "delete" = "Drupal\cms_content_sync\Form\FlowDeleteForm",
 *     }
 *   },
 *   config_prefix = "flow",
 *   admin_permission = "administer cms content sync",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "name",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/services/cms_content_sync/synchronizations/{cms_content_sync_flow}/edit",
 *     "delete-form" = "/admin/config/services/cms_content_sync/synchronizations/{cms_content_sync_flow}/delete",
 *   }
 * )
 */
class Flow extends ConfigEntityBase implements FlowInterface {
  /**
   * @var string HANDLER_IGNORE
   *    Ignore this entity type / bundle / field completely.
   */
  const HANDLER_IGNORE = 'ignore';

  /**
   * @var string PREVIEW_DISABLED
   *    Hide these entities completely.
   */
  const PREVIEW_DISABLED = 'disabled';

  /**
   * @var string PREVIEW_TABLE
   *    Show these entities in a table view.
   */
  const PREVIEW_TABLE = 'table';

  /**
   * The Flow ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Flow name.
   *
   * @var string
   */
  public $name;

  /**
   * The Flow entities.
   *
   * @TODO Refactor to use $entities and within that add the ['fields'] config
   *
   * @var array
   */
  public $sync_entities;

  /**
   * Ensure that pools are imported before the flows.
   */
  public function calculateDependencies() {
    parent::calculateDependencies();

    $pools = Pool::getAll();
    foreach ($pools as $pool) {
      if ($this->usesPool($pool)) {
        $this->addDependency('config', 'cms_content_sync.pool.' . $pool->id);
      }
    }
  }

  /**
   * Get all flows exporting this entity.
   *
   * @param $entity
   * @param $action
   * @param bool $include_dependencies
   *
   * @return array|\Drupal\cms_content_sync\Entity\Flow[]
   *
   * @throws \Exception
   */
  public static function getExportFlows($entity, $action, $include_dependencies = TRUE) {
    $flows = ExportIntent::getFlowsForEntity(
      $entity,
      ExportIntent::EXPORT_AUTOMATICALLY,
      $action
    );
    if ($include_dependencies && !count($flows)) {
      $flows = ExportIntent::getFlowsForEntity(
        $entity,
        ExportIntent::EXPORT_AS_DEPENDENCY,
        $action
      );
      if (count($flows)) {
        $infos = EntityStatus::getInfosForEntity(
          $entity->getEntityTypeId(),
          $entity->uuid()
        );
        $exported = [];
        foreach ($infos as $info) {
          if (!in_array($info->getFlow(), $flows)) {
            continue;
          }
          if (in_array($info->getFlow(), $exported)) {
            continue;
          }
          if (!$info->getLastExport()) {
            continue;
          }
          $exported[] = $info->getFlow();
        }
        $flows = $exported;
      }
    }

    return $flows;
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    try {
      foreach ($entities as $entity) {
        $exporter = new SyncCoreFlowExport($entity);
        $exporter->remove(FALSE);
      }
    }
    catch (RequestException $e) {
      $messenger = \Drupal::messenger();
      $messenger->addError(t('The Sync Core server could not be accessed. Please check the connection.'));
      throw new AccessDeniedHttpException();
    }
  }

  /**
   * Get a unique version hash for the configuration of the provided entity type
   * and bundle.
   *
   * @param string $type_name
   *   The entity type in question.
   * @param string $bundle_name
   *   The bundle in question.
   *
   * @return string
   *   A 32 character MD5 hash of all important configuration for this entity
   *   type and bundle, representing it's current state and allowing potential
   *   conflicts from entity type updates to be handled smoothly.
   */
  public static function getEntityTypeVersion($type_name, $bundle_name) {
    // TODO: Include export_config keys in version definition for config entity types like webforms.
    if (EntityHandlerPluginManager::isEntityTypeFieldable($type_name)) {
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $field_definitions = $entityFieldManager->getFieldDefinitions($type_name, $bundle_name);

      $field_definitions_array = (array) $field_definitions;
      unset($field_definitions_array['field_cms_content_synced']);

      $field_names = array_keys($field_definitions_array);
      sort($field_names);

      $version = json_encode($field_names);
    }
    else {
      $version = '';
    }

    $version = md5($version);
    return $version;
  }

  /**
   * Check whether the local deletion of the given entity is allowed.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public static function isLocalDeletionAllowed(EntityInterface $entity) {
    if (!$entity->uuid()) {
      return TRUE;
    }
    $entity_status = EntityStatus::getInfosForEntity(
      $entity->getEntityTypeId(),
      $entity->uuid()
    );
    foreach ($entity_status as $info) {
      if (!$info->getLastImport() || $info->isSourceEntity()) {
        continue;
      }
      $flow = $info->getFlow();
      if (!$flow) {
        continue;
      }

      $config = $flow->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle(), TRUE);
      if ($config['import'] === ImportIntent::IMPORT_DISABLED) {
        continue;
      }
      if (!boolval($config['import_deletion_settings']['allow_local_deletion_of_import'])) {
        return FALSE;
      }
    }
    return TRUE;
  }

  /**
   * Get a list of all pools that are used for exporting this entity, either
   * automatically or manually selected.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see ::ACTION_*}.
   *
   * @return \Drupal\cms_content_sync\Entity\Pool[]
   */
  public function getUsedImportPools($entity_type, $bundle) {
    $config = $this->getEntityTypeConfig($entity_type, $bundle);

    $result = [];
    $pools = Pool::getAll();

    foreach ($config['import_pools'] as $id => $setting) {
      $pool = $pools[$id];

      if ($setting == Pool::POOL_USAGE_FORBID) {
        continue;
      }

      $result[] = $pool;
    }

    return $result;
  }

  /**
   * Get a list of all pools that are used for exporting this entity, either
   * automatically or manually selected.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string|string[] $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see ::ACTION_*}.
   * @param bool $include_forced
   *   Include forced pools. Otherwise only use-selected / referenced ones.
   *
   * @return \Drupal\cms_content_sync\Entity\Pool[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function getUsedExportPools(EntityInterface $entity, $reason, $action, $include_forced = TRUE) {
    $config = $this->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle());
    if (!$this->canExportEntity($entity, $reason, $action)) {
      return [];
    }

    $result = [];
    $pools = Pool::getAll();

    foreach ($config['export_pools'] as $id => $setting) {
      if (!isset($pools[$id])) {
        continue;
      }
      $pool = $pools[$id];

      if ($setting == Pool::POOL_USAGE_FORBID) {
        continue;
      }

      if ($setting == Pool::POOL_USAGE_FORCE) {
        if ($include_forced) {
          $result[$id] = $pool;
        }
        continue;
      }

      $entity_status = EntityStatus::getInfoForEntity($entity->getEntityTypeId(), $entity->uuid(), $this, $pool);
      if ($entity_status && $entity_status->isExportEnabled()) {
        $result[$id] = $pool;
      }
    }

    return $result;
  }

  /**
   * Ask this synchronization whether or not it can export the given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string|string[] $reason
   * @param string $action
   * @param null|Pool $pool
   *
   * @return bool
   */
  public function canExportEntity(EntityInterface $entity, $reason, $action = SyncIntent::ACTION_CREATE, $pool = NULL) {
    return $this->canExportEntityType($entity->getEntityTypeId(), $entity->bundle(), $reason, $action, $pool);
  }

  /**
   * Ask this synchronization whether or not it can export the given entity type and optionally bundle.
   *
   * @param string $entity_type_name
   * @param string|null $bundle_name
   * @param string|string[] $reason
   * @param string $action
   * @param null|Pool $pool
   *
   * @return bool
   */
  public function canExportEntityType($entity_type_name, $bundle_name, $reason, $action = SyncIntent::ACTION_CREATE, $pool = NULL) {
    if (is_string($reason)) {
      if ($reason === ExportIntent::EXPORT_ANY || $reason === ExportIntent::EXPORT_FORCED) {
        $reason = [
          ExportIntent::EXPORT_AUTOMATICALLY,
          ExportIntent::EXPORT_MANUALLY,
          ExportIntent::EXPORT_AS_DEPENDENCY,
        ];
      }
      else {
        $reason = [$reason];
      }
    }

    if (!$bundle_name) {
      foreach ($this->getEntityTypeConfig($entity_type_name) as $config) {
        if ($this->canExportEntityType($entity_type_name, $config['bundle_name'], $reason, $action, $pool)) {
          return TRUE;
        }
      }
      return FALSE;
    }

    $config = $this->getEntityTypeConfig($entity_type_name, $bundle_name);
    if (empty($config) || $config['handler'] == self::HANDLER_IGNORE) {
      return FALSE;
    }

    if ($config['export'] == ExportIntent::EXPORT_DISABLED) {
      return FALSE;
    }

    if ($action == SyncIntent::ACTION_DELETE && !boolval($config['export_deletion_settings']['export_deletion'])) {
      return FALSE;
    }

    if ($pool) {
      if (empty($config['export_pools'][$pool->id]) || $config['export_pools'][$pool->id] == Pool::POOL_USAGE_FORBID) {
        return FALSE;
      }
    }

    return in_array($config['export'], $reason);
  }

  /**
   * @var \Drupal\cms_content_sync\Entity\Flow[]
   *   All content synchronization configs. Use {@see Flow::getAll}
   *   to request them.
   */
  public static $all = NULL;

  /**
   * Load all entities.
   *
   * Load all cms_content_sync_flow entities and add overrides from global $config.
   *
   * @param bool $skip_inactive
   *   Do not return inactive flows by default.
   *
   * @return \Drupal\cms_content_sync\Entity\Flow[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getAll($skip_inactive = TRUE) {
    if ($skip_inactive && self::$all !== NULL) {
      return self::$all;
    }

    /**
     * @var Flow[] $configurations
     */
    $configurations = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->loadMultiple();

    foreach ($configurations as $id => &$configuration) {
      global $config;
      $config_name = 'cms_content_sync.flow.' . $id;
      if (!isset($config[$config_name]) || empty($config[$config_name])) {
        continue;
      }

      foreach ($config[$config_name] as $key => $new_value) {
        if ($key == 'sync_entities') {
          foreach ($new_value as $sync_key => $options) {
            foreach ($options as $options_key => $setting) {
              if (is_array($setting)) {
                foreach ($setting as $setting_key => $set) {
                  $configuration->sync_entities[$sync_key][$options_key][$setting_key] = $set;
                }
              }
              else {
                $configuration->sync_entities[$sync_key][$options_key] = $setting;
              }
            }
          }
          continue;
        }
        $configuration->set($key, $new_value);
      }
      $configuration->getEntityTypeConfig();
    }

    if ($skip_inactive) {
      $result = [];
      foreach ($configurations as $id => $flow) {
        if ($flow->get('status')) {
          $result[$id] = $flow;
        }
      }

      $configurations = $result;

      self::$all = $configurations;
    }

    return $configurations;
  }

  /**
   * Get all synchronizations that allow the provided entity import.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   *
   * @return \Drupal\cms_content_sync\Entity\Flow[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getImportSynchronizationsForEntityType($entity_type_name, $bundle_name, $reason, $action = SyncIntent::ACTION_CREATE) {
    $flows = self::getAll();

    $result = [];

    foreach ($flows as $flow) {
      if ($flow->canImportEntity($entity_type_name, $bundle_name, $reason, $action)) {
        $result[] = $flow;
      }
    }

    return $result;
  }

  /**
   * Get the first synchronization that allows the import of the provided entity
   * type.
   *
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   *
   * @return \Drupal\cms_content_sync\Entity\Flow|null
   */
  public static function getFlowForApiAndEntityType($pool, $entity_type_name, $bundle_name, $reason, $action = SyncIntent::ACTION_CREATE) {
    $flows = self::getAll();

    foreach ($flows as $flow) {
      if (!$flow->canImportEntity($entity_type_name, $bundle_name, $reason, $action)) {
        continue;
      }
      if ($pool && !in_array($pool, $flow->getUsedImportPools($entity_type_name, $bundle_name))) {
        continue;
      }

      return $flow;
    }

    return NULL;
  }

  /**
   * Ask this synchronization whether or not it can export the provided entity.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $reason
   * @param string $action
   *
   * @return bool
   */
  public function canImportEntity($entity_type_name, $bundle_name, $reason, $action = SyncIntent::ACTION_CREATE) {
    $config = $this->getEntityTypeConfig($entity_type_name, $bundle_name);
    if (empty($config) || $config['handler'] == self::HANDLER_IGNORE) {
      return FALSE;
    }

    if ($config['import'] == ImportIntent::IMPORT_DISABLED) {
      return FALSE;
    }

    if ($action == SyncIntent::ACTION_DELETE && !boolval($config['import_deletion_settings']['import_deletion'])) {
      return FALSE;
    }

    // If any handler is available, we can import this entity.
    if ($reason == ImportIntent::IMPORT_FORCED) {
      return TRUE;
    }

    // We allow all entity updates.
    if ($config['import'] == ImportIntent::IMPORT_AUTOMATICALLY) {
      return TRUE;
    }

    // Once imported manually, updates will arrive automatically.
    if ($reason == ImportIntent::IMPORT_AUTOMATICALLY && $config['import'] == ImportIntent::IMPORT_MANUALLY) {
      if ($action == SyncIntent::ACTION_UPDATE || $action == SyncIntent::ACTION_DELETE) {
        return TRUE;
      }
    }

    return $config['import'] == $reason;
  }

  /**
   * Ask this synchronization whether it supports the provided entity.
   * Returns false if either the entity type is not known or the config handler
   * is set to {@see Flow::HANDLER_IGNORE}.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  public function supportsEntity(EntityInterface $entity) {
    $config = $this->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle());
    if (empty($config) || empty($config['handler'])) {
      return FALSE;
    }

    return $config['handler'] != self::HANDLER_IGNORE;
  }

  /**
   * Check if the given pool is used by this Flow. If any handler set the flow
   * as FORCE or ALLOW, this will return TRUE.
   *
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *
   * @return bool
   */
  public function usesPool($pool) {
    foreach ($this->getEntityTypeConfig(NULL, NULL, TRUE) as $config) {
      if ($config['handler'] == Flow::HANDLER_IGNORE) {
        continue;
      }

      if ($config['export'] != ExportIntent::EXPORT_DISABLED) {
        if (!empty($config['export_pools'][$pool->id]) && $config['export_pools'][$pool->id] != Pool::POOL_USAGE_FORBID) {
          return TRUE;
        }
      }

      if ($config['import'] != ImportIntent::IMPORT_DISABLED) {
        if (!empty($config['import_pools'][$pool->id]) && $config['import_pools'][$pool->id] != Pool::POOL_USAGE_FORBID) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Unset the flow version warning.
   */
  public function resetVersionWarning() {
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('cms_content_sync_developer')) {
      $developer_config = \Drupal::service('config.factory')->getEditable('cms_content_sync.developer');
      $mismatching_versions = $developer_config->get('version_mismatch');
      if (!empty($mismatching_versions)) {
        unset($mismatching_versions[$this->id()]);
        $developer_config->set('version_mismatch', $mismatching_versions)->save();
      }
    }
  }

  /**
   * Update the version of an entity type bundle within a flow configuration.
   *
   * @param $entity_type
   * @param $bundle
   */
  public function updateEntityTypeBundleVersion($entity_type, $bundle) {
    // Get active version.
    $active_version = Flow::getEntityTypeVersion($entity_type, $bundle);

    // Get version from config.
    $config_key = $entity_type . '-' . $bundle;
    $flow_config = \Drupal::service('config.factory')->getEditable('cms_content_sync.flow.' . $this->id());
    $config_version = $flow_config->get('sync_entities.' . $config_key . '.version');

    // Only update if required.
    if ($active_version != $config_version) {
      $default = self::getDefaultFieldConfigForEntityType($entity_type, $bundle, $this->sync_entities);
      foreach ($default as $id => $config) {
        $flow_config->set('sync_entities.' . $id, $config);
      }
      $flow_config->set('sync_entities.' . $config_key . '.version', $active_version);
      $flow_config->save();
      drupal_set_message('CMS Content Sync - Flow: ' . $this->label() . ' has been updated. Ensure to export the new configuration to the sync core.');
    }
  }

  /**
   * @param $type
   * @param $bundle
   * @param null $existing
   * @param null $field
   *
   * @return array
   */
  public static function getDefaultFieldConfigForEntityType($type, $bundle, $existing = NULL, $field = NULL) {
    if ($field) {
      $field_default_values = [
        'export' => NULL,
        'import' => NULL,
      ];

      $entity_type = \Drupal::entityTypeManager()->getDefinition($type);
      $forbidden_fields = [
        // These are not relevant or misleading when synchronized.
        'revision_default',
        'revision_translation_affected',
        'content_translation_outdated',
        // Field collections.
        'host_type',
        // Files.
        'uri',
        'filemime',
        'filesize',
        // Media.
        'thumbnail',
        // Taxonomy.
        'parent',
        // These are standard fields defined by the Flow
        // Entity type that entities may not override (otherwise
        // these fields will collide with CMS Content Sync functionality)
        'source',
        'source_id',
        'source_connection_id',
        'preview',
        'url',
        'apiu_translation',
        'metadata',
        'embed_entities',
        'menu_link',
        'title',
        'created',
        'changed',
        'uuid',
        $entity_type->getKey('bundle'),
        $entity_type->getKey('id'),
        $entity_type->getKey('uuid'),
        $entity_type->getKey('label'),
        $entity_type->getKey('revision'),
      ];

      if (in_array($field, $forbidden_fields) !== FALSE) {
        $field_default_values['handler'] = 'ignore';
        $field_default_values['export'] = ExportIntent::EXPORT_DISABLED;
        $field_default_values['import'] = ImportIntent::IMPORT_DISABLED;
        return $field_default_values;
      }

      $field_handler_service = \Drupal::service('plugin.manager.cms_content_sync_field_handler');
      $field_definition = \Drupal::service('entity_field.manager')->getFieldDefinitions($type, $bundle)[$field];

      $field_handlers = $field_handler_service->getHandlerOptions($type, $bundle, $field, $field_definition, TRUE);
      if (empty($field_handlers)) {
        throw new \Exception('Unsupported field type ' . $field_definition->getType() . ' for field ' . $type . '.' . $bundle . '.' . $field);
      }
      reset($field_handlers);
      $handler_id = empty($field_default_values['handler']) ? key($field_handlers) : $field_default_values['handler'];

      $field_default_values['handler'] = $handler_id;
      $field_default_values['export'] = ExportIntent::EXPORT_AUTOMATICALLY;
      $field_default_values['import'] = ImportIntent::IMPORT_AUTOMATICALLY;

      $handler = $field_handler_service->createInstance($handler_id, [
        'entity_type_name' => $type,
        'bundle_name' => $bundle,
        'field_name' => $field,
        'field_definition' => $field_definition,
        'settings' => $field_default_values,
        'sync' => NULL,
      ]);

      $advanced_settings = $handler->getHandlerSettings($field_default_values);
      if (count($advanced_settings)) {
        foreach ($advanced_settings as $name => $element) {
          $field_default_values['handler_settings'][$name] = $element['#default_value'];
        }
      }

      return $field_default_values;
    }
    $field_definition = \Drupal::service('entity_field.manager')->getFieldDefinitions($type, $bundle);

    $result = [];

    foreach ($field_definition as $key => $field) {
      $field_id = $type . '-' . $bundle . '-' . $key;
      if ($existing && isset($existing[$field_id])) {
        continue;
      }
      $result[$field_id] = self::getDefaultFieldConfigForEntityType($type, $bundle, NULL, $key);
    }
    return $result;
  }

  /**
   * Get the config for the given entity type or all entity types.
   *
   * @param string $entity_type
   * @param string $entity_bundle
   * @param bool $used_only
   *   Return only the configs where a handler is set.
   *
   * @return array
   */
  public function getEntityTypeConfig($entity_type = NULL, $entity_bundle = NULL, $used_only = FALSE) {
    $entity_types = $this->sync_entities;

    $result = [];

    foreach ($entity_types as $id => &$type) {
      // Ignore field definitions.
      if (substr_count($id, '-') != 1) {
        continue;
      }

      if ($used_only && $type['handler'] == self::HANDLER_IGNORE) {
        continue;
      }

      preg_match('/^(.+)-(.+)$/', $id, $matches);

      $entity_type_name = $matches[1];
      $bundle_name      = $matches[2];

      if ($entity_type && $entity_type_name != $entity_type) {
        continue;
      }
      if ($entity_bundle && $bundle_name != $entity_bundle) {
        continue;
      }

      // If this is called before being saved, we want to have version etc.
      // available still.
      if (empty($type['version'])) {
        $type['version']          = Flow::getEntityTypeVersion($entity_type_name, $bundle_name);
        $type['entity_type_name'] = $entity_type_name;
        $type['bundle_name']      = $bundle_name;
      }

      if ($entity_type && $entity_bundle) {
        return $type;
      }

      $result[$id] = $type;
    }

    return $result;
  }

  /**
   * The the entity type handler for the given config.
   *
   * @param $config
   *   {@see Flow::getEntityTypeConfig()}
   *
   * @return \Drupal\cms_content_sync\Plugin\EntityHandlerInterface
   */
  public function getEntityTypeHandler($config) {
    $entityPluginManager = \Drupal::service('plugin.manager.cms_content_sync_entity_handler');

    $handler = $entityPluginManager->createInstance(
      $config['handler'],
      [
        'entity_type_name' => $config['entity_type_name'],
        'bundle_name' => $config['bundle_name'],
        'settings' => $config,
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * Get the correct field handler instance for this entity type and field
   * config.
   *
   * @param $entity_type_name
   * @param $bundle_name
   * @param $field_name
   *
   * @return \Drupal\cms_content_sync\Plugin\FieldHandlerInterface
   */
  public function getFieldHandler($entity_type_name, $bundle_name, $field_name) {
    $fieldPluginManager = \Drupal::service('plugin.manager.cms_content_sync_field_handler');

    $key = $entity_type_name . '-' . $bundle_name . '-' . $field_name;
    if (empty($this->sync_entities[$key])) {
      return NULL;
    }

    if ($this->sync_entities[$key]['handler'] == self::HANDLER_IGNORE) {
      return NULL;
    }

    $entityFieldManager = \Drupal::service('entity_field.manager');
    $field_definition = $entityFieldManager->getFieldDefinitions($entity_type_name, $bundle_name)[$field_name];

    $handler = $fieldPluginManager->createInstance(
      $this->sync_entities[$key]['handler'],
      [
        'entity_type_name' => $entity_type_name,
        'bundle_name' => $bundle_name,
        'field_name' => $field_name,
        'field_definition' => $field_definition,
        'settings' => $this->sync_entities[$key],
        'sync' => $this,
      ]
    );

    return $handler;
  }

  /**
   * Get the settings for the given field.
   *
   * @param $entity_type_name
   * @param $bundle_name
   * @param $field_name
   *
   * @return array
   */
  public function getFieldHandlerConfig($entity_type_name, $bundle_name, $field_name) {
    $key = $entity_type_name . '-' . $bundle_name . '-' . $field_name;
    if (!isset($this->sync_entities[$key])) {
      return NULL;
    }
    return $this->sync_entities[$key];
  }

  /**
   * Get the preview type.
   *
   * @param $entity_type_name
   * @param $bundle_name
   *
   * @return string
   */
  public function getPreviewType($entity_type_name, $bundle_name) {
    $previews_enabled = boolval(\Drupal::config('cms_content_sync.settings')
      ->get('cms_content_sync_enable_preview'));
    if (!$previews_enabled) {
      return Flow::PREVIEW_DISABLED;
    }

    $key = $entity_type_name . '-' . $bundle_name;
    $settings = $this->sync_entities[$key];
    if (empty($settings['preview'])) {
      return Flow::PREVIEW_DISABLED;
    }
    else {
      return $settings['preview'];
    }
  }

  /**
   * Return all entity type configs with export enabled.
   *
   * @param $export_type
   *
   * @return array
   */
  public function getExportableEntityTypes($export_type = NULL, $entity_type = NULL) {

    $exportable_entity_types = [];
    $entity_types = $this->getEntityTypeConfig();

    foreach ($entity_types as $key => $entity_type) {
      if (is_null($export_type) && $entity_type['export'] != ExportIntent::EXPORT_DISABLED) {
        $exportable_entity_type[$key] = $entity_type;
      }
      elseif ($entity_type['export'] == $export_type) {
        $exportable_entity_type[$key] = $entity_type;
      }
    }

    return $exportable_entity_types;
  }

  /**
   * Return all entity type configs with import enabled.
   *
   * @param $import_type
   *
   * @return array
   */
  public function getImportableEntityTypes($import_type = NULL, $entity_type = NULL) {

    $importable_entity_types = [];
    $entity_types = $this->getEntityTypeConfig();

    foreach ($entity_types as $key => $entity_type) {
      if (is_null($import_type) && $entity_type['import'] != ImportIntent::IMPORT_DISABLED) {
        $importable_entity_types[$key] = $entity_type;
      }
      elseif ($entity_type['import'] == $import_type) {
        $importable_entity_types[$key] = $entity_type;
      }
    }

    return $importable_entity_types;
  }

  /**
   * Create a flow configuration programmatically.
   *
   * @param $flow_name
   * @param string $flow_id
   * @param bool $status
   * @param array $dependencies
   * @param $configurations
   *
   * @param bool $force_update
   *
   * @return mixed|string
   */
  public static function createFlow($flow_name, $flow_id = '', $status = TRUE, $dependencies = [], $configurations, $force_update = FALSE) {

    $flows = Flow::getAll(FALSE);

    // If no flow_id is given, create one.
    if (empty($flow_id)) {
      $flow_id = strtolower($flow_name);
      $flow_id = preg_replace('@[^a-z0-9_]+@', '_', $flow_id);
    }

    if (!$force_update && array_key_exists($flow_id, $flows)) {
      drupal_set_message('A flow with the machine name ' . $flow_id . ' does already exist. Therefor the creation has been skipped.', 'warning');
    }
    else {
      $uuid_service = \Drupal::service('uuid');
      $language_manager = \Drupal::service('language_manager');
      $default_language = $language_manager->getDefaultLanguage();
      $config = [
        'dependencies' => $dependencies,
      ];

      $flow_config = \Drupal::service('config.factory')->getEditable('cms_content_sync.flow.' . $flow_id);
      // Setup base configurations.
      $flow_config
        ->set('uuid', $uuid_service->generate())
        ->set('langcode', $default_language->getId())
        ->set('status', TRUE)
        ->set('id', $flow_id)
        ->set('name', $flow_name)
        ->set('config', $config)
        ->set('sync_entities', []);

      // Configure entity types.
      foreach ($configurations as $entity_type_key => $bundles) {
        foreach ($bundles as $bundle_key => $bundle) {

          $entityPluginManager = \Drupal::service('plugin.manager.cms_content_sync_entity_handler');
          $entity_handler = $entityPluginManager->getHandlerOptions($entity_type_key, $bundle_key);
          // @ToDo: Can we do better than this?
          $entity_handler = reset($entity_handler);

          // Set configurations.
          $flow_config->set('sync_entities.' . $entity_type_key . '-' . $bundle_key, [
            'handler' => $entity_handler['id'],
            'entity_type_name' => $entity_type_key,
            'bundle_name' => $bundle_key,
            'version' => Flow::getEntityTypeVersion($entity_type_key, $bundle_key),
            'export' => $bundle['export_configuration']['behavior'] ?? ExportIntent::EXPORT_DISABLED,
            'export_deletion_settings' => [
              'export_deletion' => $bundle['export_configuration']['export_deletion_settings'] ?? '',
            ],
            'export_pools' => $bundle['export_configuration']['export_pools'] ?? [],
            'import' => $bundle['import_configuration']['behavior'] ?? ImportIntent::IMPORT_DISABLED,
            'import_deletion_settings' => [
              'import_deletion' => $bundle['import_configuration']['import_deletion'] ?? 0,
              'allow_local_deletion_of_import' => $bundle['import_configuration']['allow_local_deletion_of_import'] ?? 0,
            ],
            'import_updates' => $bundle['import_configuration']['import_updates'] ?? ImportIntent::IMPORT_UPDATE_FORCE,
            'import_pools' => $bundle['import_configuration']['import_pools'] ?? [],
            'pool_export_widget_type' => 'checkboxes',
            'preview' => 'table',
          ]);

          /**
           * @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager
           */
          $entityFieldManager = \Drupal::service('entity_field.manager');
          $fields = $entityFieldManager->getFieldDefinitions($entity_type_key, $bundle_key);
          foreach (Flow::getDefaultFieldConfigForEntityType($entity_type_key, $bundle_key) as $field_id => $field_config) {
            if (!empty($bundle['tags'])) {
              list(,, $field_name) = explode('-', $field_id);

              $field = $fields[$field_name];
              if ($field && $field->getType() == 'entity_reference' && $field->getSetting('target_type') == 'taxonomy_term') {
                $bundles = $field->getSetting('target_bundles');
                if (!$bundles) {
                  $field_settings = $field->getSettings();
                  $bundles = $field_settings['handler_settings']['target_bundles'];
                }
                if (is_array($bundles)) {
                  foreach ($bundle['tags'] as $tag) {
                    if (in_array($tag->bundle(), $bundles)) {
                      $field_config["handler_settings"]["subscribe_only_to"][] = [
                        "type" => "taxonomy_term",
                        "bundle" => $tag->bundle(),
                        "uuid" => $tag->uuid(),
                      ];
                    }
                  }
                }
              }
            }

            $flow_config->set('sync_entities.' . $field_id, $field_config);
          }
        }
      }
      $flow_config->save();
    }
    return $flow_id;
  }

}
