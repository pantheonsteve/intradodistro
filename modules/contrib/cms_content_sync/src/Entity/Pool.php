<?php

namespace Drupal\cms_content_sync\Entity;

use Drupal\cms_content_sync\SyncCore\Client;
use Drupal\cms_content_sync\SyncCore\DataCondition;
use Drupal\cms_content_sync\SyncCore\ParentCondition;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionStorage;
use Drupal\cms_content_sync\SyncCore\Storage\ConnectionSynchronizationStorage;
use Drupal\cms_content_sync\SyncCore\Storage\EntityTypeStorage;
use Drupal\cms_content_sync\SyncCore\Storage\InstanceStorage;
use Drupal\cms_content_sync\SyncCore\Storage\MetaInformationConnectionStorage;
use Drupal\cms_content_sync\SyncCore\Storage\PreviewEntityStorage;
use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Site\Settings;
use Drupal\cms_content_sync\SyncCorePoolExport;
use Drupal\cms_content_sync\ExportIntent;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Defines the "CMS Content Sync - Pool" entity.
 *
 * @ConfigEntityType(
 *   id = "cms_content_sync_pool",
 *   label = @Translation("CMS Content Sync - Pool"),
 *   handlers = {
 *     "list_builder" = "Drupal\cms_content_sync\Controller\PoolListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cms_content_sync\Form\PoolForm",
 *       "edit" = "Drupal\cms_content_sync\Form\PoolForm",
 *       "delete" = "Drupal\cms_content_sync\Form\PoolDeleteForm",
 *     }
 *   },
 *   config_prefix = "pool",
 *   admin_permission = "administer cms content sync:",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *   },
 *   links = {
 *     "edit-form" = "/admin/config/services/cms_content_sync/pool/{cms_content_sync_pool}/edit",
 *     "delete-form" = "/admin/config/services/cms_content_sync/synchronizations/{cms_content_sync_pool}/delete",
 *   }
 * )
 */
class Pool extends ConfigEntityBase implements PoolInterface {

  /**
   * @var string POOL_USAGE_FORBID Forbid usage of this pool for this flow.
   */
  const POOL_USAGE_FORBID = 'forbid';
  /**
   * @var string POOL_USAGE_ALLOW Allow usage of this pool for this flow.
   */
  const POOL_USAGE_ALLOW = 'allow';
  /**
   * @var string POOL_USAGE_FORCE Force usage of this pool for this flow.
   */
  const POOL_USAGE_FORCE = 'force';

  /**
   * @var string AUTHENTICATION_TYPE_COOKIE Use Drupal default cookie
   * authentication before making any REST requests to this site.
   */
  const AUTHENTICATION_TYPE_COOKIE = 'cookie';
  /**
   * @var string AUTHENTICATION_TYPE_BASIC_AUTH always send username:password
   * as Authentication header when making REST requests to this site.
   */
  const AUTHENTICATION_TYPE_BASIC_AUTH = 'basic_auth';

  /**
   * The Pool ID.
   *
   * @var string
   */
  public $id;

  /**
   * The Pool label.
   *
   * @var string
   */
  public $label;

  /**
   * The Pool Sync Core backend URL.
   *
   * @var string
   */
  public $backend_url;

  /**
   * The authentication type to use.
   * See Pool::AUTHENTICATION_TYPE_* for details.
   *
   * @var string
   */
  public $authentication_type;

  /**
   * The unique site identifier.
   *
   * @var string
   */
  public $site_id;

  /**
   * @var \Drupal\cms_content_sync\SyncCore\Client
   */
  protected $client;

  /**
   * @return \Drupal\cms_content_sync\SyncCore\Client
   */
  public function getClient() {
    if (!$this->client) {
      $this->client = new Client($this->getBackendUrl());
    }

    return $this->client;
  }

  /**
   * @param string $type
   *
   * @return \Drupal\cms_content_sync\SyncCore\Storage\Storage|null
   */
  public function getStorage($type) {
    static $cache;

    if (isset($cache[$type])) {
      return $cache[$type];
    }

    if ($type == MetaInformationConnectionStorage::ID) {
      return $cache[$type] = new MetaInformationConnectionStorage($this);
    }
    if ($type == ConnectionStorage::ID) {
      return $cache[$type] = new ConnectionStorage($this);
    }
    if ($type == EntityTypeStorage::ID) {
      return $cache[$type] = new EntityTypeStorage($this);
    }
    if ($type == ConnectionSynchronizationStorage::ID) {
      return $cache[$type] = new ConnectionSynchronizationStorage($this);
    }
    if ($type == ConnectionSynchronizationStorage::ID) {
      return $cache[$type] = new ConnectionSynchronizationStorage($this);
    }
    if ($type == PreviewEntityStorage::ID) {
      return $cache[$type] = new PreviewEntityStorage($this);
    }

    return NULL;
  }

  /**
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param string $version
   * @param bool $is_export
   *
   * @return \Drupal\cms_content_sync\SyncCore\Entity\ConnectionSynchronization
   */
  public function getConnectionSynchronizationForEntityType($entity_type_name, $bundle_name, $version, $is_export) {
    $connection_id = ConnectionStorage::getExternalConnectionId(
      $this->id,
      $this->getSiteId(),
      $entity_type_name,
      $bundle_name
    );

    $id = ConnectionSynchronizationStorage::getExternalConnectionSynchronizationId($connection_id, $is_export);

    /**
     * @var \Drupal\cms_content_sync\SyncCore\Entity\ConnectionSynchronizationStorage $storage
     */
    $storage = $this->getStorage(ConnectionSynchronizationStorage::ID);

    return $storage->getEntity($id);
  }

  /**
   * {@inheritdoc}
   */
  public static function preDelete(EntityStorageInterface $storage, array $entities) {
    parent::preDelete($storage, $entities);

    try {
      foreach ($entities as $entity) {
        $exporter = new SyncCorePoolExport($entity);
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
   * Get a list of all sites from this pool that use a different version ID and
   * provide a diff on field basis.
   *
   * @param string $entity_type
   * @param string $bundle
   *
   * @return array site_id[]
   *
   * @throws \Exception
   */
  public function getSitesWithDifferentEntityTypeVersion($entity_type, $bundle) {
    /**
     * @var \Drupal\cms_content_sync\SyncCore\Storage\ConnectionStorage $connectionStorage
     */
    $connectionStorage = $this->getStorage(ConnectionStorage::ID);

    /**
     * @var \Drupal\cms_content_sync\SyncCore\Storage\EntityTypeStorage $entityTypeStorage
     */
    $entityTypeStorage = $this->getStorage(EntityTypeStorage::ID);

    $items = $connectionStorage->createListQuery()
      ->setCondition(
        DataCondition::create('id', DataCondition::MATCHES_REGEX, '^drupal-' . $this->id . '-.+-' . $entity_type . '-' . $bundle . '$')
      )
      ->orderBy('id')
      ->getDetails()
      ->execute()
      ->getAll();

    $result = [];

    $target_version = Flow::getEntityTypeVersion($entity_type, $bundle);

    $same_version_sites  = [];
    $other_version_sites = [];
    $sites               = [];

    foreach ($items as $item) {
      $version = preg_replace('@^.+-([^-]+)$@', '$1', $item['entity_type']['id']);
      $site_id = preg_replace('@^drupal-' . $this->id . '-(.+)-' . $entity_type . '-.+$@', '$1', $item['id']);

      if ($site_id == InstanceStorage::POOL_SITE_ID) {
        continue;
      }

      if ($site_id == $this->getSiteId()) {
        if ($version == $target_version) {
          $sites[$site_id] = $item;
        }
        continue;
      }

      $sites[$site_id] = $item;

      if ($target_version == $version) {
        $same_version_sites[] = $site_id;
      }
      else {
        $other_version_sites[] = $site_id;
      }
    }

    if (!isset($sites[$this->getSiteId()])) {
      return $result;
    }

    $other_version_sites = array_diff($other_version_sites, $same_version_sites);

    $this_entity_type = $entityTypeStorage
      ->createItemQuery()
      ->setEntityId($sites[$this->getSiteId()]['entity_type']['id'])
      ->execute()
      ->getItem();

    foreach ($other_version_sites as $site_id) {
      $item = $sites[$site_id];

      $data = $entityTypeStorage
        ->createItemQuery()
        ->setEntityId($item['entity_type']['id'])
        ->execute()
        ->getItem();

      $result[$site_id] = $this->getEntityTypeDiff($this_entity_type, $data);
    }

    return $result;
  }

  /**
   * Get a list of all sites from all pools that use a different version ID and
   * provide a diff on field basis.
   *
   * @param string $entity_type
   * @param string $bundle
   *
   * @return array pool => site_id[]
   *
   * @throws \Exception
   */
  public static function getAllSitesWithDifferentEntityTypeVersion($entity_type, $bundle) {
    $result = [];

    foreach (Pool::getAll() as $pool_id => $pool) {
      $diff = $pool->getSitesWithDifferentEntityTypeVersion($entity_type, $bundle);
      if (empty($diff)) {
        continue;
      }
      $result[$pool_id] = $diff;
    }

    return $result;
  }

  /**
   * Get a list of fields that either the remote site or local site is missing
   * in comparison.
   *
   * @param $mine
   * @param $theirs
   *
   * @return array
   */
  protected function getEntityTypeDiff($mine, $theirs) {
    $result = [];

    foreach ($mine['new_properties'] as $name => $type) {
      if (isset($theirs['new_properties'][$name])) {
        continue;
      }

      $result['remote_missing'][] = $name;
    }

    foreach ($theirs['new_properties'] as $name => $type) {
      if (isset($mine['new_properties'][$name])) {
        continue;
      }

      $result['local_missing'][] = $name;
    }

    return $result;
  }

  /**
   * Get a list of all sites using the given entity from this pool.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $shared_entity_id
   *
   * @return array site_id[]
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function getExternalUsages($entity_type, $bundle, $shared_entity_id) {
    /**
     * @var \Drupal\cms_content_sync\SyncCore\Storage\MetaInformationConnectionStorage $storage
     */
    $storage = $this->getStorage(MetaInformationConnectionStorage::ID);

    $items = $storage->createListQuery()
      ->orderBy('connection_id')
      ->getDetails()
      ->setCondition(new ParentCondition(
        ParentCondition::MATCH_ALL,
        [
          new DataCondition('entity_id', DataCondition::IS_EQUAL_TO, $shared_entity_id),
          new DataCondition('connection_id', DataCondition::MATCHES_REGEX, '^drupal-' . $this->id . '-.+-' . $entity_type . '-' . $bundle . '$'),
        ]
      ))
      ->execute()
      ->getAll();

    $result = [];

    foreach ($items as $item) {
      if (!empty($item['deleted_at'])) {
        continue;
      }
      $site_id = preg_replace('@^drupal-' . $this->id . '-(.+)-' . $entity_type . '-' . $bundle . '$@', '$1', $item['connection']['id']);

      if ($site_id == InstanceStorage::POOL_SITE_ID) {
        continue;
      }

      if ($site_id == $this->getSiteId()) {
        continue;
      }

      if (empty($item['entity']['_resource_url'])) {
        continue;
      }

      $entity = $this->getClient()->get($item['entity']['_resource_url']);

      $result[$site_id] = $entity['url'];
    }

    return $result;
  }

  /**
   * Get a list of all sites for all pools that are using this entity.
   * Only works for pools that are connected to the entity on this site.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return array pool => site_id[]
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public static function getAllExternalUsages($entity) {
    $entity_type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $entity_uuid = $entity->uuid();

    $result = [];

    foreach (EntityStatus::getInfosForEntity($entity_type, $entity_uuid) as $status) {
      $pool = $status->getPool();
      if (empty($pool)) {
        continue;
      }

      $pool_id = $pool->id;
      if (isset($result[$pool_id])) {
        continue;
      }

      if ($entity instanceof ConfigEntityInterface) {
        $shared_entity_id = $entity->id();
      }
      else {
        $shared_entity_id = $entity_uuid;
      }

      $result[$pool_id] = $pool->getExternalUsages($entity_type, $bundle, $shared_entity_id);
    }

    return $result;
  }

  /**
   * Returns the CMS Content Sync Backend URL for this pool.
   *
   * @return string
   */
  public function getBackendUrl() {
    // Check if the BackendUrl got overwritten.
    $cms_content_sync_settings = Settings::get('cms_content_sync');
    if (isset($cms_content_sync_settings) && isset($cms_content_sync_settings['pools'][$this->id]['backend_url'])) {
      return $cms_content_sync_settings['pools'][$this->id]['backend_url'];
    }
    else {
      return $this->backend_url;
    }
  }

  /**
   * Returns the authentication type to use for the Sync Core.
   *
   * @return string
   */
  public function getAuthenticationType() {
    return $this->authentication_type;
  }

  /**
   * Returns the site id this pool.
   *
   * @return string
   */
  public function getSiteId() {
    // Check if the siteID got overwritten.
    $cms_content_sync_settings = Settings::get('cms_content_sync');
    if (isset($cms_content_sync_settings) && isset($cms_content_sync_settings['pools'][$this->id]['site_id'])) {
      return $cms_content_sync_settings['pools'][$this->id]['site_id'];
    }
    else {
      return $this->site_id;
    }
  }

  /**
   * Get the newest import/export timestamp for this pool from all status
   * entities that exist for the given entity.
   *
   * @param $entity_type
   * @param $entity_uuid
   * @param bool $import
   *
   * @return int|null
   */
  public function getNewestTimestamp($entity_type, $entity_uuid, $import = TRUE) {
    $entity_status = EntityStatus::getInfoForPool($entity_type, $entity_uuid, $this);
    $timestamp = NULL;
    foreach ($entity_status as $info) {
      $item_timestamp = $import ? $info->getLastImport() : $info->getLastExport();
      if ($item_timestamp) {
        if (!$timestamp || $timestamp < $item_timestamp) {
          $timestamp = $item_timestamp;
        }
      }
    }
    return $timestamp;
  }

  /**
   * Get the newest import/export timestamp for this pool from all status
   * entities that exist for the given entity.
   *
   * @param $entity_type
   * @param $entity_uuid
   * @param int $timestamp
   * @param bool $import
   */
  public function setTimestamp($entity_type, $entity_uuid, $timestamp, $import = TRUE) {
    $entity_status = EntityStatus::getInfoForPool($entity_type, $entity_uuid, $this);
    foreach ($entity_status as $info) {
      if ($import) {
        $info->setLastImport($timestamp);
      }
      else {
        $info->setLastExport($timestamp);
      }
      $info->save();
    }
  }

  /**
   * Mark the entity as deleted in this pool (reflected on all entity status
   * entities related to this pool).
   *
   * @param $entity_type
   * @param $entity_uuid
   */
  public function markDeleted($entity_type, $entity_uuid) {
    $entity_status = EntityStatus::getInfoForPool($entity_type, $entity_uuid, $this);
    foreach ($entity_status as $info) {
      $info->isDeleted(TRUE);
      $info->save();
    }
  }

  /**
   * Check whether this entity has been deleted intentionally already. In this
   * case we ignore export and import intents for it.
   *
   * @param $entity_type
   * @param $entity_uuid
   *
   * @return bool
   */
  public function isEntityDeleted($entity_type, $entity_uuid) {
    $entity_status = EntityStatus::getInfoForPool($entity_type, $entity_uuid, $this);
    foreach ($entity_status as $info) {
      if ($info->isDeleted()) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Load all cms_content_sync_pool entities.
   *
   * @return \Drupal\cms_content_sync\Entity\Pool[]
   */
  public static function getAll() {

    /**
     * @var \Drupal\cms_content_sync\Entity\Pool[] $configurations
     */
    $configurations = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_pool')
      ->loadMultiple();

    return $configurations;
  }

  /**
   * Returns an list of pools that can be selected for an entity type.
   *
   * @oaram string $entity_type
   *  The entity type the pools should be returned for.
   * @param string $bundle
   *   The bundle the pools should be returned for.
   * @param \Drupal\Core\Entity\EntityInterface $parent_entity
   *   The
   *   parent entity, if any. Only required if $field_name is given-.
   * @param string $field_name
   *   The name of the parent entity field that
   *   references this entity. In this case if the field handler is set to
   *   "automatically export referenced entities", the user doesn't have to
   *   make a choice as it is set automatically anyway.
   *
   * @return array $selectable_pools
   */
  public static function getSelectablePools($entity_type, $bundle, $parent_entity = NULL, $field_name = NULL) {
    // Get all available flows.
    $flows = Flow::getAll();
    $configs = [];
    $selectable_pools = [];
    $selectable_flows = [];

    // When editing the entity directly, the "export as reference" flows won't be available and vice versa.
    $root_entity = !$parent_entity && !$field_name;
    if ($root_entity) {
      $allowed_export_options = [ExportIntent::EXPORT_FORCED, ExportIntent::EXPORT_MANUALLY, ExportIntent::EXPORT_AUTOMATICALLY];
    }
    else {
      $allowed_export_options = [ExportIntent::EXPORT_FORCED, ExportIntent::EXPORT_AS_DEPENDENCY];
    }

    foreach ($flows as $flow_id => $flow) {
      $flow_entity_config = $flow->getEntityTypeConfig($entity_type, $bundle);
      if (empty($flow_entity_config)) {
        continue;
      }
      if ($flow_entity_config['handler'] == 'ignore') {
        continue;
      }
      if (!in_array($flow_entity_config['export'], $allowed_export_options)) {
        continue;
      }
      if ($parent_entity && $field_name) {
        $parent_flow_config = $flow->sync_entities[$parent_entity->getEntityTypeId() . '-' . $parent_entity->bundle() . '-' . $field_name];
        if (!empty($parent_flow_config['handler_settings']['export_referenced_entities'])) {
          continue;
        }
      }

      $selectable_flows[$flow_id] = $flow;

      $configs[$flow_id] = [
        'flow_label' => $flow->label(),
        'flow' => $flow->getEntityTypeConfig($entity_type, $bundle),
      ];
    }

    foreach ($configs as $config_id => $config) {
      if (in_array('allow', $config['flow']['export_pools'])) {
        $selectable_pools[$config_id]['flow_label'] = $config['flow_label'];
        $selectable_pools[$config_id]['widget_type'] = $config['flow']['pool_export_widget_type'];
        foreach ($config['flow']['export_pools'] as $pool_id => $export_pool) {

          // Filter out all pools with configuration "allow".
          if ($export_pool == self::POOL_USAGE_ALLOW) {
            $pool_entity = \Drupal::entityTypeManager()->getStorage('cms_content_sync_pool')
              ->loadByProperties(['id' => $pool_id]);
            $pool_entity = reset($pool_entity);
            $selectable_pools[$config_id]['pools'][$pool_id] = $pool_entity->label();
          }
        }
      }
    }
    return $selectable_pools;
  }

  /**
   * Reset the status entities for this pool.
   *
   * @param string $pool_id
   *   The pool the status entities should be reset for.
   */
  public static function resetStatusEntities($pool_id = '') {

    // Reset the entity status.
    $status_storage = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_entity_status');

    $connection = \Drupal::database();

    // For a single pool.
    if (!empty($pool_id)) {
      // Save flags to status entities that they have been reset.
      $connection->query('UPDATE cms_content_sync_entity_status SET flags=flags|:flag WHERE last_export IS NOT NULL AND pool=:pool', [
        ':flag' => EntityStatus::FLAG_LAST_EXPORT_RESET,
        ':pool' => $pool_id,
      ]);
      $connection->query('UPDATE cms_content_sync_entity_status SET flags=flags|:flag WHERE last_import IS NOT NULL AND pool=:pool', [
        ':flag' => EntityStatus::FLAG_LAST_IMPORT_RESET,
        ':pool' => $pool_id,
      ]);

      // Actual reset.
      $db_query = $connection->update($status_storage->getBaseTable());
      $db_query->fields([
        'last_export' => NULL,
        'last_import' => NULL,
      ]);
      $db_query->condition('pool', $pool_id);
      $db_query->execute();
    }
    // For all pools.
    else {
      // Save flags to status entities that they have been reset.
      $connection->query('UPDATE cms_content_sync_entity_status SET flags=flags|:flag WHERE last_export IS NOT NULL', [
        ':flag' => EntityStatus::FLAG_LAST_EXPORT_RESET,
      ]);
      $connection->query('UPDATE cms_content_sync_entity_status SET flags=flags|:flag WHERE last_import IS NOT NULL', [
        ':flag' => EntityStatus::FLAG_LAST_IMPORT_RESET,
      ]);

      // Actual reset.
      $db_query = $connection->update($status_storage->getBaseTable());
      $db_query->fields([
        'last_export' => NULL,
        'last_import' => NULL,
      ]);
      $db_query->execute();
    }

    // Invalidate cache by storage.
    $status_storage->resetCache();

    // Above cache clearing doesn't work reliably. So we reset the whole entity cache.
    \Drupal::service('cache.entity')->deleteAll();
  }

  /**
   * Create a pool configuration programmatically.
   *
   * @param $pool_name
   * @param string $pool_id
   * @param $backend_url
   * @param $authentication_type
   * @param $site_id
   */
  public static function createPool($pool_name, $pool_id = '', $backend_url, $authentication_type, $site_id) {

    // If no pool_id is given, create one.
    if (empty($pool_id)) {
      $pool_id = strtolower($pool_name);
      $pool_id = preg_replace('@[^a-z0-9_]+@', '_', $pool_id);
    }

    $pools = Pool::getAll();
    if (array_key_exists($pool_id, $pools)) {
      drupal_set_message('A pool with the machine name ' . $pool_id . ' does already exist. Therefor the creation has been skipped.', 'warning');
    }
    else {
      $uuid_service = \Drupal::service('uuid');
      $language_manager = \Drupal::service('language_manager');
      $default_language = $language_manager->getDefaultLanguage();

      $pool_config = \Drupal::service('config.factory')->getEditable('cms_content_sync.pool.' . $pool_id);
      $pool_config
        ->set('uuid', $uuid_service->generate())
        ->set('langcode', $default_language->getId())
        ->set('status', TRUE)
        ->set('id', $pool_id)
        ->set('label', $pool_name)
        ->set('backend_url', $backend_url)
        ->set('authentication_type', $authentication_type)
        ->set('site_id', $site_id)
        ->save();
    }

    return $pool_id;
  }

}
