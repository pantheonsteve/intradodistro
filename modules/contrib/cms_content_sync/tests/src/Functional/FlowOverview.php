<?php

namespace Drupal\Tests\cms_content_sync\Functional;

/**
 * Tests the flow overview page.
 *
 * @group cms_content_sync
 */
class FlowOverview extends TestBase {

  /**
   *
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Ensure the flow overview page is reachable.
   */
  public function testFlowOverview() {
    $this->drupalGet('admin/config/services/cms_content_sync/flow');

    // Test that the flow overview is reachable.
    $this->assertSession()->statusCodeEquals(200);

    // Test that the "Add flow" button exists.
    $this->assertSession()->linkExists('Add flow');

    // Test that the a message is shown that no flows are available for now.
    $this->assertSession()->pageTextContains('There is no Flow yet.');
  }

}
