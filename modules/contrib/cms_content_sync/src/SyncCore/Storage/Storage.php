<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

use Drupal\cms_content_sync\SyncCore\ItemQuery;
use Drupal\cms_content_sync\SyncCore\ListQuery;

/**
 * Class Storage
 * A remote entity type storage for the Sync Core. Provides Query objects for
 * the given pool and the given entity type. One Storage class is available per
 * Entity Type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
abstract class Storage {
  /**
   * @var \Drupal\cms_content_sync\Entity\Pool
   */
  protected $pool;

  /**
   * Storage constructor.
   *
   * @param \Drupal\cms_content_sync\Entity\Pool $pool
   */
  public function __construct($pool) {
    $this->pool = $pool;
  }

  /**
   * Get the entity type ID.
   *
   * @return string
   */
  abstract public function getId();

  /**
   * Get the path to append to the Pool::$backend_url to query entities of this
   * type.
   *
   * @return string
   */
  public function getPath() {
    return '/' . $this->getId();
  }

  /**
   * Get the pool that this Storage belongs to. Each Pool manages its own
   * storages per entity type.
   *
   * @return \Drupal\cms_content_sync\Entity\Pool
   */
  public function getPool() {
    return $this->pool;
  }

  /**
   * Create and return an instance of the ListQuery.
   *
   * @return \Drupal\cms_content_sync\SyncCore\ListQuery
   */
  public function createListQuery() {
    return new ListQuery($this);
  }

  /**
   * Create and return an instance of the ItemQuery.
   *
   * @return \Drupal\cms_content_sync\SyncCore\ItemQuery
   */
  public function createItemQuery() {
    return new ItemQuery($this);
  }

}
