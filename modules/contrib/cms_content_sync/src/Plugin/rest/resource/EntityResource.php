<?php

namespace Drupal\cms_content_sync\Plugin\rest\resource;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Exception\SyncException;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\ModifiedResourceResponse;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\Core\Render\Renderer;
use Drupal\rest\ResourceResponse;
use http\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides entity interfaces for CMS Content Sync, allowing Sync Core to
 * request and manipulate entities.
 *
 * @RestResource(
 *   id = "cms_content_sync_entity_resource",
 *   label = @Translation("Flow Entity Resource"),
 *   uri_paths = {
 *     "canonical" = "/rest/cms-content-sync/{api}/{entity_type}/{entity_bundle}/{entity_type_version}/{entity_uuid}",
 *     "https://www.drupal.org/link-relations/create" = "/rest/cms-content-sync/{api}/{entity_type}/{entity_bundle}/{entity_type_version}"
 *   }
 * )
 */
class EntityResource extends ResourceBase {

  /**
   * @var int CODE_INVALID_DATA The provided data could not be interpreted.
   */
  const CODE_INVALID_DATA = 401;

  /**
   * @var int CODE_NOT_FOUND The entity doesn't exist or can't be accessed
   */
  const CODE_NOT_FOUND = 404;

  /**
   * @var string TYPE_HAS_NOT_BEEN_FOUND
   *    The entity type doesn't exist or can't be accessed
   */
  const TYPE_HAS_NOT_BEEN_FOUND = 'The entity type has not been found.';

  /**
   * @var string TYPE_HAS_INCOMPATIBLE_VERSION The version hashes are different
   */
  const TYPE_HAS_INCOMPATIBLE_VERSION = 'The entity type has an incompatible version.';

  /**
   * @var string READ_LIST_ENTITY_ID
   *   "ID" used to perform list requests in the
   *   {@see EntityResource}. Should be refactored later.
   */
  const READ_LIST_ENTITY_ID = '0';

  /**
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderedManager;

  /**
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected $entityRepository;

  /**
   * Constructs an object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   An entity type bundle info instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   An entity type manager instance.
   * @param \Drupal\Core\Render\Renderer $render_manager
   *   A rendered instance.
   * @param \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository
   *   The entity repository interface.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    LoggerInterface $logger,
    EntityTypeBundleInfo $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $render_manager,
    EntityRepositoryInterface $entity_repository
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $serializer_formats,
      $logger
    );

    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderedManager = $render_manager;
    $this->entityRepository = $entity_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('entity.repository')
    );
  }

  /**
   * Get the base URL of the site. Either the configured one or global $base_url
   * as default.
   *
   * @return string
   *
   * @throws \Exception
   */
  public static function getBaseUrl() {
    global $base_url;

    // Check if the base_url is overwritten within the settings.
    $cms_content_sync_settings = \Drupal::config('cms_content_sync.settings');
    $cms_content_sync_base_url = $cms_content_sync_settings->get('cms_content_sync_base_url');
    if (isset($cms_content_sync_settings) && $cms_content_sync_base_url != '') {

      // Validate the Base URL.
      try {
        if (UrlHelper::isValid($cms_content_sync_base_url, TRUE) && Unicode::substr($cms_content_sync_base_url, -1) !== '/') {
          return $cms_content_sync_base_url;
        }
        else {
          throw new \Exception(t('The defined CMS Content Sync Base URL, is not a valid URL. Ensure that it does not contain a trailing slash.'));
        }
      }
      catch (Exception $e) {
        \Drupal::logger('cms_content_sync')->error($e);
      }
    }

    return $base_url;
  }

  /**
   * Get the absolute URL that Sync Core should use to create, update or delete
   * an entity.
   *
   * @param string $api_id
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $version
   * @param string $entity_uuid
   *
   * @return string
   */
  public static function getInternalUrl($api_id, $entity_type_name, $bundle_name, $version, $entity_uuid = NULL) {
    $export_url = EntityResource::getBaseUrl();

    $url = sprintf('%s/rest/cms-content-sync/%s/%s/%s/%s',
      $export_url,
      $api_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
    if ($entity_uuid) {
      $url .= '/' . $entity_uuid;
    }
    $url .= '?_format=json&is_dependency=[is_dependency]&is_manual=[is_manual]';
    return $url;
  }

  /**
   * Responds to entity GET requests.
   *
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function get($entity_type, $entity_bundle, $entity_uuid) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    $entity_types_keys = array_keys($entity_types);
    if (in_array($entity_type, $entity_types_keys)) {
      $entity_type_entity = \Drupal::entityTypeManager()
        ->getStorage($entity_type)->getEntityType();

      $query = \Drupal::entityQuery($entity_type);
      if ($bundle = $entity_type_entity->getKey('bundle')) {
        $query->condition($bundle, $entity_bundle);
      }
      if (!empty($entity_uuid)) {
        $query->condition('uuid', $entity_uuid);
      }
      if ($entity_type == 'file') {
        $query->condition('status', FILE_STATUS_PERMANENT);
      }

      $entity_ids = array_values($query->execute());

      $entities = array_values(\Drupal::entityTypeManager()->getStorage($entity_type)->loadMultiple($entity_ids));
      $items    = [];

      foreach ($entities as $entity) {
        // $sync   = Flow::getFlowsForEntity($entity, ExportIntent::EXPORT_AUTOMATICALLY);.
        $result = [];
        // @TODO add export all option
        // $sync->getSerializedEntity($result, $entity, ExportIntent::EXPORT_AUTOMATICALLY);
        $status = FALSE;
        if ($status) {
          $items[] = $result;
        }
      }

      if (!empty($entity_uuid)) {
        $items = $items[0];
      }

      return new ModifiedResourceResponse($items);
    }

    return new ResourceResponse(
      ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
    );

  }

  /**
   * Responds to entity PATCH requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type to compare ours against.
   * @param string $entity_uuid
   *   The uuid of an entity.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function patch($api, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, SyncIntent::ACTION_UPDATE);
  }

  /**
   * Responds to entity DELETE requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The name of an entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type.
   * @param string $entity_uuid
   *   The uuid of an entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function delete($api, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, ['uuid' => $entity_uuid, 'id' => $entity_uuid], SyncIntent::ACTION_DELETE);
  }

  /**
   * Responds to entity POST requests.
   *
   * @param string $api
   *   The used content sync api.
   * @param string $entity_type
   *   The posted entity type.
   * @param string $entity_bundle
   *   The name of an entity bundle.
   * @param string $entity_type_version
   *   The version of the entity type.
   * @param array $data
   *   The data to be stored in the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   A list of entities of the given type and bundle.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function post($api, $entity_type, $entity_bundle, $entity_type_version, array $data) {
    return $this->handleIncomingEntity($api, $entity_type, $entity_bundle, $entity_type_version, $data, SyncIntent::ACTION_CREATE);
  }

  /**
   * Save that the import for the given entity failed.
   *
   * @param string $pool_id
   *   The Pool ID.
   * @param $entity_type
   *   The Entity Type ID.
   * @param $entity_bundle
   *   The bundle name.
   * @param $entity_type_version
   *   The requested entity type version.
   * @param $entity_uuid
   *   The entity UUID.
   * @param $failure_reason
   * @param $action
   * @param $reason
   * @param null $flow_id
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveFailedImport($pool_id, $entity_type, $entity_bundle, $entity_type_version, $entity_uuid, $failure_reason, $action, $reason, $flow_id = NULL) {
    $entity_status = EntityStatus::getInfoForEntity($entity_type, $entity_uuid, $flow_id, $pool_id);

    if (!$entity_status) {
      $entity_status = EntityStatus::create([
        'flow' => $flow_id ? $flow_id : EntityStatus::FLOW_NO_FLOW,
        'pool' => $pool_id,
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
        'entity_type_version' => $entity_type_version,
        'flags' => 0,
        'source_url' => NULL,
      ]);
    }

    $soft_fails = [
      ImportIntent::IMPORT_FAILED_UNKNOWN_POOL,
      ImportIntent::IMPORT_FAILED_NO_FLOW,
      ImportIntent::IMPORT_FAILED_HANDLER_DENIED,
    ];

    $soft = in_array($failure_reason, $soft_fails);

    $entity_status->didImportFail(TRUE, $soft, [
      'error' => $failure_reason,
      'action' => $action,
      'reason' => $reason,
      'bundle' => $entity_bundle,
    ]);

    $entity_status->save();
  }

  /**
   * @param string $api
   *   The API {@see Flow}.
   * @param string $entity_type_name
   *   The entity type of the processed entity.
   * @param string $entity_bundle
   *   The bundle of the processed entity.
   * @param string $entity_type_version
   *   The version the config was saved for.
   * @param array $data
   *   For {@see ::ACTION_CREATE} and
   *    {@see ::ACTION_UPDATE}: the data for the entity. Will
   *    be passed to {@see SyncIntent}.
   * @param string $action
   *   The {@see ::ACTION_*} to be performed on the entity.
   *
   * @return \Symfony\Component\HttpFoundation\Response The result (error,
   *   ignorance or success).
   * @throws SyncException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  private function handleIncomingEntity($api, $entity_type_name, $entity_bundle, $entity_type_version, array $data, $action) {
    $entity_types = $this->entityTypeBundleInfo->getAllBundleInfo();

    if (empty($entity_types[$entity_type_name])) {
      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
      );
    }

    $is_dependency = isset($_GET['is_dependency']) && $_GET['is_dependency'] == 'true';
    $is_manual     = isset($_GET['is_manual']) && $_GET['is_manual'] == 'true';
    $reason        = $is_dependency ? ImportIntent::IMPORT_AS_DEPENDENCY :
      ($is_manual ? ImportIntent::IMPORT_MANUALLY : ImportIntent::IMPORT_AUTOMATICALLY);

    // DELETE requests only give the ID of the config, not their UUID. So we need to grab the UUID from our local
    // database before continuing.
    if ($action === SyncIntent::ACTION_DELETE && EntityHandlerPluginManager::isEntityTypeConfiguration($entity_type_name)) {
      $entity_uuid = \Drupal::entityTypeManager()
        ->getStorage($entity_type_name)
        ->load($data['id'])
        ->uuid();
    }
    else {
      $entity_uuid = $data['uuid'];
    }

    $pool = Pool::getAll()[$api];
    if (empty($pool)) {
      \Drupal::logger('cms_content_sync')->warning('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $entity_uuid,
        '@not' => 'NO',
        '@message' => t('No pool config matches this request (@api).', [
          '@api' => $api,
        ])->render(),
      ]);

      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_UNKNOWN_POOL,
        $action,
        $reason
      );

      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
      );
    }

    $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, $reason, $action);

    // Deletion requests will not provide the "is_dependency" query param.
    if (empty($flow) && $action == SyncIntent::ACTION_DELETE && $reason != ImportIntent::IMPORT_AS_DEPENDENCY) {
      $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, ImportIntent::IMPORT_AS_DEPENDENCY, $action);
      if (!empty($flow)) {
        $reason = ImportIntent::IMPORT_AS_DEPENDENCY;
      }
    }

    if (empty($flow)) {
      \Drupal::logger('cms_content_sync')->notice('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $entity_uuid,
        '@not' => 'NO',
        '@message' => t('No synchronization config matches this request (dependency: @dependency, manual: @manual).', [
          '@dependency' => $is_dependency ? 'YES' : 'NO',
          '@manual' => $is_manual ? 'YES' : 'NO',
        ])->render(),
      ]);

      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_NO_FLOW,
        $action,
        $reason
      );

      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : ['message' => t(self::TYPE_HAS_NOT_BEEN_FOUND)->render()], self::CODE_NOT_FOUND
      );
    }

    $local_version = Flow::getEntityTypeVersion($entity_type_name, $entity_bundle);

    // Allow DELETE requests- when an entity is deleted, the entity type definition may have changed in the meantime
    // but this doesn't prevent us from deleting it. The version is only important for creations and updates.
    if ($entity_type_version != $local_version && $action != SyncIntent::ACTION_DELETE) {
      \Drupal::logger('cms_content_sync')->warning('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $entity_uuid,
        '@not' => 'NO',
        '@message' => t('The requested entity type version @requested doesn\'t match the local entity type version @local.', [
          '@requested' => $entity_type_version,
          '@local' => $local_version,
        ])->render(),
      ]);

      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_DIFFERENT_VERSION,
        $action,
        $reason,
        $flow->id
      );

      return new ResourceResponse(
        ['message' => t(self::TYPE_HAS_INCOMPATIBLE_VERSION)->render()], self::CODE_NOT_FOUND
      );
    }

    try {
      $intent = new ImportIntent($flow, $pool, $reason, $action, $entity_type_name, $entity_bundle, $data);
      $status = $intent->execute();
    }
    catch (SyncException $e) {
      $message = $e->parentException ? $e->parentException->getMessage() : (
        $e->errorCode == $e->getMessage() ? '' : $e->getMessage()
      );
      if ($message) {
        $message = t('Internal error @code: @message', [
          '@code' => $e->errorCode,
          '@message' => $message,
        ])->render();
      }
      else {
        $message = t('Internal error @code', [
          '@code' => $e->errorCode,
        ])->render();
      }

      \Drupal::logger('cms_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message' . "\n" . '@trace', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $entity_uuid,
        '@not' => 'NO',
        '@message' => $message,
        '@trace' => ($e->parentException ? $e->parentException->getTraceAsString() . "\n\n\n" : "") . $e->getTraceAsString(),
      ]);

      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_CONTENT_SYNC_ERROR,
        $action,
        $reason,
        $flow->id
      );

      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : [
          'message' => t('SyncException @code: @message',
            [
              '@code'     => $e->errorCode,
              '@message'  => $e->getMessage(),
            ]
          )->render(),
          'code' => $e->errorCode,
        ], 500
      );
    }
    catch (\Exception $e) {
      $message = $e->getMessage();

      \Drupal::logger('cms_content_sync')->error('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message' . "\n" . '@trace', [
        '@reason' => $reason,
        '@action' => $action,
        '@entity_type'  => $entity_type_name,
        '@bundle' => $entity_bundle,
        '@uuid' => $entity_uuid,
        '@not' => 'NO',
        '@message' => $message,
        '@trace' => $e->getTraceAsString(),
      ]);

      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_INTERNAL_ERROR,
        $action,
        $reason,
        $flow->id
      );

      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : [
          'message' => t('Unexpected error: @message', ['@message' => $e->getMessage()])->render(),
        ], 500
      );
    }

    if (!$status) {
      $this->saveFailedImport(
        $api,
        $entity_type_name,
        $entity_bundle,
        $entity_type_version,
        $entity_uuid,
        ImportIntent::IMPORT_FAILED_HANDLER_DENIED,
        $action,
        $reason,
        $flow->id
      );
    }

    if ($status || $action == SyncIntent::ACTION_UPDATE) {
      $entity = $intent->getEntity();
      if ($entity && $entity->hasLinkTemplate('canonical')) {
        try {
          $url = $entity->toUrl('canonical', ['absolute' => TRUE])
            ->toString(TRUE)
            ->getGeneratedUrl();
          $data['url'] = $url;
        }
        catch (\Exception $e) {
          throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION, $e);
        }
      }

      // If we send data for DELETE requests, the Drupal Serializer will throw
      // a random error. So we just leave the body empty then.
      return new ModifiedResourceResponse($action == SyncIntent::ACTION_DELETE ? NULL : $data);
    }
    else {
      return new ResourceResponse(
        $action == SyncIntent::ACTION_DELETE ? NULL : [
          'message' => t('Entity is not configured to be imported yet.')->render(),
        ], 404
      );
    }
  }

}
