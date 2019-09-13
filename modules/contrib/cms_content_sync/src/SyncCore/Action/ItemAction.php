<?php

namespace Drupal\cms_content_sync\SyncCore\Action;

use Drupal\cms_content_sync\SyncCore\ItemQuery;
use Drupal\cms_content_sync\SyncCore\Storage\Storage;

/**
 * Class ItemAction
 * Execute an action for a specific entity.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class ItemAction extends ItemQuery {

  protected $actionPath = NULL;
  protected $method = NULL;

  /**
   * ItemAction constructor.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   * @param string $actionPath
   * @param string $method
   */
  public function __construct(Storage $storage, $actionPath, $method) {
    parent::__construct($storage);

    $this->actionPath = $actionPath;
    $this->method = $method;
  }

  /**
   * @inheritdoc
   */
  public function getPath() {
    return parent::getPath() . '/' . $this->actionPath;
  }

  /**
   * @inheritdoc
   */
  public function getMethod() {
    return $this->method;
  }

}
