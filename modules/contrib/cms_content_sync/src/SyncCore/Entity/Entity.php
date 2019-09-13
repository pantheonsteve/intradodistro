<?php

namespace Drupal\cms_content_sync\SyncCore\Entity;

use Drupal\cms_content_sync\SyncCore\Storage\Storage;

/**
 *
 */
class Entity {
  /**
   * @var \Drupal\cms_content_sync\SyncCore\Storage\Storage
   */
  protected $storage;
  /**
   * @var string
   */
  protected $id;

  /**
   * Entity constructor.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   * @param string $id
   */
  public function __construct($storage, $id) {
    $this->storage = $storage;
    $this->id = $id;
  }

  /**
   * @return \Drupal\cms_content_sync\SyncCore\Storage\Storage
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * @return string
   */
  public function getId() {
    return $this->id;
  }

}
