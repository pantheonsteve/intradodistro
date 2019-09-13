<?php

namespace Drupal\webhooks\Event;

/**
 * Class WebhookEvents.
 *
 * @package Drupal\webhooks\Event
 */
final class WebhookEvents {

  /**
   * Name of the event fired when a webhook is sent.
   *
   * @Event
   */
  const SEND = 'webhook.send';

  /**
   * Name of the event fired when a webhook is received.
   *
   * @Event
   */
  const RECEIVE = 'webhook.receive';

}
