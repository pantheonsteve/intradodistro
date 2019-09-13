<?php

namespace Drupal\webhooks\Exception;

/**
 * Class WebhookMismatchSignatureException.
 *
 * @package Drupal\webhooks\Exception
 */
class WebhookMismatchSignatureException extends \Exception {

  /**
   * MismatchSignatureException constructor.
   *
   * @param string $signature_received
   *   The received signature.
   * @param string $signature_generated
   *   The generated signature.
   */
  public function __construct($signature_received, $signature_generated) {
    $message = sprintf(
      'The received signature %s does not match the generated signature %s.',
      $signature_received,
      $signature_generated
    );
    parent::__construct($message);
  }

}
