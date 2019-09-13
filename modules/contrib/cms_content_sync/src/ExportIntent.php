<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Event\AfterEntityExport;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionStorage;
use Drupal\cms_content_sync\SyncCore\Storage\InstanceStorage;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Exception\SyncException;

/**
 * Class ExportIntent.
 *
 * @package Drupal\cms_content_sync
 */
class ExportIntent extends SyncIntent {
  /**
   * @var string EXPORT_DISABLED
   *   Disable export completely for this entity type, unless forced.
   *   - used as a configuration option
   *   - not used as $action
   */
  const EXPORT_DISABLED = 'disabled';
  /**
   * @var string EXPORT_AUTOMATICALLY
   *   Automatically export all entities of this entity type.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_AUTOMATICALLY = 'automatically';
  /**
   * @var string EXPORT_MANUALLY
   *   Export only some of these entities, chosen manually.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_MANUALLY = 'manually';
  /**
   * @var string EXPORT_AS_DEPENDENCY
   *   Export only some of these entities, exported if other exported entities
   *   use it.
   *   - used as a configuration option
   *   - used as $action
   */
  const EXPORT_AS_DEPENDENCY = 'dependency';
  /**
   * @var string EXPORT_FORCED
   *   Force the entity to be exported (as long as a handler is also selected).
   *   Can be used programmatically for custom workflows.
   *   - not used as a configuration option
   *   - used as $action
   */
  const EXPORT_FORCED = 'forced';
  /**
   * @var string EXPORT_ANY
   *   Only used as a filter to check if the Flow exports this entity in any
   *   way.
   *   - not used as a configuration option
   *   - not used as $action
   *   - so only used to query against Flows that have *any* export setting for a given entity (type).
   */
  const EXPORT_ANY = 'any';

  /**
   * @var string EXPORT_FAILED_REQUEST_FAILED
   *   The request to the Sync Core failed completely.
   */
  const EXPORT_FAILED_REQUEST_FAILED = 'export_failed_request_failed';
  /**
   * @var string EXPORT_FAILED_REQUEST_INVALID_STATUS_CODE
   *   The Sync Core returned a non-2xx status code.
   */
  const EXPORT_FAILED_REQUEST_INVALID_STATUS_CODE = 'export_failed_invalid_status_code';
  /**
   * @var string EXPORT_FAILED_DEPENDENCY_EXPORT_FAILED
   *   The entity wasn't exported because when exporting a dependency, an error was thrown.
   */
  const EXPORT_FAILED_DEPENDENCY_EXPORT_FAILED = 'export_failed_dependency_export_failed';
  /**
   * @var string EXPORT_FAILED_INTERNAL_ERROR
   *   The entity wasn't exported because when serializing it, an error was thrown.
   */
  const EXPORT_FAILED_INTERNAL_ERROR = 'export_failed_internal_error';
  /**
   * @var string IMPORT_FAILED_HANDLER_DENIED
   *   Soft fail: The export failed because the handler returned FALSE when executing the export.
   */
  const EXPORT_FAILED_HANDLER_DENIED = 'export_failed_handler_denied';
  /**
   * @var string EXPORT_FAILED_UNCHANGED
   *   Soft fail: The entity wasn't exported because it didn't change since the last export.
   */
  const EXPORT_FAILED_UNCHANGED = 'export_failed_unchanged';

  /**
   * ExportIntent constructor.
   *
   * @param \Drupal\cms_content_sync\Entity\Flow $flow
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   * @param $reason
   * @param $action
   * @param \Drupal\Core\Entity\EntityInterface $entity
   */
  public function __construct(Flow $flow, Pool $pool, $reason, $action, EntityInterface $entity) {
    parent::__construct($flow, $pool, $reason, $action, $entity->getEntityTypeId(), $entity->bundle(), $entity->uuid(), $entity instanceof ConfigEntityInterface ? $entity->id() : NULL);

    if (!$this->entity_status->getLastExport()) {
      if (!EntityStatus::getLastImportForEntity($entity) && !ImportIntent::entityHasBeenImportedByRemote($entity->getEntityTypeId(), $entity->uuid())) {
        $this->entity_status->isSourceEntity(TRUE);
      }
    }

    $this->entity = $entity;
  }

  /**
   * Get the correct synchronization for a specific action on a given entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string|string[] $reason
   * @param string $action
   *
   * @return \Drupal\cms_content_sync\Entity\Flow[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getFlowsForEntity(EntityInterface $entity, $reason, $action = SyncIntent::ACTION_CREATE) {
    $flows = Flow::getAll();

    $result = [];

    foreach ($flows as $flow) {
      if ($flow->canExportEntity($entity, $reason, $action)) {
        $result[] = $flow;
      }
    }

    return $result;
  }

  /**
   * Get the correct synchronization for a specific action on a given entity.
   *
   * @param string $entity_type_name
   * @param string|null $bundle_name
   * @param string|string[] $reason
   * @param string $action
   *
   * @return \Drupal\cms_content_sync\Entity\Flow[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getFlowsForEntityType($entity_type_name, $bundle_name, $reason, $action = SyncIntent::ACTION_CREATE) {
    $flows = Flow::getAll();

    $result = [];

    foreach ($flows as $flow) {
      if ($flow->canExportEntityType($entity_type_name, $bundle_name, $reason, $action)) {
        $result[] = $flow;
      }
    }

    return $result;
  }

  /**
   * Serialize the given entity using the entity export and field export
   * handlers.
   *
   * @param array &$result
   *   The data to be provided to Sync Core.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the export could be gotten.
   */
  public function serialize(array &$result) {
    $config = $this->flow->getEntityTypeConfig($this->entityType, $this->bundle);
    $handler = $this->flow->getEntityTypeHandler($config);

    $status = $handler->export($this);

    if (!$status) {
      return FALSE;
    }

    $result = $this->getData();
    return TRUE;
  }

  /**
   * Wrapper for {@see Flow::getExternalConnectionPath}.
   *
   * @param string $shared_entity_id
   * @param bool $as_dependency
   * @param string $version
   *   The version used for the export. For DELETE requests this may be a previous version. All
   *   other requests must use the newest version. If an UPDATE is sent with a new version, it becomes a CREATE instead.
   *
   * @return string
   */
  public function getExternalUrl($shared_entity_id, $as_dependency = FALSE, $version = NULL) {
    $url = $this->pool->getBackendUrl() . '/' . ConnectionStorage::getExternalConnectionPath(
        $this->pool->id,
        $this->pool->getSiteId(),
        $this->entityType,
        $this->bundle
      );

    if ($shared_entity_id) {
      $url .= '/' . $shared_entity_id;
    }

    if ($as_dependency) {
      $url .= '?is_dependency=true';
    }

    return $url;
  }

  /**
   * Save that the import for the given entity failed.
   *
   * @param string $failure_reason
   *   See ExportIntent::EXPORT_FAILURE_*.
   * @param null|string $message
   *   An optional message accompanying this error.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveFailedExport($failure_reason, $message = NULL) {
    $soft_fails = [
      ExportIntent::EXPORT_FAILED_HANDLER_DENIED,
      ExportIntent::EXPORT_FAILED_UNCHANGED,
    ];

    $soft = in_array($failure_reason, $soft_fails);

    $this->entity_status->didExportFail(TRUE, $soft, [
      'error' => $failure_reason,
      'action' => $this->getAction(),
      'reason' => $this->getReason(),
      'message' => $message,
    ]);

    $this->entity_status->save();
  }

  /**
   * Export the given entity.
   *
   * @param bool $return_only
   *
   * @return array|bool TRUE|FALSE if the entity is exported via REST.
   *   NULL|string (serialized entity) if $return_only is set to TRUE.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function execute($return_only = FALSE) {
    $action = $this->getAction();
    $reason = $this->getReason();
    $entity = $this->getEntity();

    /**
     * @var array $deletedTranslations
     *   The translations that have been deleted. Important to notice when
     *   updates must be performed (see ::ACTION_DELETE_TRANSLATION).
     */
    static $deletedTranslations = [];

    if ($action == SyncIntent::ACTION_DELETE_TRANSLATION) {
      $deletedTranslations[$entity->getEntityTypeId()][$entity->uuid()] = TRUE;
      return FALSE;
    }

    if ($entity instanceof TranslatableInterface) {
      $entity = $entity->getUntranslated();
      $this->entity = $entity;
    }
    $export = time();
    if ($entity instanceof EntityChangedInterface) {
      $export = $entity->getChangedTime();
      if ($entity instanceof TranslatableInterface) {
        foreach ($entity->getTranslationLanguages(FALSE) as $language) {
          $translation = $entity->getTranslation($language->getId());
          /**
           * @var \Drupal\Core\Entity\EntityChangedInterface $translation
           */
          if ($translation->getChangedTime() > $export) {
            $export = $translation->getChangedTime();
          }
        }
      }
    }

    // If this very request was sent to delete/create this entity, ignore the
    // export as the result of this request will already tell Sync Core it has
    // been deleted. Otherwise Sync Core will return a reasonable 404 for
    // deletions.
    if (ImportIntent::entityHasBeenImportedByRemote($entity->getEntityTypeId(), $entity->uuid())) {
      self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()] = self::NO_EXPORT_REASON__JUST_IMPORTED;
      return FALSE;
    }

    $entity_type   = $entity->getEntityTypeId();
    $entity_bundle = $entity->bundle();
    $entity_uuid   = $entity->uuid();

    $exported = $this->entity_status->getLastExport();

    if ($exported) {
      if ($action == SyncIntent::ACTION_CREATE) {
        $action = SyncIntent::ACTION_UPDATE;
      }
    }
    else {
      if ($action == SyncIntent::ACTION_UPDATE) {
        $action = SyncIntent::ACTION_CREATE;
      }
      // If the entity was deleted but has never been exported before,
      // exporting the deletion action doesn't make sense as it doesn't even
      // exist remotely.
      elseif ($action == SyncIntent::ACTION_DELETE) {
        self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()] = self::NO_EXPORT_REASON__NEVER_EXPORTED;
        return FALSE;
      }
    }

    $cms_content_sync_disable_optimization = boolval(\Drupal::config('cms_content_sync.debug')
      ->get('cms_content_sync_disable_optimization'));

    if (!self::$exported) {
      self::$exported = [];
    }
    if (isset(self::$exported[$action][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id]) && !$return_only) {
      return self::$exported[$action][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id];
    }
    if ($action == SyncIntent::ACTION_CREATE) {
      if (isset(self::$exported[SyncIntent::ACTION_UPDATE][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id]) && !$return_only) {
        return self::$exported[SyncIntent::ACTION_UPDATE][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id];
      }
    }
    elseif ($action == SyncIntent::ACTION_UPDATE) {
      if (isset(self::$exported[SyncIntent::ACTION_CREATE][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id]) && !$return_only) {
        return self::$exported[SyncIntent::ACTION_CREATE][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id];
      }
    }
    self::$exported[$action][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id] = TRUE;

    // If the entity didn't change, it doesn't have to be re-exported.
    if (!$cms_content_sync_disable_optimization && $this->entity_status->getLastExport() && $this->entity_status->getLastExport() >= $export && $reason != self::EXPORT_FORCED &&
      $action != SyncIntent::ACTION_DELETE &&
      empty($deletedTranslations[$entity->getEntityTypeId()][$entity->uuid()])) {

      // Don't use optimization for taxonomy terms as Drupal doesn't update the
      // changed timestamp on the entity when moving it in the tree for the
      // first time.
      // Menu link translations share their change time stamp with the original
      // entity, so translations would not be exported.
      if ($entity_type != 'taxonomy_term' && $entity_type != 'menu_link_content') {
        self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()] = self::NO_EXPORT_REASON__UNCHANGED;
        return FALSE;
      }
    }

    /** @var \Drupal\Core\Entity\EntityRepositoryInterface $entity_repository */
    $entity_repository = \Drupal::service('entity.repository');

    $proceed = TRUE;
    $body = NULL;

    if ($action != SyncIntent::ACTION_DELETE) {
      $body = [];

      try {
        $proceed = $this->serialize($body);
      }
      catch (\Exception $e) {
        $this->saveFailedExport(ExportIntent::EXPORT_FAILED_INTERNAL_ERROR, $e->getMessage());

        throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
      }

      if ($proceed) {
        $embedded_entities = [];
        if (!empty($body['embed_entities'])) {
          foreach ($body['embed_entities'] as $data) {
            try {
              /**
               * @var \Drupal\Core\Entity\EntityInterface $embed_entity
               */
              $embed_entity = $entity_repository->loadEntityByUuid($data[SyncIntent::ENTITY_TYPE_KEY], $data[SyncIntent::UUID_KEY]);
              $all_pools    = Pool::getAll();
              $pools        = $this->flow->getUsedExportPools($entity, $this->getReason(), $this->getAction(), TRUE);
              $used_pools   = [];

              $flows = Flow::getAll();
              $flows = [$this->flow->id => $this->flow] + $flows;

              $version = Flow::getEntityTypeVersion($embed_entity->getEntityTypeId(), $embed_entity->bundle());

              foreach ($flows as $flow) {
                if (!$flow->canExportEntity($embed_entity, [self::EXPORT_AUTOMATICALLY, self::EXPORT_AS_DEPENDENCY], SyncIntent::ACTION_CREATE)) {
                  continue;
                }

                foreach ($flow->getEntityTypeConfig($embed_entity->getEntityTypeId(), $embed_entity->bundle())['export_pools'] as $pool_id => $behavior) {
                  if (in_array($pool_id, $used_pools)) {
                    continue;
                  }

                  if ($behavior == Pool::POOL_USAGE_FORBID) {
                    continue;
                  }

                  // If this entity was newly created, it won't have any export groups
                  // selected, unless they're FORCED. In this case we add default sync
                  // groups based on the parent entity, as you would expect.
                  if ($data[SyncIntent::AUTO_EXPORT_KEY]) {
                    if (!isset($pools[$pool_id])) {
                      // TODO: Save all parent > child relationships so we can check if this pool is used somewhere else
                      // $pool = $all_pools[$pool_id];
                      // $info = EntityStatus::getInfoForEntity($embed_entity->getEntityTypeId(), $embed_entity->uuid(), $flow, $pool);
                      // if ($info) {
                      //  $info->isExportEnabled(NULL, FALSE);
                      //  $info->save();
                      // }
                      continue;
                    }

                    $pool = $pools[$pool_id];
                    $info = EntityStatus::getInfoForEntity($embed_entity->getEntityTypeId(), $embed_entity->uuid(), $flow, $pool);

                    if (!$info) {
                      $info = EntityStatus::create([
                        'flow' => $flow->id,
                        'pool' => $pool->id,
                        'entity_type' => $embed_entity->getEntityTypeId(),
                        'entity_uuid' => $embed_entity->uuid(),
                        'entity_type_version' => $version,
                        'flags' => 0,
                      ]);
                    }

                    $info->isExportEnabled(NULL, TRUE);
                    $info->save();
                  }
                  else {
                    $pool = $all_pools[$pool_id];
                    if ($behavior == Pool::POOL_USAGE_ALLOW) {
                      $info = EntityStatus::getInfoForEntity($embed_entity->getEntityTypeId(), $embed_entity->uuid(), $flow, $pool);
                      if (!$info || !$info->isExportEnabled()) {
                        continue;
                      }
                    }
                  }

                  ExportIntent::exportEntity($embed_entity, self::EXPORT_AS_DEPENDENCY, SyncIntent::ACTION_CREATE, $flow, $pool);

                  $info = EntityStatus::getInfoForEntity($embed_entity->getEntityTypeId(), $embed_entity->uuid(), $flow, $pool);
                  if (!$info || !$info->getLastExport()) {
                    continue;
                  }

                  $used_pools[] = $pool_id;
                  $definition = $data;
                  $definition[SyncIntent::API_KEY] = $pool->id;
                  $definition[SyncIntent::SOURCE_CONNECTION_ID_KEY] = ConnectionStorage::getExternalConnectionId(
                    $pool->id,
                    $pool->getSiteId(),
                    $embed_entity->getEntityTypeId(),
                    $embed_entity->bundle()
                  );
                  $definition[SyncIntent::POOL_CONNECTION_ID_KEY] = ConnectionStorage::getExternalConnectionId(
                    $pool->id,
                    InstanceStorage::POOL_SITE_ID,
                    $embed_entity->getEntityTypeId(),
                    $embed_entity->bundle()
                  );
                  $embedded_entities[] = $definition;
                }
              }
            }
            catch (\Exception $e) {
              $this->saveFailedExport(ExportIntent::EXPORT_FAILED_DEPENDENCY_EXPORT_FAILED, $e->getMessage());

              throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION, $e);
            }
          }
        }

        $body['embed_entities'] = $embedded_entities;
      }
    }

    \Drupal::logger('cms_content_sync')->info('@not @embed EXPORT @action @entity_type:@bundle @uuid @reason: @message', [
      '@reason' => $reason,
      '@action' => $action,
      '@entity_type'  => $entity_type,
      '@bundle' => $entity_bundle,
      '@uuid' => $entity_uuid,
      '@not' => $proceed ? '' : 'NO',
      '@embed' => $return_only ? 'EMBEDDING' : '',
      '@message' => $proceed ? t('The entity has been exported.') : t('The entity handler denied to export this entity.'),
    ]);

    // Handler chose to deliberately ignore this entity,
    // e.g. a node that wasn't published yet and is not exported unpublished.
    if (!$proceed) {
      self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()] = self::NO_EXPORT_REASON__HANDLER_IGNORES;
      $this->saveFailedExport(ExportIntent::EXPORT_FAILED_HANDLER_DENIED);
      return $return_only ? NULL : FALSE;
    }

    if ($return_only) {
      self::$exported[$action][$entity_type][$entity_bundle][$entity_uuid][$this->pool->id] = $body;
      return $body;
    }

    $version = $this->flow->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle())['version'];

    // If the version changed, UPDATE becomes CREATE instead and DELETE requests must be performed against the old
    // version, as otherwise they would result in a 404 Not Found response.
    if ($version != $this->entity_status->getEntityTypeVersion()) {
      if ($action == SyncIntent::ACTION_UPDATE) {
        $action = SyncIntent::ACTION_CREATE;
      }
      elseif ($action == SyncIntent::ACTION_DELETE) {
        $version = $this->entity_status->getEntityTypeVersion();
      }
    }

    if ($action == SyncIntent::ACTION_CREATE) {
      $shared_entity_id = NULL;
    }
    elseif ($entity instanceof ConfigEntityInterface) {
      $shared_entity_id = $entity->id();
    }
    else {
      $shared_entity_id = $entity->uuid();
    }
    $url = $this->getExternalUrl($shared_entity_id, $this->flow->getEntityTypeConfig($entity_type, $entity_bundle)['export'] == ExportIntent::EXPORT_AS_DEPENDENCY, $version);

    $headers = [
      'Content-Type' => 'application/json',
    ];

    $methods = [
      SyncIntent::ACTION_CREATE => 'post',
      SyncIntent::ACTION_UPDATE => 'put',
      SyncIntent::ACTION_DELETE => 'delete',
    ];

    $client = \Drupal::httpClient();
    try {
      $response = $client->request(
        $methods[$action],
        $url,
        array_merge(
          ['headers' => $headers, 'http_errors' => FALSE],
          $body ? ['body' => json_encode($body)] : [],
          $action == SyncIntent::ACTION_DELETE ? ['timeout' => 15] : []
        )
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('cms_content_sync')->error(
        'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . '@message',
        [
          '@entity_type' => $entity_type,
          '@entity_bundle' => $entity_bundle,
          '@entity_uuid' => $entity_uuid,
          '@message' => $e->getMessage(),
          '@url' => $url,
        ]
      );

      $this->saveFailedExport(ExportIntent::EXPORT_FAILED_REQUEST_FAILED, $e->getMessage());

      throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED, $e);
    }

    if ($response->getStatusCode() != 200 && $response->getStatusCode() != 201) {
      $error = TRUE;

      // The Sync Core doesn't know this entity yet. This may happen when the Sync Core URL has changed
      // or after a reset of the Sync Core database. In this case we simply try a CREATE instead.
      if ($action == SyncIntent::ACTION_UPDATE && $response->getStatusCode() == 404) {
        $action = SyncIntent::ACTION_CREATE;
        $url = $this->getExternalUrl(NULL, $this->flow->getEntityTypeConfig($entity_type, $entity_bundle)['export'] == ExportIntent::EXPORT_AS_DEPENDENCY, $version);

        try {
          $response = $client->request(
            $methods[$action],
            $url,
            array_merge(
              ['headers' => $headers, 'http_errors' => FALSE],
              ['body' => json_encode($body)]
            )
          );

          $error = $response->getStatusCode() != 200 && $response->getStatusCode() != 201;
        }
        catch (\Exception $e) {
          \Drupal::logger('cms_content_sync')->error(
            'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . '@message',
            [
              '@entity_type' => $entity_type,
              '@entity_bundle' => $entity_bundle,
              '@entity_uuid' => $entity_uuid,
              '@message' => $e->getMessage(),
              '@url' => $url,
            ]
          );

          $this->saveFailedExport(ExportIntent::EXPORT_FAILED_REQUEST_FAILED, $e->getMessage());

          throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED, $e);
        }
      }

      if ($error) {
        \Drupal::logger('cms_content_sync')->error(
          'Failed to export entity @entity_type-@entity_bundle @entity_uuid to @url' . PHP_EOL . 'Got status code @status_code @reason_phrase with body:' . PHP_EOL . '@message',
          [
            '@entity_type' => $entity_type,
            '@entity_bundle' => $entity_bundle,
            '@entity_uuid' => $entity_uuid,
            '@status_code' => $response->getStatusCode(),
            '@reason_phrase' => $response->getReasonPhrase(),
            '@body' => $response->getBody() . '',
            '@url' => $url,
          ]
        );

        $this->saveFailedExport(ExportIntent::EXPORT_FAILED_REQUEST_INVALID_STATUS_CODE, $response->getBody());

        throw new SyncException(SyncException::CODE_EXPORT_REQUEST_FAILED);
      }
    }

    if (!$this->entity_status->getLastExport() && !$this->entity_status->getLastImport() && isset($body['url'])) {
      $this->entity_status->set('source_url', $body['url']);
    }
    $this->entity_status->setLastExport($export);

    if ($action == SyncIntent::ACTION_DELETE) {
      $this->entity_status->isDeleted(TRUE);
      $this->pool->markDeleted($entity_type, $entity_uuid);
    }

    if ($version != $this->entity_status->getEntityTypeVersion()) {
      $this->entity_status->setEntityTypeVersion($version);
    }

    $this->entity_status->save();

    // Dispatch EntityExport event to give other modules the possibility to
    // react on it.
    \Drupal::service('event_dispatcher')->dispatch(AfterEntityExport::EVENT_NAME, new AfterEntityExport($entity, $this->pool, $this->flow, $this->reason, $this->action));

    return TRUE;
  }

  /**
   * @var array
   *   A list of all exported entities to make sure entities aren't exported
   *   multiple times during the same request in the format
   *   [$action][$entity_type][$bundle][$uuid] => TRUE
   */
  static protected $exported;

  /**
   * Check whether the given entity is currently being exported. Useful to check
   * against hierarchical references as for nodes and menu items for example.
   *
   * @param string $entity_type
   *   The entity type to check for.
   * @param string $uuid
   *   The UUID of the entity in question.
   * @param string $pool
   *   The pool to export to.
   * @param null|string $action
   *   See ::ACTION_*.
   *
   * @return bool
   */
  public static function isExporting($entity_type, $uuid, $pool, $action = NULL) {
    foreach (self::$exported as $do => $types) {
      if ($action ? $do != $action : $do == SyncIntent::ACTION_DELETE) {
        continue;
      }
      if (!isset($types[$entity_type])) {
        continue;
      }
      foreach ($types[$entity_type] as $bundle => $entities) {
        if (!empty($entities[$uuid][$pool])) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * Helper function to export an entity and throw errors if anything fails.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see ::ACTION_*}.
   * @param \Drupal\cms_content_sync\Entity\Flow $flow
   *   The flow to be used. If none is given, all flows that may export this
   *   entity will be asked to do so for all relevant pools.
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *   The pool to be used. If not set, all relevant pools for the flow will be
   *   used one after another.
   *
   * @param bool $return_only
   *
   * @return bool|string Whether the entity is configured to be exported or not.
   *   if $return_only is given, this will return the serialized entity to embed
   *   or NULL.
   *
   * @throws SyncException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function exportEntity(EntityInterface $entity, $reason, $action, Flow $flow = NULL, Pool $pool = NULL, $return_only = FALSE) {
    if (!$flow) {
      $flows = self::getFlowsForEntity($entity, $reason, $action);
      if (!count($flows)) {
        return FALSE;
      }

      $result = FALSE;
      foreach ($flows as $flow) {
        if ($return_only) {
          $result = self::exportEntity($entity, $reason, $action, $flow, NULL, TRUE);
          if ($result) {
            return $result;
          }
        }
        else {
          $result |= self::exportEntity($entity, $reason, $action, $flow);
        }
      }
      return $result;
    }

    if (!$pool) {
      $pools = $flow->getUsedExportPools($entity, $reason, $action, TRUE);
      $result = FALSE;
      foreach ($pools as $pool) {
        $infos = EntityStatus::getInfosForEntity($entity->getEntityTypeId(), $entity->uuid(), ['pool' => $pool->label()]);
        $cancel = FALSE;
        foreach ($infos as $info) {
          if (!$info->getFlow()) {
            continue;
          }

          $config = $info->getFlow()->getEntityTypeConfig($entity->getEntityTypeId(), $entity->bundle())['import_updates'];

          if ($info->getLastImport() && in_array($config, [ImportIntent::IMPORT_UPDATE_FORCE_AND_FORBID_EDITING, ImportIntent::IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN])) {
            $cancel = TRUE;
            break;
          }
        }

        if ($cancel) {
          continue;
        }

        if ($return_only) {
          $result = self::exportEntity($entity, $reason, $action, $flow, $pool, TRUE);
          if ($result) {
            return $result;
          }
        }
        else {
          $result |= self::exportEntity($entity, $reason, $action, $flow, $pool);
        }
      }
      return $result;
    }

    $intent = new ExportIntent($flow, $pool, $reason, $action, $entity);
    return $intent->execute($return_only);
  }

  /**
   * @var string NO_EXPORT_REASON__JUST_IMPORTED The entity has been imported
   *   during this very request, so it can't be re-exported immediately.
   */
  const NO_EXPORT_REASON__JUST_IMPORTED = 'JUST_IMPORTED';

  /**
   * @var string NO_EXPORT_REASON__NEVER_EXPORTED The entity has never been
   *   exported before, so exporting the deletion doesn't make sense (it will
   *   not even exist remotely yet).
   */
  const NO_EXPORT_REASON__NEVER_EXPORTED = 'NEVER_EXPORTED';

  /**
   * @var string NO_EXPORT_REASON__UNCHANGED The entity hasn't changed, so the
   *   export would not do anything.
   */
  const NO_EXPORT_REASON__UNCHANGED = 'UNCHANGED';

  /**
   * @var string NO_EXPORT_REASON__HANDLER_IGNORES The handler for the entity
   *   refused to export this entity. These are usually handler specific
   *   configurations like "Don't export unpublished content" for nodes.
   */
  const NO_EXPORT_REASON__HANDLER_IGNORES = 'HANDLER_IGNORES';

  /**
   * @var string NO_EXPORT_REASON__NO_POOL No pool was assigned, so there's no export to take place.
   */
  const NO_EXPORT_REASON__NO_POOL = 'NO_POOL';

  /**
   * @var array
   *   exported. Can be queried via self::getNoExportReason($entity). Structure:
   *   [ entity_type_id:string ][ entity_uuid:string ] => string|Exception
   */
  protected static $noExportReasons = [];

  /**
   * Get the reason why an export has not happened.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param bool $as_message
   *
   * @return string|\Exception|null See self::$noExportReasons.
   */
  public static function getNoExportReason($entity, $as_message = FALSE) {
    // If export wasn't even tried, no pool has been assigned.
    if (empty(self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()])) {
      $issue = self::NO_EXPORT_REASON__NO_POOL;
    }
    else {
      $issue = self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()];
    }

    if ($as_message) {
      return self::displayNoExportReason(
        $issue
      );
    }

    return $issue;
  }

  /**
   * Get a user message on why the export failed.
   *
   * @param string|\Exception $reason
   *   The reason from self::getNoExportReason().
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup|string
   */
  public static function displayNoExportReason($reason) {
    if ($reason instanceof \Exception) {
      return $reason->getMessage();
    }

    switch ($reason) {
      case self::NO_EXPORT_REASON__HANDLER_IGNORES:
        return \t('The configuration forbids the export.');

      case self::NO_EXPORT_REASON__JUST_IMPORTED:
        return \t('The entity has just been imported and cannot be exported immediately with the same request.');

      case self::NO_EXPORT_REASON__NEVER_EXPORTED:
        return \t('The entity has not been exported before, so exporting the deletion doesn\'t have any effect.');

      case self::NO_EXPORT_REASON__UNCHANGED:
        return \t('The entity has not changed since it\'s last export.');

      default:
        return \t('The entity doesn\'t have any Pool assigned.');
    }

    return NULL;
  }

  /**
   * Helper function to export an entity and display the user the results. If
   * you want to make changes programmatically, use ::exportEntity() instead.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to export.
   * @param string $reason
   *   {@see Flow::EXPORT_*}.
   * @param string $action
   *   {@see ::ACTION_*}.
   * @param \Drupal\cms_content_sync\Entity\Flow $flow
   *   The flow to be used. If none is given, all flows that may export this
   *   entity will be asked to do so for all relevant pools.
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *   The pool to be used. If not set, all relevant pools for the flow will be
   *   used one after another.
   *
   * @return bool Whether the entity is configured to be exported or not.
   */
  public static function exportEntityFromUi(EntityInterface $entity, $reason, $action, Flow $flow = NULL, Pool $pool = NULL) {
    $messenger = \Drupal::messenger();
    try {
      $status = self::exportEntity($entity, $reason, $action, $flow, $pool);

      if ($status) {
        if ($action == SyncIntent::ACTION_DELETE) {
          $messenger->addMessage(t('%label has been exported with CMS Content Sync.', ['%label' => $entity->getEntityTypeId()]));
        }
        else {
          $messenger->addMessage(t('%label has been exported with CMS Content Sync.', ['%label' => $entity->label()]));
        }
        return TRUE;
      }

      return FALSE;
    }
    catch (SyncException $e) {
      $message = $e->parentException ? $e->parentException->getMessage() : (
        $e->errorCode == $e->getMessage() ? '' : $e->getMessage()
      );
      if ($message) {
        $messenger->addWarning(t('Failed to export %label with CMS Content Sync (%code). Message: %message', [
          '%label' => $entity->label(),
          '%code' => $e->errorCode,
          '%message' => $message,
        ]));
      }
      else {
        $messenger->addWarning(t('Failed to export %label with CMS Content Sync (%code).', [
          '%label' => $entity->label(),
          '%code' => $e->errorCode,
        ]));
      }
      self::$noExportReasons[$entity->getEntityTypeId()][$entity->uuid()] = $e;
      return TRUE;
    }
  }

}
