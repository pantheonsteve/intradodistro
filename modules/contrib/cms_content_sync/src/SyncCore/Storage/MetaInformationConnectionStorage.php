<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class MetaInformationConnectionStorage
 * Implement Storage for the Sync Core "Meta Information per Connection" entity
 * type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class MetaInformationConnectionStorage extends Storage {
  const ID = 'api_unify-api_unify-entity_meta_information_connection-0_1';

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
