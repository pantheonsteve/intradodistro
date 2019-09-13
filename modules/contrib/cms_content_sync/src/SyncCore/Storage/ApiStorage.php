<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class ApiStorage
 * Implement Storage for the Sync Core "API" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class ApiStorage extends Storage {
  const ID = 'api_unify-api_unify-api-0_1';

  /**
   * @var string CUSTOM_API_VERSION
   *   The API version used to identify APIs as. Breaking changes in
   *   Flow will require this version to be increased and all
   *   synchronization entities to be re-saved via update hook.
   */
  const CUSTOM_API_VERSION = '1.0';

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
