<?php

namespace Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization;

use Drupal\cms_content_sync\SyncCore\Action\SubItemAction;
use Drupal\cms_content_sync\SyncCore\Client;
use Drupal\cms_content_sync\SyncCore\Storage\Storage;

/**
 * Class CloneAction.
 * Trigger synchronization for a specific entity.
 *
 * @package Drupal\cms_content_sync\SyncCore\Action\ConnectionSynchronization
 */
class SynchronizeSingleAction extends SubItemAction {

  /**
   * CloneAction constructor.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   */
  public function __construct(Storage $storage) {
    parent::__construct($storage, 'clone', Client::METHOD_POST);
  }

  /**
   * @param bool $clone
   * @return $this
   */
  public function isClone($clone) {
    $this->arguments['no_synchronization'] = $clone;

    return $this;
  }

  /**
   * @param bool $manual
   * @return $this
   */
  public function isManual($manual) {
    $this->arguments['manual'] = $manual;

    return $this;
  }

  /**
   * @param bool $dependency
   * @return $this
   */
  public function isDependency($dependency) {
    $this->arguments['dependency'] = $dependency;

    return $this;
  }

  /**
   * @inheritdoc
   */
  public function returnBoolean() {
    return TRUE;
  }

}
