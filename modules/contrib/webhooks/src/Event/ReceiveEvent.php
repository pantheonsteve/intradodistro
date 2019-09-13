<?php

namespace Drupal\webhooks\Event;

use Drupal\webhooks\Webhook;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class ReceiveEvent.
 *
 * @package Drupal\webhooks\Event
 */
class ReceiveEvent extends Event {

  /**
   * The webhook.
   *
   * @var \Drupal\webhooks\Webhook
   */
  protected $webhook;

  /**
   * ReceiveEvent constructor.
   *
   * @param \Drupal\webhooks\Webhook $webhook
   *   A webhook.
   */
  public function __construct(Webhook $webhook) {
    $this->webhook = $webhook;
  }

  /**
   * Get the webhook.
   *
   * @return \Drupal\webhooks\Webhook
   *   The webhook.
   */
  public function getWebhook() {
    return $this->webhook;
  }

}
