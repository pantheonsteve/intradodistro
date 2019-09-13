<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class ItemQuery
 * Get an individual item by ID.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class ItemQuery extends Query {

  /**
   * @var string
   */
  protected $entityId;

  /**
   * @param string $id
   *
   * @return $this
   */
  public function setEntityId($id) {
    $this->entityId = $id;

    return $this;
  }

  /**
   * @inheritdoc
   */
  public static function create($storage) {
    return new ItemQuery($storage);
  }

  /**
   * @inheritdoc
   */
  public function getPath() {
    return $this->storage->getPath() . '/' . $this->entityId;
  }

  /**
   * @return ItemResult
   * @throws \Exception
   */
  public function execute() {
    $result = new ItemResult($this);

    $result->execute();

    return $result;
  }

}
