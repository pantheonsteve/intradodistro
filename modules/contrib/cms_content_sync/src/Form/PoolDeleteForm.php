<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\Core\Entity\EntityConfirmFormBase;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Builds the form to delete an Pool.
 */
class PoolDeleteForm extends EntityConfirmFormBase {

  /**
   * @inheritdoc
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $flows = [];

    foreach (Flow::getAll(FALSE) as $flow) {
      if ($flow->usesPool($this->entity)) {
        $flows[] = $flow->name;
      }
    }

    if (count($flows)) {
      $form_state->setError($form,
        \t(
          'You can\'t delete a pool that is used in a Flow. Please remove it from the following Flows first: %flows',
          ['%flows' => implode(' ', $flows)]
        )
      );
    }

    return parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Are you sure you want to delete %name?', ['%name' => $this->entity->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.cms_content_sync_pool.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Delete config related status entities.
    $entity_status = \Drupal::entityTypeManager()->getStorage('cms_content_sync_entity_status')
      ->loadByProperties(['pool' => $this->getEntity()->id()]);

    foreach ($entity_status as $status) {
      $entity = EntityStatus::load($status->id());
      $entity->delete();
    }

    $this->entity->delete();
    drupal_set_message($this->t('Pool %label has been deleted.', ['%label' => $this->entity->label()]));

    $form_state->setRedirectUrl($this->getCancelUrl());
  }

}
