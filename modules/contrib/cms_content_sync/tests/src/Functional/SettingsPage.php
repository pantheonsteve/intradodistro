<?php

namespace Drupal\Tests\cms_content_sync\Functional;

/**
 * Tests the settings page.
 *
 * @group cms_content_sync
 */
class SettingsPage extends TestBase {

  /**
   *
   */
  protected function setUp() {
    parent::setUp();
  }

  /**
   * Ensure the pool overview page is reachable.
   */
  public function testSettingsPage() {
    $page = $this->getSession()->getPage();
    $this->drupalGet('admin/config/services/cms_content_sync/settings');

    // Test that the settings page is reachable.
    $this->assertSession()->statusCodeEquals(200);

    // Test that the Base URL can be set.
    $test_cms_content_sync_base_url = 'http://cms_content_sync-base-url.com';
    $page->fillField('Base URL', $test_cms_content_sync_base_url);
    $page->pressButton('Save configuration');
    $this->drupalGet('admin/config/services/cms_content_sync/settings');
    $this->assertSession()->fieldValueEquals('edit-cms_content_sync-base-url', $test_cms_content_sync_base_url);
  }

}
