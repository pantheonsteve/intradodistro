<?php

namespace Drupal\webhooks\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class WebhookConfigForm.
 *
 * @package Drupal\webhooks\Form
 */
class WebhookConfigForm extends EntityForm {

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    $form = parent::form($form, $form_state);

    /** @var \Drupal\webhooks\Entity\WebhookConfig $webhook_config */
    $webhook_config = $this->entity;
    $form['label'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#maxlength' => 255,
      '#default_value' => $webhook_config->label(),
      '#description' => $this->t("Label for the Webhook."),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $webhook_config->id(),
      '#machine_name' => array(
        'exists' => '\Drupal\webhooks\Entity\WebhookConfig::load',
      ),
      '#disabled' => !$webhook_config->isNew(),
    );
    $form['type'] = array(
      '#type' => 'select',
      '#title' => $this->t('Type'),
      '#options' => [
        'incoming' => $this->t('Incoming'),
        'outgoing' => $this->t('Outgoing'),
      ],
      '#default_value' => $webhook_config->getType() ? $webhook_config->getType() : 'outgoing',
      '#description' => $this->t("The webhook type, e.g. incoming or outgoing."),
      '#required' => TRUE,
    );
    $form['payload_url'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Payload URL'),
      '#attributes' => array(
        'placeholder' => $this->t('http://example.com/post'),
      ),
      '#default_value' => $webhook_config->getPayloadUrl(),
      '#maxlength' => 255,
      '#description' => $this->t("Target URL for your payload."),
      '#required' => TRUE,
    );
    $form['secret'] = array(
      '#type' => 'password',
      '#attributes' => array(
        'placeholder' => $this->t('Secret'),
      ),
      '#title' => $this->t('Secret'),
      '#maxlength' => 255,
      '#description' => $this->t("Secret that the target website gave you."),
      '#default_value' => $webhook_config->getSecret(),
    );
    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t("Active"),
      '#description' => $this->t("Shows if the webhook is active or not."),
      '#default_value' => $webhook_config->isNew() ? TRUE : $webhook_config->status(),
    );
    $form['content_type'] = array(
      '#type' => 'select',
      '#title' => $this->t("Content Type"),
      '#description' => $this->t("The Content Type of your webhook."),
      '#options' => [
        'json' => $this->t('application/json'),
      ],
      '#default_value' => $webhook_config->getContentType(),
    );
    $form['events'] = array(
      '#type' => 'tableselect',
      '#header' => array('type' => 'Entity Type' , 'event' => 'Event'),
      '#description' => $this->t("The Events you want to send to the endpoint."),
      '#options' => [
        'entity:user:create' => ['type' => 'User' , 'event' => 'Create'],
        'entity:user:update' => ['type' => 'User' , 'event' => 'Update'],
        'entity:user:delete' => ['type' => 'User' , 'event' => 'Delete'],
        'entity:node:create' => ['type' => 'Node' , 'event' => 'Create'],
        'entity:node:update' => ['type' => 'Node' , 'event' => 'Update'],
        'entity:node:delete' => ['type' => 'Node' , 'event' => 'Delete'],
        'entity:comment:create' => ['type' => 'Comment' , 'event' => 'Create'],
        'entity:comment:update' => ['type' => 'Comment' , 'event' => 'Update'],
        'entity:comment:delete' => ['type' => 'Comment' , 'event' => 'Delete'],
      ],
    );
    $form['events']['#default_value'] = $webhook_config->isNew() ? [] : $webhook_config->getEvents();

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\webhooks\Entity\WebhookConfig $webhook_config */
    $webhook_config = $this->entity;
    // Keep the old secret if no new one has been given.
    if (empty($form_state->getValue('secret'))) {
      $webhook_config->set('secret', $form['secret']['#default_value']);
    }
    $active = $webhook_config->save();

    switch ($active) {
      case SAVED_NEW:
        drupal_set_message($this->t('Created the %label Webhook.', [
          '%label' => $webhook_config->label(),
        ]));
        break;

      default:
        drupal_set_message($this->t('Saved the %label Webhook.', [
          '%label' => $webhook_config->label(),
        ]));
    }
    /** @var \Drupal\Core\Url $url */
    $url = $webhook_config->urlInfo('collection');
    $form_state->setRedirectUrl($url);
  }

}
