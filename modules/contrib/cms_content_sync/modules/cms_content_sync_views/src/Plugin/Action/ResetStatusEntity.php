<?php

namespace Drupal\cms_content_sync_views\Plugin\Action;

use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Reset status entity.
 *
 * @Action(
 *   id = "reset_status_entity",
 *   label = @Translation("Reset Status"),
 *   type = "cms_content_sync_entity_status"
 * )
 */
class ResetStatusEntity extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\cms_content_sync\Entity\EntityStatus $entity */
    if (!is_null($entity)) {
      $entity->resetStatus();
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

}
