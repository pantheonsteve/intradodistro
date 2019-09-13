<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class ItemResult
 * Get an individual item by ID.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class ItemResult extends Result {

  /**
   * @var array
   */
  protected $result;

  /**
   * Execute the query and store the result.
   *
   * @return $this
   *
   * @throws \Exception
   */
  public function execute() {
    $client = $this->query->getStorage()->getPool()->getClient();

    $this->result = $client->request($this->query);

    return $this;
  }

  /**
   * @return bool The result.
   */
  public function succeeded() {
    return !!$this->result;
  }

  /**
   * @return array The entity data.
   */
  public function getItem() {
    return $this->result;
  }

}
