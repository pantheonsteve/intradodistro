<?php

namespace Drupal\bootstrap_simple_carousel\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\ConfirmFormHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\file\Entity\File;

/**
 * Class DeleteForm.
 *
 * Delete item form.
 *
 * @package Drupal\bootstrap_simple_carousel\Form
 */
class DeleteForm extends ConfirmFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_simple_carousel_edit_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Are you sure you want to delete the item?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('bootstrap_simple_carousel.table');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

    $result = NULL;
    if (!empty($id)) {
      $result = \Drupal::database()->select('bootstrap_simple_carousel', 'c')
        ->fields('c')
        ->where('cid = :cid', ['cid' => $id])
        ->execute()
        ->fetchObject();
    }

    if (!empty($result->cid)) {
      $form['cid'] = [
        '#type' => 'hidden',
        '#required' => FALSE,
        '#default_value' => $result->cid,
      ];

      $form['image'] = [
        '#type' => 'hidden',
        '#required' => FALSE,
        '#default_value' => $result->image_id,
      ];

      $form['actions']['#type'] = 'actions';
      $form['actions']['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Delete'),
        '#button_type' => 'primary',
      ];

      $form['actions']['cancel'] = ConfirmFormHelper::buildCancelLink($this, $this->getRequest());
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    if (!empty($form_state->getValue('cid'))) {
      $file = File::load($form_state->getValue('image'));
      $file->setTemporary();
      $file->save();

      $query = \Drupal::database()->delete('bootstrap_simple_carousel');
      $query->condition('cid', $form_state->getValue('cid'));
      $result = $query->execute();
    }

    $message = !empty($result) ? t('Item has been removed!') : t('Item was not removed!');
    drupal_set_message($message);

    $form_state->setRedirect('bootstrap_simple_carousel.table');
  }

}
