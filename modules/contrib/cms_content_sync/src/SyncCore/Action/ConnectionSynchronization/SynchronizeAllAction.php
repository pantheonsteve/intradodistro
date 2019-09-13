<?php

namespace Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization;

use Drupal\cms_content_sync\SyncCore\Action\ItemAction;
use Drupal\cms_content_sync\SyncCore\Client;
use Drupal\cms_content_sync\SyncCore\Storage\Storage;

/**
 * Class CloneAction.
 * Trigger synchronization for a specific entity.
 *
 * @package Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization
 */
class SynchronizeAllAction extends ItemAction {

  /**
   * CloneAction constructor.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   */
  public function __construct(Storage $storage) {
    parent::__construct($storage, 'synchronize', Client::METHOD_POST);
  }

  /**
   * @inheritdoc
   */
  public function returnBoolean() {
    return TRUE;
  }

}
