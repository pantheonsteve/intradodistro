<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class RemoteStorageStorage
 * Implement Storage for the Sync Core "Remote Storage" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class RemoteStorageStorage extends Storage {
  const ID = 'api_unify-api_unify-remote_storage-0_1';

  /**
   * Get the Sync Core connection ID for the given entity type config.
   *
   * @param string $api_id
   *   API ID from this config.
   * @param string $site_id
   *   ID from this site from this config.
   *
   * @return string A unique connection ID.
   */
  public static function getStorageId($api_id, $site_id) {
    return sprintf('drupal-%s-%s',
      $api_id,
      $site_id
    );
  }

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
