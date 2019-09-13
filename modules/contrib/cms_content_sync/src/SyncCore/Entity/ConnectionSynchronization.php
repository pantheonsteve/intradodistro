<?php

namespace Drupal\cms_content_sync\SyncCore\Entity;

use Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization\SynchronizeSingleAction;

/**
 *
 */
class ConnectionSynchronization extends Entity {

  /**
   * Create and return an instance of an SynchronizeSingleAction.
   *
   * @return \Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization\SynchronizeSingleAction
   */
  public function synchronizeSingle() {
    $action = new SynchronizeSingleAction($this->storage);
    $action->setEntityId($this->id);

    return $action;
  }

}
