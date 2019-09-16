<?php

namespace Drupal\Tests\lightning_landing_page\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning_layout
 * @group lightning_landing_page
 */
class PathautoPatternTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_landing_page'];

  /**
   * Tests that landing pages are available at path '/[node:title]'.
   */
  public function testLandingPagePattern() {
    // Install Pathauto so that the optional config which integrates landing
    // pages with it will be picked up.
    $this->container->get('module_installer')->install(['pathauto']);

    $node = $this->drupalCreateNode([
      'type' => 'landing_page',
    ]);
    $this->assertSame(SAVED_UPDATED, $node->setTitle('Foo Bar')->setPublished()->save());

    $this->drupalGet('/foo-bar');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains($node->getTitle());
  }

}
