<?php

namespace Drupal\Tests\cms_content_sync\Functional;

/**
 * Tests the pool overview page.
 *
 * @group cms_content_sync
 */
class PoolOverview extends TestBase {

  /**
   *
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Ensure the pool overview page is reachable.
   */
  public function testPoolOverview() {
    $this->drupalGet('admin/config/services/cms_content_sync/pool');

    // Test that the pool overview is reachable.
    $this->assertSession()->statusCodeEquals(200);

    // Test that the "Add pool" button exists.
    $this->assertSession()->linkExists('Add pool');

    // Test that the a message is shown that no pools are available for now.
    $this->assertSession()->pageTextContains('There is no Pool yet.');
  }

}
