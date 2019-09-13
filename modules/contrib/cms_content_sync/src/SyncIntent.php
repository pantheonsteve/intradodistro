<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler\DefaultTaxonomyHandler;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionStorage;
use Drupal\cms_content_sync\SyncCore\Storage\InstanceStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Exception\SyncException;

/**
 * Class SyncIntent.
 *
 * For every import and export of every entity, an instance of this class is
 * created and passed through the entity and field handlers. When exporting,
 * you can set field values and embed entities. When exporting, you can
 * receive these values back and resolve the entity references you saved.
 *
 * The same class is used for export and import to allow adjusting values
 * with hook integration.
 */
abstract class SyncIntent {
  /**
   * @var \Drupal\cms_content_sync\Entity\Flow
   *   The synchronization this request spawned at.
   * @var string            $entityType             Entity type of the processed entity.
   * @var string            $bundle                 Bundle of the processed entity.
   * @var string            $uuid                   UUID of the processed entity.
   * @var array             $fieldValues            The field values for the untranslated entity.
   * @var array             $embedEntities          The entities that should be processed along with this entity. Each entry is an array consisting of all SyncIntent::_*KEY entries.
   * @var string            $activeLanguage         The currently active language.
   * @var array             $translationFieldValues The field values for the translation of the entity per language as key.
   */
  protected $flow,
    $entityType,
    $bundle,
    $uuid,
    $id,
    $fieldValues,
    $embedEntities,
    $activeLanguage,
    $translationFieldValues;

  /**
   * @var \Drupal\cms_content_sync\Entity\EntityStatus
   */
  protected $entity_status;
  protected $pool,
    $reason,
    $action,
    $entity;

  /**
   * Keys used in the definition array for embedded entities.
   *
   * @see SyncIntent::embedEntity        for its usage on export.
   * @see SyncIntent::loadEmbeddedEntity for its usage on import.
   *
   * @var string API_KEY                  The API of the processed and referenced entity.
   * @var string ENTITY_TYPE_KEY          The entity type of the referenced entity.
   * @var string BUNDLE_KEY               The bundle of the referenced entity.
   * @var string VERSION_KEY              The version of the entity type of the referenced entity.
   * @var string UUID_KEY                 The UUID of the referenced entity.
   * @var string AUTO_EXPORT_KEY          Whether or not to automatically export the referenced entity as well.
   * @var string SOURCE_CONNECTION_ID_KEY The Sync Core connection ID of the referenced entity.
   * @var string POOL_CONNECTION_ID_KEY   The Sync Core connection ID of the pool for this api + entity type + bundle.
   */
  const API_KEY                  = 'api';
  const ENTITY_TYPE_KEY          = 'type';
  const BUNDLE_KEY               = 'bundle';
  const VERSION_KEY              = 'version';
  const UUID_KEY                 = 'uuid';
  const ID_KEY                   = 'id';
  const AUTO_EXPORT_KEY          = 'auto_export';
  const SOURCE_CONNECTION_ID_KEY = 'connection_id';
  const POOL_CONNECTION_ID_KEY   = 'next_connection_id';
  const ENTITY_EMBED_KEY         = 'entity';
  const LABEL_KEY                = 'label';

  /**
   * @var string ACTION_CREATE
   *   export/import the creation of this entity.
   */
  const ACTION_CREATE = 'create';
  /**
   * @var string ACTION_UPDATE
   *   export/import the update of this entity.
   */
  const ACTION_UPDATE = 'update';
  /**
   * @var string ACTION_DELETE
   *   export/import the deletion of this entity.
   */
  const ACTION_DELETE = 'delete';
  /**
   * @var string ACTION_DELETE_TRANSLATION
   *   Drupal doesn't update the ->getTranslationStatus($langcode) to
   *   TRANSLATION_REMOVED before calling hook_entity_translation_delete, so we
   *   need to use a custom action to circumvent deletions of translations of
   *   entities not being handled. This is only used for calling the
   *   ->exportEntity function. It will then be replaced by a simple
   *   ::ACTION_UPDATE.
   */
  const ACTION_DELETE_TRANSLATION = 'delete translation';

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
   * @param string $uuid
   *   {@see SyncIntent::$uuid}.
   * @param string $source_url
   *   The source URL if imported or NULL if exported from this site.
   */
  public function __construct(Flow $flow, Pool $pool, $reason, $action, $entity_type, $bundle, $uuid, $id = NULL, $source_url = NULL) {
    $this->flow          = $flow;
    $this->pool          = $pool;
    $this->reason        = $reason;
    $this->action        = $action;
    $this->entityType    = $entity_type;
    $this->bundle        = $bundle;
    $this->uuid          = $uuid;
    $this->id            = $id;
    $this->entity_status = EntityStatus::getInfoForEntity($entity_type, $uuid, $flow, $pool);

    if (!$this->entity_status) {
      $this->entity_status = EntityStatus::create([
        'flow' => $this->flow->id,
        'pool' => $this->pool->id,
        'entity_type' => $entity_type,
        'entity_uuid' => $uuid,
        'entity_type_version' => Flow::getEntityTypeVersion($entity_type, $bundle),
        'flags' => 0,
        'source_url' => $source_url,
      ]);
    }

    $this->embedEntities          = [];
    $this->activeLanguage         = NULL;
    $this->translationFieldValues = NULL;
    $this->fieldValues            = [];
  }

  /**
   * Execute the intent.
   *
   * @return bool
   */
  abstract public function execute();

  /**
   * @return string
   */
  public function getReason() {
    return $this->reason;
  }

  /**
   * @return string
   */
  public function getAction() {
    return $this->action;
  }

  /**
   * @return \Drupal\cms_content_sync\Entity\Flow
   */
  public function getFlow() {
    return $this->flow;
  }

  /**
   * @return \Drupal\cms_content_sync\Entity\Pool
   */
  public function getPool() {
    return $this->pool;
  }

  /**
   * @return \Drupal\Core\Entity\EntityInterface
   *   The entity of the intent, if it already exists locally.
   */
  public function getEntity() {
    if (!$this->entity) {
      if ($this->id) {
        $entity = \Drupal::entityTypeManager()
          ->getStorage($this->entityType)
          ->load($this->id);
      }
      else {
        $entity = \Drupal::service('entity.repository')
          ->loadEntityByUuid($this->entityType, $this->uuid);
      }

      if ($entity) {
        $this->setEntity($entity);
      }
    }
    return $this->entity;
  }

  /**
   * Returns the entity status.
   */
  public function getEntityStatus() {
    return $this->entity_status;
  }

  /**
   * Set the entity when importing (may not be saved yet then).
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity you just created.
   *
   * @return $this|\Drupal\Core\Entity\EntityInterface|\Drupal\Core\Entity\TranslatableInterface
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function setEntity(EntityInterface $entity) {
    if ($entity == $this->entity) {
      return $this->entity;
    }
    if ($this->entity) {
      throw new SyncException(SyncException::CODE_INTERNAL_ERROR, NULL, "Attempting to re-set existing entity.");
    }
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     * @var \Drupal\Core\Entity\TranslatableInterface $entity
     */
    $this->entity = $entity;
    if ($this->entity) {
      if ($this->activeLanguage) {
        $this->entity = $this->entity->getTranslation($this->activeLanguage);
      }
    }
    return $this->entity;
  }

  /**
   * Retrieve a value you stored before via ::setentity_statusData().
   *
   * @see EntityStatus::getData()
   *
   * @param string|string[] $key
   *   The key to retrieve.
   *
   * @return mixed Whatever you previously stored here.
   */
  public function getStatusData($key) {
    return $this->entity_status ? $this->entity_status->getData($key) : NULL;
  }

  /**
   * Store a key=>value pair for later retrieval.
   *
   * @see EntityStatus::setData()
   *
   * @param string|string[] $key
   *   The key to store the data against. Especially
   *   field handlers should use nested keys like ['field','[name]','[key]'].
   * @param mixed $value
   *   Whatever simple value you'd like to store.
   *
   * @return bool
   */
  public function setStatusData($key, $value) {
    if (!$this->entity_status) {
      return FALSE;
    }
    $this->entity_status->setData($key, $value);
    return TRUE;
  }

  /**
   * Get all languages for field translations that are currently used.
   */
  public function getTranslationLanguages() {
    return empty($this->translationFieldValues) ? [] : array_keys($this->translationFieldValues);
  }

  /**
   * Change the language used for provided field values. If you want to add a
   * translation of an entity, the same SyncIntent is used. First, you
   * add your fields using self::setField() for the untranslated version.
   * After that you call self::changeTranslationLanguage() with the language
   * identifier for the translation in question. Then you perform all the
   * self::setField() updates for that language and eventually return to the
   * untranslated entity by using self::changeTranslationLanguage() without
   * arguments.
   *
   * @param string $language
   *   The identifier of the language to switch to or NULL to reset.
   */
  public function changeTranslationLanguage($language = NULL) {
    $this->activeLanguage = $language;
    if ($this->entity) {
      if ($language) {
        $this->entity = $this->entity->getTranslation($language);
      }
      else {
        $this->entity = $this->entity->getUntranslated();
      }
    }
  }

  /**
   * Return the language that's currently used.
   *
   * @see SyncIntent::changeTranslationLanguage() for a detailed explanation.
   */
  public function getActiveLanguage() {
    return $this->activeLanguage;
  }

  /**
   * Get the definition for a referenced entity that should be exported /
   * embedded as well.
   *
   * @see SyncIntent::$embedEntities
   *
   * @param string $entity_type
   *   The entity type of the referenced entity.
   * @param string $bundle
   *   The bundle of the referenced entity.
   * @param string $uuid
   *   The UUID of the referenced entity.
   * @param string $id
   *   The ID of the entity, if it should be kept across sites.
   * @param int $auto_export
   *   Whether the referenced entity should be exported automatically to all
   *   it's pools as well.
   * @param array $details
   *   Additional details you would like to export.
   *
   * @return array The definition to be exported.
   */
  public function getEmbedEntityDefinition($entity_type, $bundle, $uuid, $id = NULL, $auto_export = self::ENTITY_REFERENCE_RESOLVE_IF_EXISTS, $details = NULL) {
    $version = Flow::getEntityTypeVersion($entity_type, $bundle);

    return array_merge([
      self::API_KEY           => $this->pool->id,
      self::ENTITY_TYPE_KEY   => $entity_type,
      self::UUID_KEY          => $uuid,
      self::ID_KEY            => $id,
      self::BUNDLE_KEY        => $bundle,
      self::VERSION_KEY       => $version,
      self::AUTO_EXPORT_KEY   => $auto_export,
      self::SOURCE_CONNECTION_ID_KEY => ConnectionStorage::getExternalConnectionId(
        $this->pool->id,
        $this->pool->getSiteId(),
        $entity_type,
        $bundle
      ),
      self::POOL_CONNECTION_ID_KEY => ConnectionStorage::getExternalConnectionId(
        $this->pool->id,
        InstanceStorage::POOL_SITE_ID,
        $entity_type,
        $bundle
      ),
    ], $details ? $details : []);
  }

  /**
   * Embed an entity by its properties.
   *
   * @see SyncIntent::getEmbedEntityDefinition
   * @see SyncIntent::embedEntity
   *
   * @param string $entity_type
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param string $bundle
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param string $uuid
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param string $id
   *   The ID of the entity, if it should be kept across sites.
   * @param int $auto_export
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param array $details
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array The definition you can store via <a href='psi_element://SyncIntent::setField'>SyncIntent::setField</a> and on the
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function embedEntityDefinition($entity_type, $bundle, $uuid, $id = NULL, $auto_export = self::ENTITY_REFERENCE_RESOLVE_IF_EXISTS, $details = NULL, $entity = NULL) {
    // Prevent circle references without middle man.
    if ($entity_type == $this->entityType && $uuid == $this->uuid) {
      throw new SyncException(
        SyncException::CODE_INTERNAL_ERROR,
        NULL,
        "Can't circle-reference own entity (" . $entity_type . " " . $uuid . ")."
      );
    }

    if ($auto_export === self::ENTITY_REFERENCE_EMBED) {
      $data = $this->getEmbedEntityDefinition($entity_type, $bundle, $uuid, $id, $auto_export, $details);

      if ($entity) {
        $embed_entity = $entity;
      }
      elseif ($id) {
        $embed_entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($id);
      }
      else {
        $embed_entity = \Drupal::service('entity.repository')->loadEntityByUuid($entity_type, $uuid);
      }

      // If this is nested, we already get the serialized entity from the child.
      if (!isset($data[self::ENTITY_EMBED_KEY])) {
        $data[self::ENTITY_EMBED_KEY] = ExportIntent::exportEntity(
          $embed_entity, ExportIntent::EXPORT_AS_DEPENDENCY, SyncIntent::ACTION_CREATE, NULL, NULL, TRUE
        );
      }

      // Add all dependencies from the child directly to us, otherwise they'll
      // be missing on the remote site.
      foreach ($data[self::ENTITY_EMBED_KEY]['embed_entities'] as $embed) {
        $this->embedEntityDefinition(
          $embed[self::ENTITY_TYPE_KEY],
          $embed[self::BUNDLE_KEY],
          $embed[self::UUID_KEY],
          $embed[self::ID_KEY],
          $embed[self::AUTO_EXPORT_KEY],
          $embed
        );
      }

      $data[self::ENTITY_EMBED_KEY]['embed_entities'] = [];

      return $data;
    }

    // Already included? Just return the definition then.
    foreach ($this->embedEntities as &$definition) {
      if ($definition[self::ENTITY_TYPE_KEY] == $entity_type && $definition[self::UUID_KEY] == $uuid && $definition[self::ID_KEY] == $id) {
        // Overwrite auto export flag if it should be set now.
        if (!$definition[self::AUTO_EXPORT_KEY] && $auto_export) {
          $definition[self::AUTO_EXPORT_KEY] = $auto_export;
        }
        return $this->getEmbedEntityDefinition(
          $entity_type, $bundle, $uuid, $id, $auto_export, $details
        );
      }
    }

    $result = $this->getEmbedEntityDefinition(
      $entity_type, $bundle, $uuid, $id, $auto_export, $details
    );

    $embed = $auto_export;

    // Check if the Pool has been selected manually. In this case, we need to embed the entity despite the AUTO EXPORT not being set.
    if (!$embed) {
      $statuses = EntityStatus::getInfosForEntity($entity_type, $uuid, ['flow' => $this->flow->id()]);
      foreach ($statuses as $status) {
        if ($status->isExportEnabled()) {
          $embed = TRUE;
          break;
        }
      }
    }

    if ($embed) {
      $this->embedEntities[] = $result;
    }

    return $result;
  }

  /**
   * @var int ENTITY_REFERENCE_RESOLVE_IF_EXISTS
   *   Don't export the referenced entity automatically, but if the entity
   *   exists on the remote site, resolve the reference on the field when
   *   importing this entity.
   */
  const ENTITY_REFERENCE_RESOLVE_IF_EXISTS = 0;

  /**
   * @var int ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY
   *   Export the referenced entity in parallel to the current entity. The Sync
   *   Core will then import the referenced entity before this entity to make
   *   sure the reference can be resolved when this field is imported.
   */
  const ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY = 1;

  /**
   * @var int ENTITY_REFERENCE_EMBED
   *   Embed the full entity within this field, so no other request is required.
   *   The remote site will then use the definition to import the entity when
   *   the field is imported.
   */
  const ENTITY_REFERENCE_EMBED = 2;

  /**
   * Export the provided entity along with the processed entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The referenced entity to export as well.
   * @param int $auto_export
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   * @param array $details
   *   {@see SyncIntent::getEmbedEntityDefinition}.
   *
   * @return array The definition you can store via {@see SyncIntent::setField} and on the other end receive via {@see SyncIntent::getField}.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function embedEntity($entity, $auto_export = self::ENTITY_REFERENCE_RESOLVE_IF_EXISTS, $details = NULL) {
    return $this->embedEntityDefinition(
      $entity->getEntityTypeId(),
      $entity->bundle(),
      $entity->uuid(),
      EntityHandlerPluginManager::isEntityTypeConfiguration($entity->getEntityType()) ? $entity->id() : NULL,
      $auto_export,
      $details,
      $entity
    );
  }

  /**
   * Restore an entity that was added via
   * {@see SyncIntent::embedEntityDefinition} or
   * {@see SyncIntent::embedEntity}.
   *
   * @param array $definition
   *   The definition you saved in a field and gotten
   *   back when calling one of the mentioned functions above.
   *
   * @return \Drupal\Core\Entity\EntityInterface The restored entity.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  public function loadEmbeddedEntity($definition) {
    $version = Flow::getEntityTypeVersion(
      $definition[self::ENTITY_TYPE_KEY],
      $definition[self::BUNDLE_KEY]
    );
    if ($version != $definition[self::VERSION_KEY]) {
      \Drupal::logger('cms_content_sync')->error('Failed to resolve reference to @entity_type:@bundle: Remote version @remote_version doesn\'t match local version @local_version', [
        '@entity_type'  => $definition[self::ENTITY_TYPE_KEY],
        '@bundle' => $definition[self::BUNDLE_KEY],
        '@remote_version' => $definition[self::VERSION_KEY],
        '@local_version' => $version,
      ]);
      return NULL;
    }

    if ($definition[self::AUTO_EXPORT_KEY] === self::ENTITY_REFERENCE_EMBED) {
      $embedded_entity = $definition[self::ENTITY_EMBED_KEY];
      if (empty($embedded_entity)) {
        return NULL;
      }

      $pool_id = $definition[self::API_KEY];
      $pool = Pool::getAll()[$pool_id];
      if (empty($pool)) {
        return NULL;
      }

      $entity_type_name = $definition[self::ENTITY_TYPE_KEY];
      $entity_bundle = $definition[self::BUNDLE_KEY];
      $reason = ImportIntent::IMPORT_AS_DEPENDENCY;
      $action = SyncIntent::ACTION_CREATE;
      $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, $reason, $action);
      if (!$flow) {
        return NULL;
      }

      $intent = new ImportIntent($flow, $pool, $reason, $action, $entity_type_name, $entity_bundle, $embedded_entity);
      $status = $intent->execute();
      if (!$status) {
        return NULL;
      }

      return $intent->getEntity();
    }

    if (empty($definition[self::ID_KEY])) {
      $entity = \Drupal::service('entity.repository')->loadEntityByUuid(
        $definition[self::ENTITY_TYPE_KEY],
        $definition[self::UUID_KEY]
      );
    }
    else {
      $entity = \Drupal::entityTypeManager()->getStorage($definition[self::ENTITY_TYPE_KEY])->load($definition[self::ID_KEY]);
    }

    // Taxonomy terms can be mapped by their name.
    if (!$entity && !empty($definition[self::LABEL_KEY])) {
      $config = $this->flow->getEntityTypeConfig($definition[self::ENTITY_TYPE_KEY], $definition[self::BUNDLE_KEY]);
      if (!empty($config) && !empty($config['handler_settings'][DefaultTaxonomyHandler::MAP_BY_LABEL_SETTING])) {
        $entity_type = \Drupal::entityTypeManager()->getDefinition($definition[self::ENTITY_TYPE_KEY]);
        $label_property = $entity_type->getKey('label');

        $existing = \Drupal::entityTypeManager()->getStorage($definition[self::ENTITY_TYPE_KEY])->loadByProperties([
          $label_property => $definition[self::LABEL_KEY],
        ]);

        $entity = reset($existing);
      }
    }

    return $entity;
  }

  /**
   * Get all embedded entity data besides the predefined keys.
   * Images for example have "alt" and "title" in addition to the file reference.
   *
   * @param $definition
   *
   * @return array
   */
  public function getEmbeddedEntityData($definition) {
    return array_filter($definition, function ($key) {
      return !in_array($key, [
        static::API_KEY,
        static::ENTITY_TYPE_KEY,
        static::BUNDLE_KEY,
        static::VERSION_KEY,
        static::UUID_KEY,
        static::ID_KEY,
        static::AUTO_EXPORT_KEY,
        static::SOURCE_CONNECTION_ID_KEY,
        static::POOL_CONNECTION_ID_KEY,
      ]);
    }, ARRAY_FILTER_USE_KEY);
  }

  /**
   * Get the data that shall be exported to Sync Core.
   *
   * @return array The result.
   */
  public function getData() {
    return array_merge($this->fieldValues, [
      'embed_entities'    => $this->embedEntities,
      'uuid'              => $this->uuid,
      'id'                => $this->id ? $this->id : $this->uuid,
      'apiu_translation'  => $this->translationFieldValues,
    ]);
  }

  /**
   * Provide the value of a field you stored when exporting by using.
   *
   * @see SyncIntent::setField()
   *
   * @param string $name
   *   The name of the field to restore.
   *
   * @return mixed The value you stored for this field.
   */
  public function getField($name) {
    $source = $this->getFieldValues();

    return isset($source[$name]) ? $source[$name] : NULL;
  }

  /**
   * Get all field values at once for the currently active language.
   *
   * @return array All field values for the active language.
   */
  public function getFieldValues() {
    if ($this->activeLanguage) {
      $source = $this->translationFieldValues[$this->activeLanguage];
    }
    else {
      $source = $this->fieldValues;
    }

    return $source;
  }

  /**
   * Set the value of the given field. By default every field handler
   * will have a field available for storage when importing / exporting that
   * accepts all non-associative array-values. Within this array you can
   * use the following types: array, associative array, string, integer, float,
   * boolean, NULL. These values will be JSON encoded when exporting and JSON
   * decoded when importing. They will be saved in a structured database by
   * Sync Core in between, so you can't pass any non-array value by default.
   *
   * @param string $name
   *   The name of the field in question.
   * @param mixed $value
   *   The value to store.
   */
  public function setField($name, $value) {
    if ($this->activeLanguage) {
      if ($this->translationFieldValues === NULL) {
        $this->translationFieldValues = [];
      }
      $this->translationFieldValues[$this->activeLanguage][$name] = $value;
      return;
    }

    $this->fieldValues[$name] = $value;
  }

  /**
   * @see SyncIntent::$entityType
   *
   * @return string
   */
  public function getEntityType() {
    return $this->entityType;
  }

  /**
   * @see SyncIntent::$bundle
   */
  public function getBundle() {
    return $this->bundle;
  }

  /**
   * @see SyncIntent::$uuid
   */
  public function getUuid() {
    return $this->uuid;
  }

  /**
   * @see SyncIntent::$id
   */
  public function getId() {
    return $this->id;
  }

}
