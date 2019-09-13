<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class EntityTypeStorage
 * Implement Storage for the actual entities being sync'd.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class EntityStorage extends Storage {
  protected $entityType;
  protected $bundle;
  protected $version;

  /**
   * @inheritdoc
   */
  public function getId() {
    return ConnectionStorage::getExternalConnectionId($this->pool->id, $this->pool->getSiteId(), $this->entityType, $this->bundle);
  }

}
