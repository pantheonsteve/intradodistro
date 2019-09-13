<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class ConnectionStorage
 * Implement Storage for the Sync Core "Connection" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class ConnectionStorage extends Storage {
  const ID = 'api_unify-api_unify-connection-0_1';

  /**
   * @var string POOL_DEPENDENCY_CONNECTION_ID
   *   Same as {@see Flow::DEPENDENCY_CONNECTION_ID} but for the
   *   pool connection.
   */
  const POOL_DEPENDENCY_CONNECTION_ID = 'drupal-[api.name]-' . InstanceStorage::POOL_SITE_ID . '-[entity_type.name_space]-[entity_type.name]';

  /**
   * @var string DEPENDENCY_CONNECTION_ID
   *   The format for connection IDs. Must be used consequently to allow
   *   references to be resolved correctly.
   */
  const DEPENDENCY_CONNECTION_ID = 'drupal-[api.name]-[instance.id]-[entity_type.name_space]-[entity_type.name]';

  /**
   * Get the Sync Core connection ID for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $site_id
   *   ID from this site from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   *
   * @return string A unique connection ID.
   */
  public static function getExternalConnectionId($api_id, $site_id, $entity_type_name, $bundle_name) {
    return sprintf('drupal-%s-%s-%s-%s',
      $api_id,
      $site_id,
      $entity_type_name,
      $bundle_name
    );
  }

  /**
   * Get the Sync Core connection path for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $site_id
   *   ID from this site from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   *
   * @return string A unique connection path.
   */
  public static function getExternalConnectionPath($api_id, $site_id, $entity_type_name, $bundle_name) {
    return sprintf('drupal/%s/%s/%s/%s',
      $api_id,
      $site_id,
      $entity_type_name,
      $bundle_name
    );
  }

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
