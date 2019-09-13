<?php

namespace Drupal\cms_content_sync\Entity;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityBase;
use Drupal\Core\Entity\EntityChangedTrait;
use Drupal\Core\Entity\EntityTypeInterface;

/**
 * Defines the "CMS Content Sync - Entity Status" entity type.
 *
 * @ingroup cms_content_sync_entity_status
 *
 * @ContentEntityType(
 *   id = "cms_content_sync_entity_status",
 *   label = @Translation("CMS Content Sync - Entity Status"),
 *   base_table = "cms_content_sync_entity_status",
 *   entity_keys = {
 *     "id" = "id",
 *     "flow" = "flow",
 *     "pool" = "pool",
 *     "entity_uuid" = "entity_uuid",
 *     "entity_type" = "entity_type",
 *     "entity_type_version" = "entity_type_version",
 *     "flags" = "flags",
 *   },
 *   handlers = {
 *     "views_data" = "Drupal\views\EntityViewsData",
 *   },
 * )
 */
class EntityStatus extends ContentEntityBase implements EntityStatusInterface {

  use EntityChangedTrait;

  const FLAG_UNUSED_CLONED             = 0x00000001;
  const FLAG_DELETED                   = 0x00000002;
  const FLAG_USER_ALLOWED_EXPORT       = 0x00000004;
  const FLAG_EDIT_OVERRIDE             = 0x00000008;
  const FLAG_IS_SOURCE_ENTITY          = 0x00000010;
  const FLAG_EXPORT_ENABLED            = 0x00000020;
  const FLAG_DEPENDENCY_EXPORT_ENABLED = 0x00000040;
  const FLAG_LAST_EXPORT_RESET         = 0x00000080;
  const FLAG_LAST_IMPORT_RESET         = 0x00000100;
  const FLAG_EXPORT_FAILED             = 0x00000200;
  const FLAG_IMPORT_FAILED             = 0x00000400;
  const FLAG_EXPORT_FAILED_SOFT        = 0x00000800;
  const FLAG_IMPORT_FAILED_SOFT        = 0x00001000;

  const DATA_IMPORT_FAILURE = 'import_failure';
  const DATA_EXPORT_FAILURE = 'export_failure';

  const FLOW_NO_FLOW = 'ERROR_STATUS_ENTITY_FLOW';

  /**
   * {@inheritdoc}
   */
  public static function preCreate(EntityStorageInterface $storage_controller, array &$values) {
    // Set Entity ID or UUID by default one or the other is not set.
    if (!isset($values['entity_type'])) {
      throw new \Exception(t('The type of the entity is required.'));
    }
    if (!isset($values['flow'])) {
      throw new \Exception(t('The flow is required.'));
    }
    if (!isset($values['pool'])) {
      throw new \Exception(t('The pool is required.'));
    }

    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = \Drupal::service('entity.repository')->loadEntityByUuid($values['entity_type'], $values['entity_uuid']);

    if (!isset($values['entity_type_version'])) {
      $values['entity_type_version'] = Flow::getEntityTypeVersion($entity->getEntityType()->id(), $entity->bundle());
      return;
    }
  }

  /**
   * @param string $entity_type
   * @param string $entity_uuid
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   *
   * @return \Drupal\cms_content_sync\Entity\EntityStatus[]
   * @throws \Exception
   */
  public static function getInfoForPool($entity_type, $entity_uuid, Pool $pool) {
    if (!$entity_type) {
      throw new \Exception('$entity_type is required.');
    }
    if (!$entity_uuid) {
      throw new \Exception('$entity_uuid is required.');
    }
    /**
     * @var \Drupal\cms_content_sync\Entity\EntityStatus[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_entity_status')
      ->loadByProperties([
        'entity_type' => $entity_type,
        'entity_uuid' => $entity_uuid,
        'pool'        => $pool->id,
      ]);

    return $entities;
  }

  /**
   * Get a list of all entity status entities for the given entity.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $entity_uuid
   *   The entity UUID.
   * @param array $filter
   *   Additional filters. Usually "flow"=>... or "pool"=>...
   *
   * @return \Drupal\cms_content_sync\Entity\EntityStatus[]
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function getInfosForEntity($entity_type, $entity_uuid, $filter = NULL) {
    if (!$entity_type) {
      throw new \Exception('$entity_type is required.');
    }
    if (!$entity_uuid) {
      throw new \Exception('$entity_uuid is required.');
    }
    $base_filter = [
      'entity_type' => $entity_type,
      'entity_uuid' => $entity_uuid,
    ];

    $filters_combined = $base_filter;
    $filter_without_flow = isset($filter['flow']) && (empty($filter['flow']) || $filter['flow'] == self::FLOW_NO_FLOW);

    if ($filter_without_flow) {
      $filters_combined = array_merge($filters_combined, [
        'flow' => self::FLOW_NO_FLOW,
      ]);
    }
    elseif ($filter) {
      $filters_combined = array_merge($filters_combined, $filter);
    }

    /**
     * @var \Drupal\cms_content_sync\Entity\EntityStatus[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_entity_status')
      ->loadByProperties($filters_combined);

    $result = [];

    // If an import fails, we may create a status entity without a flow assigned.
    // We ignore them for normal functionality, so they're filtered out.
    if ($filter_without_flow) {
      foreach ($entities as $i => $entity) {
        if (!$entity->getFlow()) {
          $result[] = $entity;
        }
      }
    }
    else {
      foreach ($entities as $i => $entity) {
        if ($entity->getFlow()) {
          $result[] = $entity;
        }
      }
    }

    return $result;
  }

  /**
   * @param string $entity_type
   * @param string $entity_uuid
   * @param \Drupal\cms_content_sync\Entity\Flow|string $flow
   * @param \Drupal\cms_content_sync\Entity\Pool|string $pool
   *
   * @return \Drupal\cms_content_sync\Entity\EntityStatus|mixed
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Exception
   */
  public static function getInfoForEntity($entity_type, $entity_uuid, $flow, $pool) {
    if (!$entity_type) {
      throw new \Exception('$entity_type is required.');
    }
    if (!$entity_uuid) {
      throw new \Exception('$entity_uuid is required.');
    }

    $filter = [
      'entity_type' => $entity_type,
      'entity_uuid' => $entity_uuid,
      'pool' => is_string($pool) ? $pool : $pool->id,
    ];

    if ($flow) {
      $filter['flow'] = is_string($flow) ? $flow : $flow->id;
    }
    else {
      $filter['flow'] = self::FLOW_NO_FLOW;
    }

    /**
     * @var \Drupal\cms_content_sync\Entity\EntityStatus[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_entity_status')
      ->loadByProperties($filter);

    if (!$flow) {
      foreach ($entities as $entity) {
        if (!$entity->getFlow()) {
          return $entity;
        }
      }

      return NULL;
    }

    return reset($entities);
  }

  /**
   * @param $entity
   */
  public function resetStatus() {
    $this->setLastExport(NULL);
    $this->setLastImport(NULL);
    $this->save();

    // Above cache clearing doesn't work reliably. So we reset the whole entity cache.
    \Drupal::service('cache.entity')->deleteAll();
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @return int|null
   * @throws \Exception
   */
  public static function getLastExportForEntity(EntityInterface $entity) {
    $entity_status = EntityStatus::getInfosForEntity($entity->getEntityTypeId(), $entity->uuid());
    $latest = NULL;

    foreach ($entity_status as $info) {
      if ($info->getLastExport() && (!$latest || $info->getLastExport() > $latest)) {
        $latest = $info->getLastExport();
      }
    }

    return $latest;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @return int|null
   * @throws \Exception
   */
  public static function getLastImportForEntity(EntityInterface $entity) {
    $entity_status = EntityStatus::getInfosForEntity($entity->getEntityTypeId(), $entity->uuid());
    $latest = NULL;

    foreach ($entity_status as $info) {
      if ($info->getLastImport() && (!$latest || $info->getLastImport() > $latest)) {
        $latest = $info->getLastImport();
      }
    }

    return $latest;
  }

  /**
   *
   */
  public static function accessTemporaryExportPoolInfoForField($entity_type, $uuid, $field_name, $delta, $tree_position = [], $set_flow_id = NULL, $set_pool_ids = NULL, $set_uuid = NULL) {
    static $field_storage = [];

    if ($set_flow_id && $set_pool_ids) {
      $data = [
        'flow_id'   => $set_flow_id,
        'pool_ids'  => $set_pool_ids,
        'uuid'      => $set_uuid,
      ];
      if (!isset($field_storage[$entity_type][$uuid])) {
        $field_storage[$entity_type][$uuid] = [];
      }
      $setter = &$field_storage[$entity_type][$uuid];
      foreach ($tree_position as $name) {
        if (!isset($setter[$name])) {
          $setter[$name] = [];
        }
        $setter = &$setter[$name];
      }
      if (!isset($setter[$field_name][$delta])) {
        $setter[$field_name][$delta] = [];
      }
      $setter = &$setter[$field_name][$delta];
      $setter = $data;
    }
    else {
      if (!empty($field_storage[$entity_type][$uuid])) {
        $value = $field_storage[$entity_type][$uuid];
        foreach ($tree_position as $name) {
          if (!isset($value[$name])) {
            return NULL;
          }
          $value = $value[$name];
        }
        return isset($value[$field_name][$delta]) ? $value[$field_name][$delta] : NULL;
      }
    }

    return NULL;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   * @param string $parent_field_name
   * @param int $parent_field_delta
   * @param \Drupal\Core\Entity\EntityInterface $reference
   * @param array $tree_position
   */
  public static function saveSelectedExportPoolInfoForField($parent_entity, $parent_field_name, $parent_field_delta, $reference, $tree_position = []) {
    $data = EntityStatus::accessTemporaryExportPoolInfoForField($parent_entity->getEntityTypeId(), $parent_entity->uuid(), $parent_field_name, $parent_field_delta, $tree_position);

    // On sites that don't export, this will be NULL.
    if (empty($data['flow_id'])) {
      return;
    }

    $values = $data['pool_ids'];

    $processed = [];
    if (is_array($values)) {
      foreach ($values as $id => $selected) {
        if ($selected && $id !== 'ignore') {
          $processed[] = $id;
        }
      }
    }
    else {
      if ($values !== 'ignore') {
        $processed[] = $values;
      }
    }

    EntityStatus::saveSelectedExportPoolInfo($reference, $data['flow_id'], $processed, $parent_entity, $parent_field_name);
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $reference
   * @param string $flow_id
   * @param string[] $pool_ids
   * @param null|EntityInterface $parent_entity
   * @param null|string $parent_field_name
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function saveSelectedExportPoolInfo($reference, $flow_id, $pool_ids, $parent_entity = NULL, $parent_field_name = NULL) {
    $entity_type = $reference->getEntityTypeId();
    $bundle = $reference->bundle();
    $uuid = $reference->uuid();

    $flow = Flow::getAll()[$flow_id];
    $pools = Pool::getAll();

    $entity_type_pools = Pool::getSelectablePools($entity_type, $bundle, $parent_entity, $parent_field_name)[$flow_id]['pools'];
    foreach ($entity_type_pools as $entity_type_pool_id => $config) {
      $pool = $pools[$entity_type_pool_id];
      $entity_status = EntityStatus::getInfoForEntity($entity_type, $uuid, $flow, $pool);
      if (in_array($entity_type_pool_id, $pool_ids)) {
        if (!$entity_status) {
          $entity_status = EntityStatus::create([
            'flow' => $flow->id,
            'pool' => $pool->id,
            'entity_type' => $entity_type,
            'entity_uuid' => $uuid,
            'entity_type_version' => Flow::getEntityTypeVersion($entity_type, $bundle),
            'flags' => 0,
            'source_url' => NULL,
          ]);
        }

        $entity_status->isExportEnabled(TRUE);
        $entity_status->save();

        continue;
      }

      if ($entity_status) {
        $entity_status->isExportEnabled(FALSE);
        $entity_status->save();
      }
    }
  }

  /**
   * Get the entity this entity status belongs to.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity() {
    return \Drupal::service('entity.repository')->loadEntityByUuid(
      $this->getEntityTypeName(),
      $this->getUuid()
    );
  }

  /**
   * Returns the information if the entity has been exported before but the last export date was reset.
   *
   * @param bool $set
   *   Optional parameter to set the value for LastExportReset.
   *
   * @return bool
   */
  public function wasLastExportReset($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_LAST_EXPORT_RESET);
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_LAST_EXPORT_RESET);
    }
    return (bool) ($this->get('flags')->value & self::FLAG_LAST_EXPORT_RESET);
  }

  /**
   * Returns the information if the entity has been imported before but the last import date was reset.
   *
   * @param bool $set
   *   Optional parameter to set the value for LastImportReset.
   *
   * @return bool
   */
  public function wasLastImportReset($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_LAST_IMPORT_RESET);
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_LAST_IMPORT_RESET);
    }
    return (bool) ($this->get('flags')->value & self::FLAG_LAST_IMPORT_RESET);
  }

  /**
   * Returns the information if the last export of the entity failed.
   *
   * @param bool $set
   *   Optional parameter to set the value for ExportFailed.
   * @param bool $soft
   *   A soft fail- this was intended according to configuration. But the user might want to know why to debug different
   *   expectations.
   * @param null|array $details
   *   If $set is TRUE, you can provide additional details on why the export failed. Can be gotten via
   *   ->whyDidExportFail()
   *
   * @return bool
   */
  public function didExportFail($set = NULL, $soft = FALSE, $details = NULL) {
    $flag = $soft ? self::FLAG_EXPORT_FAILED_SOFT : self::FLAG_EXPORT_FAILED;
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | $flag);
      if (!empty($details)) {
        $this->setData(self::DATA_EXPORT_FAILURE, $details);
      }
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~$flag);
      $this->setData(self::DATA_EXPORT_FAILURE, NULL);
    }
    return (bool) ($this->get('flags')->value & $flag);
  }

  /**
   * Get the details provided to ->didExportFail( TRUE, ... ) before.
   *
   * @return array|null
   */
  public function whyDidExportFail() {
    return $this->getData(self::DATA_EXPORT_FAILURE);
  }

  /**
   * Returns the information if the last import of the entity failed.
   *
   * @param bool $set
   *   Optional parameter to set the value for ImportFailed.
   * @param bool $soft
   *   A soft fail- this was intended according to configuration. But the user might want to know why to debug different
   *   expectations.
   * @param array|null $details
   *   If $set is TRUE, you can provide additional details on why the import failed. Can be gotten via
   *   ->whyDidImportFail()
   *
   * @return bool
   */
  public function didImportFail($set = NULL, $soft = FALSE, $details = NULL) {
    $flag = $soft ? self::FLAG_IMPORT_FAILED_SOFT : self::FLAG_IMPORT_FAILED;
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | $flag);
      if (!empty($details)) {
        $this->setData(self::DATA_IMPORT_FAILURE, $details);
      }
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~$flag);
      $this->setData(self::DATA_IMPORT_FAILURE, NULL);
    }
    return (bool) ($this->get('flags')->value & $flag);
  }

  /**
   * Get the details provided to ->didImportFail( TRUE, ... ) before.
   *
   * @return array|null
   */
  public function whyDidImportFail() {
    return $this->getData(self::DATA_IMPORT_FAILURE);
  }

  /**
   * Returns the information if the entity has been chosen by the user to
   * be exported with this flow and pool.
   *
   * @param bool $setExportEnabled
   *   Optional parameter to set the value for ExportEnabled.
   * @param bool $setDependencyExportEnabled
   *   Optional parameter to set the value for DependencyExportEnabled.
   *
   * @return bool
   */
  public function isExportEnabled($setExportEnabled = NULL, $setDependencyExportEnabled = NULL) {
    if ($setExportEnabled === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_EXPORT_ENABLED);
    }
    elseif ($setExportEnabled === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_EXPORT_ENABLED);
    }
    if ($setDependencyExportEnabled === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_DEPENDENCY_EXPORT_ENABLED);
    }
    elseif ($setDependencyExportEnabled === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_DEPENDENCY_EXPORT_ENABLED);
    }
    return (bool) ($this->get('flags')->value & (self::FLAG_EXPORT_ENABLED | self::FLAG_DEPENDENCY_EXPORT_ENABLED));
  }

  /**
   * Returns the information if the entity has been chosen by the user to
   * be exported with this flow and pool.
   *
   * @return bool
   */
  public function isManualExportEnabled() {
    return (bool) ($this->get('flags')->value & (self::FLAG_EXPORT_ENABLED));
  }

  /**
   * Returns the information if the entity has been exported with this flow and
   * pool as a dependency.
   *
   * @return bool
   */
  public function isDependencyExportEnabled() {
    return (bool) ($this->get('flags')->value & (self::FLAG_DEPENDENCY_EXPORT_ENABLED));
  }

  /**
   * Returns the information if the user override the entity locally.
   *
   * @param bool $set
   *   Optional parameter to set the value for EditOverride.
   *
   * @param bool $individual
   *
   * @return bool
   */
  public function isOverriddenLocally($set = NULL, $individual = FALSE) {
    $status = EntityStatus::getInfosForEntity($this->getEntityTypeName(), $this->getUuid());
    if ($set === TRUE) {
      if ($individual) {
        $this->set('flags', $this->get('flags')->value | self::FLAG_EDIT_OVERRIDE);
      }
      else {
        foreach ($status as $info) {
          $info->isOverriddenLocally(TRUE, TRUE);
        }
      }
      return TRUE;
    }
    elseif ($set === FALSE) {
      if ($individual) {
        $this->set('flags', $this->get('flags')->value & ~self::FLAG_EDIT_OVERRIDE);
      }
      else {
        foreach ($status as $info) {
          $info->isOverriddenLocally(FALSE, TRUE);
        }
      }
      return FALSE;
    }

    if ($individual) {
      return (bool) ($this->get('flags')->value & self::FLAG_EDIT_OVERRIDE);
    }

    foreach ($status as $info) {
      if ($info->isOverriddenLocally(NULL, TRUE)) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Returns the information if the entity has originally been created on this
   * site.
   *
   * @param bool $set
   *   Optional parameter to set the value for IsSourceEntity.
   *
   * @return bool
   */
  public function isSourceEntity($set = NULL, $individual = FALSE) {
    $status = EntityStatus::getInfosForEntity($this->getEntityTypeName(), $this->getUuid());
    if ($set === TRUE) {
      if ($individual) {
        $this->set('flags', $this->get('flags')->value | self::FLAG_IS_SOURCE_ENTITY);
      }
      else {
        foreach ($status as $info) {
          $info->isSourceEntity(TRUE, TRUE);
        }
        $this->isSourceEntity(TRUE, TRUE);
      }
      return TRUE;
    }
    elseif ($set === FALSE) {
      if ($individual) {
        $this->set('flags', $this->get('flags')->value & ~self::FLAG_IS_SOURCE_ENTITY);
      }
      else {
        foreach ($status as $info) {
          $info->isSourceEntity(FALSE, TRUE);
        }
        $this->isSourceEntity(FALSE, TRUE);
      }
      return FALSE;
    }

    if ($individual) {
      return (bool) ($this->get('flags')->value & self::FLAG_IS_SOURCE_ENTITY);
    }

    foreach ($status as $info) {
      if ($info->isSourceEntity(NULL, TRUE)) {
        return TRUE;
      }
    }
    return $this->isSourceEntity(NULL, TRUE);
  }

  /**
   * Returns the information if the user allowed the export.
   *
   * @param bool $set
   *   Optional parameter to set the value for UserAllowedExport.
   *
   * @return bool
   */
  public function didUserAllowExport($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_USER_ALLOWED_EXPORT);
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_USER_ALLOWED_EXPORT);
    }
    return (bool) ($this->get('flags')->value & self::FLAG_USER_ALLOWED_EXPORT);
  }

  /**
   * Returns the information if the entity is deleted.
   *
   * @param bool $set
   *   Optional parameter to set the value for Deleted.
   *
   * @return bool
   */
  public function isDeleted($set = NULL) {
    if ($set === TRUE) {
      $this->set('flags', $this->get('flags')->value | self::FLAG_DELETED);
    }
    elseif ($set === FALSE) {
      $this->set('flags', $this->get('flags')->value & ~self::FLAG_DELETED);
    }
    return (bool) ($this->get('flags')->value & self::FLAG_DELETED);
  }

  /**
   * Returns the timestamp for the last import.
   *
   * @return int
   */
  public function getLastImport() {
    return $this->get('last_import')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param int $timestamp
   */
  public function setLastImport($timestamp) {
    if ($this->getLastImport() == $timestamp) {
      return;
    }

    $this->set('last_import', $timestamp);

    // As this import was successful, we can now reset the flags for status entity resets and failed imports.
    if (!empty($timestamp)) {
      $this->wasLastImportReset(FALSE);
      $this->didImportFail(FALSE);

      // Delete status entities without Flow assigned- they're no longer needed.
      $error_entities = EntityStatus::getInfosForEntity($this->getEntityTypeName(), $this->getUuid(), ['flow' => self::FLOW_NO_FLOW], TRUE);
      foreach ($error_entities as $entity) {
        $entity->delete();
      }
    }
    // Otherwise this entity has been reset.
    else {
      $this->wasLastImportReset(TRUE);
    }
  }

  /**
   * Returns the UUID of the entity this information belongs to.
   *
   * @return string
   */
  public function getUuid() {
    return $this->get('entity_uuid')->value;
  }

  /**
   * Returns the entity type name of the entity this information belongs to.
   *
   * @return string
   */
  public function getEntityTypeName() {
    return $this->get('entity_type')->value;
  }

  /**
   * Returns the timestamp for the last export.
   *
   * @return int
   */
  public function getLastExport() {
    return $this->get('last_export')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param int $timestamp
   */
  public function setLastExport($timestamp) {
    if ($this->getLastExport() == $timestamp) {
      return;
    }

    $this->set('last_export', $timestamp);

    // As this export was successful, we can now reset the flags for status entity resets and failed exports.
    if (!empty($timestamp)) {
      $this->wasLastExportReset(FALSE);
      $this->didExportFail(FALSE);
    }
    // Otherwise this entity has been reset.
    else {
      $this->wasLastExportReset(TRUE);
    }
  }

  /**
   * Get the flow.
   *
   * @return \Drupal\cms_content_sync\Entity\Flow
   */
  public function getFlow() {
    if (empty($this->get('flow')->value)) {
      return NULL;
    }

    $flows = Flow::getAll();
    if (empty($flows[$this->get('flow')->value])) {
      return NULL;
    }

    return $flows[$this->get('flow')->value];
  }

  /**
   * Get the pool.
   *
   * @return \Drupal\cms_content_sync\Entity\Pool
   */
  public function getPool() {
    return Pool::getAll()[$this->get('pool')->value];
  }

  /**
   * Returns the entity type version.
   *
   * @return string
   */
  public function getEntityTypeVersion() {
    return $this->get('entity_type_version')->value;
  }

  /**
   * Set the last import timestamp.
   *
   * @param string $version
   */
  public function setEntityTypeVersion($version) {
    $this->set('entity_type_version', $version);
  }

  /**
   * Returns the entities source url.
   *
   * @return string
   */
  public function getSourceUrl() {
    return $this->get('source_url')->value;
  }

  /**
   * Get a previously saved key=>value pair.
   *
   * @see self::setData()
   *
   * @param null|string|string[] $key
   *   The key to retrieve.
   *
   * @return mixed Whatever you previously stored here or NULL if the key
   *   doesn't exist.
   */
  public function getData($key = NULL) {
    $data    = $this->get('data')->getValue()[0];
    $storage = &$data;

    if (empty($key)) {
      return $data;
    }

    if (!is_array($key)) {
      $key = [$key];
    }

    foreach ($key as $index) {
      if (!isset($storage[$index])) {
        return NULL;
      }
      $storage = &$storage[$index];
    }

    return $storage;
  }

  /**
   * Set a key=>value pair.
   *
   * @param string|string[] $key
   *   The key to set (for hierarchical usage, provide
   *   an array of indices.
   * @param mixed $value
   *   The value to set. Must be a valid value for Drupal's
   *   "map" storage (so basic types that can be serialized).
   */
  public function setData($key, $value) {
    $data = $this->get('data')->getValue();
    if (!empty($data)) {
      $data = $data[0];
    }
    else {
      $data = [];
    }
    $storage = &$data;

    if (is_string($key) && $value === NULL) {
      if (isset($data[$key])) {
        unset($data[$key]);
      }
    }
    else {
      if (!is_array($key)) {
        $key = [$key];
      }

      foreach ($key as $index) {
        if (!isset($storage[$index])) {
          $storage[$index] = [];
        }
        $storage = &$storage[$index];
      }

      $storage = $value;
    }

    $this->set('data', $data);
  }

  /**
   * {@inheritdoc}
   */
  public static function baseFieldDefinitions(EntityTypeInterface $entity_type) {
    $fields = parent::baseFieldDefinitions($entity_type);

    $fields['flow'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Flow'))
      ->setDescription(t('The flow the status entity is based on.'));

    $fields['pool'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Pool'))
      ->setDescription(t('The pool the entity is connected to.'));

    $fields['entity_uuid'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity UUID'))
      ->setDescription(t('The UUID of the entity that is synchronized.'))
      ->setSetting('max_length', 128);

    $fields['entity_type'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type'))
      ->setDescription(t('The entity type of the entity that is synchronized.'));

    $fields['entity_type_version'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Entity type version'))
      ->setDescription(t('The version of the entity type provided by Content Sync.'))
      ->setSetting('max_length', 32);

    $fields['source_url'] = BaseFieldDefinition::create('string')
      ->setLabel(t('Source URL'))
      ->setDescription(t('The entities source URL.'))
      ->setRequired(FALSE);

    $fields['last_export'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last exported'))
      ->setDescription(t('The last time the entity got exported.'))
      ->setRequired(FALSE);

    $fields['last_import'] = BaseFieldDefinition::create('timestamp')
      ->setLabel(t('Last import'))
      ->setDescription(t('The last time the entity got imported.'))
      ->setRequired(FALSE);

    $fields['flags'] = BaseFieldDefinition::create('integer')
      ->setLabel(t('Flags'))
      ->setDescription(t('Stores boolean information about the exported/imported entity.'))
      ->setSetting('unsigned', TRUE)
      ->setDefaultValue(0);

    $fields['data'] = BaseFieldDefinition::create('map')
      ->setLabel(t('Data'))
      ->setDescription(t('Stores further information about the exported/imported entity that can also be used by entity and field handlers.'))
      ->setRequired(FALSE);

    return $fields;
  }

}
