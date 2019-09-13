<?php

namespace Drupal\webhooks\Tests;

use Drupal\simpletest\WebTestBase;

/**
 * Provides automated tests for the webhooks module.
 */
class WebhookTest extends WebTestBase {

  /**
   * {@inheritdoc}
   */
  public static function getInfo() {
    return array(
      'name' => "webhooks Webhook's controller functionality",
      'description' => 'Test Unit for module webhooks and controller Webhook.',
      'group' => 'Other',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();
  }

  /**
   * Tests webhooks functionality.
   */
  public function testWebhook() {
    // Check that the basic functions of module webhooks.
    $this->assertEquals(TRUE, TRUE, 'Test Unit Generated via Drupal Console.');
  }

}
