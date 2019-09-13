<?php

namespace Drupal\webhooks\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;
use Drupal\Core\Entity\EntityStorageInterface;

/**
 * Defines the Webhook entity.
 *
 * @ConfigEntityType(
 *   id = "webhook_config",
 *   label = @Translation("Webhook"),
 *   handlers = {
 *     "list_builder" = "Drupal\webhooks\WebhookConfigListBuilder",
 *     "form" = {
 *       "add" = "Drupal\webhooks\Form\WebhookConfigForm",
 *       "edit" = "Drupal\webhooks\Form\WebhookConfigForm",
 *       "delete" = "Drupal\webhooks\Form\WebhookConfigDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\webhooks\WebhookConfigHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "webhook",
 *   admin_permission = "administer webhooks",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "uuid" = "uuid"
 *   },
 *   links = {
 *     "canonical" = "/admin/config/services/webhook/{webhook_config}",
 *     "add-form" = "/admin/config/services/webhook/add",
 *     "edit-form" = "/admin/config/services/webhook/{webhook_config}/edit",
 *     "delete-form" = "/admin/config/services/webhook/{webhook_config}/delete",
 *     "collection" = "/admin/config/services/webhook"
 *   }
 * )
 */
class WebhookConfig extends ConfigEntityBase implements WebhookConfigInterface {

  /**
   * The Webhook ID.
   *
   * @var string
   */
  protected $id;

  /**
   * The Webhook label.
   *
   * @var string
   */
  protected $label;

  /**
   * The Webhook Payload URL.
   *
   * @var string
   */
  protected $payload_url;

  /**
   * The Webhook type.
   *
   * @var string
   */
  protected $type;

  /**
   * The Webhook events.
   *
   * @var array
   */
  protected $events;

  /**
   * The Webhook content type.
   *
   * @var string
   */
  protected $content_type;

  /**
   * The Webhook last usage.
   *
   * @var integer
   */
  protected $last_usage;

  /**
   * The Webhook response_ok.
   *
   * @var bool
   */
  protected $response_ok;

  /**
   * The Webhook reference entity type.
   *
   * @var string
   */
  protected $ref_entity_type;

  /**
   * The Webhook reference entity id.
   *
   * @var string
   */
  protected $ref_entity_id;

  /**
   * The Webhook secret.
   *
   * @var string
   */
  protected $secret;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $values, $entity_type) {
    parent::__construct($values, $entity_type);

    if (isset($values['events']) && is_string($values['events'])) {
      $this->events = unserialize($values['events']);
    }
  }

  /**
   * Get the webhook id.
   *
   * @return string
   *   The webhooks identifier string.
   */
  public function getId() {
    return $this->id;
  }

  /**
   * Get the webhook label.
   *
   * @return string
   *   The webhook label.
   */
  public function getLabel() {
    return $this->label;
  }

  /**
   * Get the payload URL.
   *
   * @return string
   *   The payload URL.
   */
  public function getPayloadUrl() {
    return $this->payload_url;
  }

  /**
   * Get the type.
   *
   * @return string
   *   The webhook type.
   */
  public function getType() {
    return $this->type;
  }

  /**
   * Get the events listening on.
   *
   * @return string
   *   The events listening on.
   */
  public function getEvents() {
    return $this->events;
  }

  /**
   * Get the content type.
   *
   * @return string
   *   The content type string, e.g. json, xml.
   */
  public function getContentType() {
    return $this->content_type;
  }

  /**
   * Get last usage time.
   *
   * @return int
   *   The last usage time.
   */
  public function getLastUsage() {
    return $this->last_usage;
  }

  /**
   * Check if last response was ok.
   *
   * @return bool
   *   A bool true if last response was ok, false otherwise.
   */
  public function hasResponseOk() {
    return $this->response_ok;
  }

  /**
   * Get referenced entity type.
   *
   * @return string
   *   The referenced entity type.
   */
  public function getRefEntityType() {
    return $this->ref_entity_type;
  }

  /**
   * Get referenced entity id.
   *
   * @return string
   *   Get referenced entity id.
   */
  public function getRefEntityId() {
    return $this->ref_entity_id;
  }

  /**
   * Get secret.
   *
   * @return string
   *   The secret string.
   */
  public function getSecret() {
    return $this->secret;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave(EntityStorageInterface $storage) {
    parent::preSave($storage);
    $this->events = serialize($this->events);
  }

}
