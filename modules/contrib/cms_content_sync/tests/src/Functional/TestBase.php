<?php

namespace Drupal\Tests\cms_content_sync\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Base class for CMS Content Sync functional tests.
 */
abstract class TestBase extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = [
    'cms_content_sync',
    'block',
  ];

  /**
   *
   */
  protected function setUp() {
    parent::setUp();
    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');
    $web_user = $this->drupalCreateUser(['administer site configuration', 'administer cms content sync']);
    $this->drupalLogin($web_user);
  }

}
