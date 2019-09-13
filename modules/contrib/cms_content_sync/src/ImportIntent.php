<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Event\AfterEntityImport;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;

/**
 *
 */
class ImportIntent extends SyncIntent {
  /**
   * @var string IMPORT_DISABLED
   *   Disable import completely for this entity type, unless forced.
   *   - used as a configuration option
   *   - not used as $action
   */
  const IMPORT_DISABLED = 'disabled';
  /**
   * @var string IMPORT_AUTOMATICALLY
   *   Automatically import all entities of this entity type.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_AUTOMATICALLY = 'automatically';
  /**
   * @var string IMPORT_MANUALLY
   *   Import only some of these entities, chosen manually.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_MANUALLY = 'manually';
  /**
   * @var string IMPORT_AS_DEPENDENCY
   *   Import only some of these entities, imported if other imported entities
   *   use it.
   *   - used as a configuration option
   *   - used as $action
   */
  const IMPORT_AS_DEPENDENCY = 'dependency';
  /**
   * @var string IMPORT_FORCED
   *   Force the entity to be imported (as long as a handler is also selected).
   *   Can be used programmatically for custom workflows.
   *   - not used as a configuration option
   *   - used as $action
   */
  const IMPORT_FORCED = 'forced';


  /**
   * @var string IMPORT_UPDATE_IGNORE
   *   Ignore all incoming updates.
   */
  const IMPORT_UPDATE_IGNORE = 'ignore';
  /**
   * @var string IMPORT_UPDATE_FORCE
   *   Overwrite any local changes on all updates.
   */
  const IMPORT_UPDATE_FORCE = 'force';
  /**
   * @var string IMPORT_UPDATE_FORCE_AND_FORBID_EDITING
   *   Import all changes and forbid local editors to change the content.
   */
  const IMPORT_UPDATE_FORCE_AND_FORBID_EDITING = 'force_and_forbid_editing';
  /**
   * @var string IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN
   *   Import all changes and forbid local editors to change the content unless
   *   they check the "override" checkbox. As long as that is checked, we
   *   ignore any incoming updates in favor of the local changes.
   */
  const IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN = 'allow_override';


  /**
   * @var string IMPORT_FAILED_DIFFERENT_VERSION
   *   The remote entity type version is different to the local entity type version.
   */
  const IMPORT_FAILED_DIFFERENT_VERSION = 'import_failed_different_version';
  /**
   * @var string IMPORT_FAILED_INTERNAL_ERROR
   *   An internal Content Sync error occurred when trying to import the entity.
   */
  const IMPORT_FAILED_CONTENT_SYNC_ERROR = 'import_failed_content_sync_error';
  /**
   * @var string IMPORT_FAILED_INTERNAL_ERROR
   *   An unexpected error occurred when trying to import the entity.
   */
  const IMPORT_FAILED_INTERNAL_ERROR = 'import_failed_internal_error';
  /**
   * @var string IMPORT_FAILED_UNKNOWN_POOL
   *   Soft: The provided Pool doesn't exist.
   */
  const IMPORT_FAILED_UNKNOWN_POOL = 'import_failed_unknown_pool';
  /**
   * @var string IMPORT_FAILED_NO_FLOW
   *   Soft: No Flow is configured to import this entity.
   */
  const IMPORT_FAILED_NO_FLOW = 'import_failed_no_flow';
  /**
   * @var stringIMPORT_FAILED_HANDLER_DENIED
   *   Soft: The import failed because the handler returned FALSE when executing the import.
   */
  const IMPORT_FAILED_HANDLER_DENIED = 'import_failed_handler_denied';


  protected $mergeChanges;

  /**
   * SyncIntent constructor.
   *
   * @param \Drupal\cms_content_sync\Entity\Flow $flow
   *   {@see SyncIntent::$sync}.
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *   {@see SyncIntent::$pool}.
   * @param string $reason
   *   {@see Flow::EXPORT_*} or {@see Flow::IMPORT_*}.
   * @param string $action
   *   {@see ::ACTION_*}.
   * @param string $entity_type
   *   {@see SyncIntent::$entityType}.
   * @param string $bundle
   *   {@see SyncIntent::$bundle}.
   * @param array $data
   *   The data provided from Sync Core for imports.
   *   Format is the same as in ::getData()
   */
  public function __construct(Flow $flow, Pool $pool, $reason, $action, $entity_type, $bundle, $data) {
    if (EntityHandlerPluginManager::isEntityTypeConfiguration($entity_type)) {
      $entity_id = $data['id'];
    }
    else {
      $entity_id = NULL;
    }

    parent::__construct($flow, $pool, $reason, $action, $entity_type, $bundle, $data['uuid'], $entity_id, isset($data['url']) ? $data['url'] : '');

    if (!empty($data['url'])) {
      $this->entity_status->set('source_url', $data['url']);
    }

    if (!empty($data['embed_entities'])) {
      $this->embedEntities = $data['embed_entities'];
    }
    if (!empty($data['apiu_translation'])) {
      $this->translationFieldValues = $data['apiu_translation'];
    }
    if (!empty($data)) {
      $this->fieldValues = array_diff_key(
        $data,
        [
          'embed_entities' => [],
          'apiu_translation' => [],
          'uuid' => NULL,
          'id' => NULL,
          'bundle' => NULL,
        ]
      );
    }

    $this->mergeChanges = $this->flow->getEntityTypeConfig($this->entityType, $this->bundle)['import_updates'] == ImportIntent::IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN &&
      $this->entity_status->isOverriddenLocally();
  }

  /**
   * Mark the given dependency as missing so it's automatically resolved whenever it gets imported.
   *
   * @param array $definition
   * @param null $field
   */
  public function saveUnresolvedDependency($definition, $field = NULL, $data = NULL) {
    // User references are ignored.
    if (empty($definition[SyncIntent::ENTITY_TYPE_KEY]) || (empty($definition[SyncIntent::ID_KEY]) && empty($definition[SyncIntent::UUID_KEY]))) {
      return;
    }

    MissingDependencyManager::saveUnresolvedDependency(
      $definition[SyncIntent::ENTITY_TYPE_KEY],
      empty($definition[SyncIntent::ID_KEY]) ? $definition[SyncIntent::UUID_KEY] : $definition[SyncIntent::ID_KEY],
      $this->getEntity(),
      $this->getReason(),
      $field,
      $data
    );
  }

  /**
   * Resolve all references to the entity that has just been imported if they're missing at other content.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function resolveMissingDependencies() {
    MissingDependencyManager::resolveDependencies($this->getEntity());
  }

  /**
   * @return bool
   */
  public function shouldMergeChanges() {
    return $this->mergeChanges;
  }

  /**
   * Import the provided entity.
   *
   * @return bool
   *
   * @throws Exception\SyncException
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function execute() {
    $import = $this->pool->getNewestTimestamp($this->entityType, $this->uuid, TRUE);
    if (!$import) {
      if ($this->action == SyncIntent::ACTION_UPDATE) {
        $this->action = SyncIntent::ACTION_CREATE;
      }
    }
    elseif ($this->action == SyncIntent::ACTION_CREATE) {
      $this->action = SyncIntent::ACTION_UPDATE;
    }
    $import = time();

    $config = $this->flow->getEntityTypeConfig($this->entityType, $this->bundle);
    $handler = $this->flow->getEntityTypeHandler($config);

    self::entityHasBeenImportedByRemote($this->entityType, $this->uuid, TRUE);

    $result = $handler->import($this);

    \Drupal::logger('cms_content_sync')->info('@not IMPORT @action @entity_type:@bundle @uuid @reason: @message', [
      '@reason' => $this->reason,
      '@action' => $this->action,
      '@entity_type'  => $this->entityType,
      '@bundle' => $this->bundle,
      '@uuid' => $this->uuid,
      '@not' => $result ? '' : 'NO',
      '@message' => $result ? t('The entity has been imported.') : t('The entity handler denied to import this entity.'),
    ]);

    // Don't save entity_status entity if entity wasn't imported anyway.
    if (!$result) {
      return FALSE;
    }

    // Need to save after setting timestamp to prevent exception.
    $this->entity_status->setLastImport($import);
    $this->pool->setTimestamp($this->entityType, $this->uuid, $import, TRUE);
    $this->entity_status->save();

    if ($this->action == SyncIntent::ACTION_DELETE) {
      $this->pool->markDeleted($this->entityType, $this->uuid);
    }

    $entity = $this->getEntity();

    // Dispatch EntityExport event to give other modules the possibility to
    // react on it.
    \Drupal::service('event_dispatcher')->dispatch(AfterEntityImport::EVENT_NAME, new AfterEntityImport($entity, $this->pool, $this->flow, $this->reason, $this->action));

    $this->resolveMissingDependencies();

    return TRUE;
  }

  /**
   * Check if the provided entity has just been imported by Sync Core in this
   * very request. In this case it doesn't make sense to perform a remote
   * request telling Sync Core it has been created/updated/deleted
   * (it will know as a result of this current request).
   *
   * @param string $entity_type
   *   The entity type.
   * @param string $entity_uuid
   *   The entity UUID.
   * @param bool $set
   *   If TRUE, this entity will be set to have been imported at this request.
   *
   * @return bool
   */
  public static function entityHasBeenImportedByRemote($entity_type = NULL, $entity_uuid = NULL, $set = FALSE) {
    static $entities = [];

    if (!$entity_type) {
      return !empty($entities);
    }

    if ($set) {
      return $entities[$entity_type][$entity_uuid] = TRUE;
    }

    return !empty($entities[$entity_type][$entity_uuid]);
  }

}
