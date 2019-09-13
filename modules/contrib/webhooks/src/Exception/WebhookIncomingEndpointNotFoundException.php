<?php

namespace Drupal\webhooks\Exception;

/**
 * Class WebhookIncomingEndpointNotFoundException.
 *
 * @package Drupal\webhooks\Exception
 */
class WebhookIncomingEndpointNotFoundException extends \Exception {

  /**
   * WebhookIncomingEndpointNotFoundException constructor.
   *
   * @param string $incoming_webhook_name
   *   The name of the incoming webhook.
   */
  public function __construct($incoming_webhook_name) {
    $message = sprintf(
      'No incoming webhook has been configured with the machine name "%s".',
      $incoming_webhook_name
    );
    parent::__construct($message);
  }

}
