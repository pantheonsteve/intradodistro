<?php

namespace Drupal\cms_content_sync\SyncCore\Action;

/**
 * Class ItemAction
 * Execute an action for a specific entity.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class SubItemAction extends ItemAction {

  protected $itemId = NULL;

  /**
   * @inheritdoc
   */
  public function getPath() {
    return parent::getPath() . '/' . $this->itemId;
  }

  /**
   * Set the item ID for this action.
   *
   * @param string $itemId
   *
   * @return $this
   */
  public function setItemId($itemId) {
    $this->itemId = $itemId;

    return $this;
  }

}
