<?php

namespace Drupal\webhooks\Event;

use Drupal\webhooks\Entity\WebhookConfig;
use Drupal\webhooks\Webhook;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class SendEvent.
 *
 * @package Drupal\webhooks\Event
 */
class SendEvent extends Event {

  /**
   * The webhook.
   *
   * @var \Drupal\webhooks\Webhook
   */
  protected $webhook;

  /**
   * The webhook configuration.
   *
   * @var \Drupal\webhooks\Entity\WebhookConfig
   */
  protected $webhookConfig;

  /**
   * SendEvent constructor.
   *
   * @param \Drupal\webhooks\Entity\WebhookConfig $webhook_config
   *   A webhook configuration entity.
   * @param \Drupal\webhooks\Webhook $webhook
   *   A webhook.
   */
  public function __construct(
      WebhookConfig $webhook_config,
      Webhook $webhook
  ) {
    $this->webhook = $webhook;
    $this->webhookConfig = $webhook_config;
  }

  /**
   * Get the webhooks.
   *
   * @return \Drupal\webhooks\Webhook
   *   A webhook.
   */
  public function getWebhook() {
    return $this->webhook;
  }

  /**
   * Get the webhook configuration.
   *
   * @return \Drupal\webhooks\Entity\WebhookConfig
   *   A webhook configuration.
   */
  public function getWebhookConfig() {
    return $this->webhookConfig;
  }

}
