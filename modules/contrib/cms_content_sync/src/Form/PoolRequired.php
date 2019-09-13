<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Provides a node deletion confirmation form.
 *
 * @internal
 */
class PoolRequired extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'cms_content_sync_pool_required';
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return $this->t('This will redirect you to the pool creation page.');
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return $this->t('Before you can create a flow, you have to create at least one pool before. Do you want to create a pool now?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('entity.cms_content_sync_flow.collection');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return $this->t('Create pool');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirect('entity.cms_content_sync_pool.add_form');
  }

}
