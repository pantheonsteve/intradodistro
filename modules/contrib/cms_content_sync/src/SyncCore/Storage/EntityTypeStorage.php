<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class EntityTypeStorage
 * Implement Storage for the Sync Core "Entity Type" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class EntityTypeStorage extends Storage {
  const ID = 'api_unify-api_unify-entity_type-0_1';

  /**
   * Get the Sync Core entity type ID for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $entity_type_name
   *   The entity type.
   * @param string $bundle_name
   *   The bundle.
   * @param string $version
   *   The version. {@see Flow::getEntityTypeVersion}.
   *
   * @return string A unique entity type ID.
   */
  public static function getExternalEntityTypeId($api_id, $entity_type_name, $bundle_name, $version) {
    return sprintf('drupal-%s-%s-%s-%s',
      $api_id,
      $entity_type_name,
      $bundle_name,
      $version
    );
  }

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
