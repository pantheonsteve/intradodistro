<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Pull controller.
 */
class SetFlowStatus extends ControllerBase {

  /**
   * Set flow status.
   */
  public function setStatus($cms_content_sync_flow) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->load($cms_content_sync_flow);

    if ($flow->status()) {
      $flow->set('status', FALSE);
      drupal_set_message($this->t('The flow @flow_name has been disabled.', ['@flow_name' => $flow->label()]));
    }
    else {
      $flow->set('status', TRUE);
      drupal_set_message($this->t('The flow @flow_name has been enabled.', ['@flow_name' => $flow->label()]));
    }
    $flow->save();

    return new RedirectResponse(Url::fromRoute('entity.cms_content_sync_flow.collection')->toString());
  }

}
