<?php

namespace Drupal\bootstrap_simple_carousel\Form;

use Drupal\bootstrap_simple_carousel\Service\CarouselService;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class EditForm.
 *
 * Add/edit form.
 *
 * @package Drupal\bootstrap_simple_carousel\Form
 */
class EditForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_simple_carousel_edit_form';
  }

  /**
   * Constructs a \Drupal\system\ConfigFormBase object.
   *
   * @param \Drupal\Core\Database\Connection $connection
   *   Constructs a Connection object.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(Connection $connection, EntityTypeManagerInterface $entity_type_manager) {
    $this->connection = $connection;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('database'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $id = NULL) {

    $result = NULL;
    if (!empty($id)) {
      $result = $this->connection->select('bootstrap_simple_carousel', 'c')
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
    }

    if (!empty($result->image_id)) {
      $form['image_preview'] = [
        '#markup' => CarouselService::getInstance()
          ->renderImageById($result->image_id),
        '#suffix' => '<br><b>NOTE: You can\'t change image, just remove\\set inactive the item and create new one</b>',
      ];
    }
    else {
      $form['image'] = [
        '#type' => 'managed_file',
        '#title' => $this->t('Image'),
        '#upload_validators' => [
          'file_validate_extensions' => ['gif png jpg jpeg'],
          'file_validate_size' => [25600000],
        ],
        '#upload_location' => 'public://bootstrap_simple_carousel/',
        '#required' => !isset($result->image_id),
        '#default_value' => !empty($result->image_id) ? $result->image_id : '',
      ];
    }

    $form['image_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image title'),
      '#required' => FALSE,
      '#default_value' => !empty($result->image_title) ? $result->image_title : '',
    ];

    $form['image_alt'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Image alt'),
      '#required' => FALSE,
      '#default_value' => !empty($result->image_alt) ? $result->image_alt : '',
    ];

    $form['caption_title'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Caption title'),
      '#required' => FALSE,
      '#default_value' => !empty($result->caption_title) ? $result->caption_title : '',
    ];

    $form['caption_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Caption text'),
      '#required' => FALSE,
      '#default_value' => !empty($result->caption_text) ? $result->caption_text : '',
    ];
    $form['status'] = [
      '#type' => 'select',
      '#title' => ('Status'),
      '#options' => CarouselService::getInstance()->getStatuses(),
      '#default_value' => !empty($result->status) ? $result->status : '',
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#button_type' => 'primary',
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    if (!in_array($form_state->getValue('status'), [0, 1])) {
      $form_state->setErrorByName('status', $this->t('Status is incorrect.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $fields = [
      'image_title' => $form_state->getValue('image_title'),
      'image_alt' => $form_state->getValue('image_alt'),
      'caption_title' => $form_state->getValue('caption_title'),
      'caption_text' => $form_state->getValue('caption_text'),
      'status' => $form_state->getValue('status'),
    ];

    if (!empty($form_state->getValue('image'))) {
      $file = $this->entityTypeManager->getStorage('file')->load(current($form_state->getValue('image')));
      $file->setPermanent();
      $file->save();
      $fields['image_id'] = current($form_state->getValue('image'));
    }

    if (!empty($form_state->getValue('cid'))) {
      $query = $this->connection->update('bootstrap_simple_carousel')
        ->condition('cid', $form_state->getValue('cid'));
    }
    else {
      $query = $this->connection->insert('bootstrap_simple_carousel');
    }
    $result = $query
      ->fields($fields)
      ->execute();

    $message = !empty($result) ? $this->t('Item successfully saved!') : $this->t('Record was not saved!');
    drupal_set_message($message);

    $form_state->setRedirect('bootstrap_simple_carousel.table');
  }

}
