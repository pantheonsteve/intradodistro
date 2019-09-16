<?php

namespace Drupal\Tests\lightning_landing_page\Functional;

use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_library\Entity\Layout;
use Drupal\node\Entity\Node;
use Drupal\Tests\BrowserTestBase;

/**
 * @group lightning_layout
 * @group lightning_landing_page
 */
class InstallTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_landing_page',
    'metatag',
  ];

  /**
   * Tests that Layout Builder overrides are enabled in the full node view mode.
   */
  public function testInstall() {
    $node = Node::create([
      'type' => 'landing_page',
    ]);
    $this->assertTrue($node->hasField(OverridesSectionStorage::FIELD_NAME));

    $account = $this->drupalCreateUser([
      'create landing_page content',
    ]);
    $this->drupalLogin($account);
    $this->drupalGet('/node/add/landing_page');
    $this->assertSession()->statusCodeEquals(200);
    // The Layout select should not be displayed because there is no Layout
    // for Landing pages.
    $this->assertSession()->fieldNotExists('Layout');

    // Assert that meta tag fields are present.
    $meta_tags = $this->getSession()
      ->getPage()
      ->findAll('css', '[name^="field_meta_tags[0]["]');
    $this->assertGreaterThan(0, count($meta_tags));

    // Add a Layout for Landing pages and assert the Layout select is there.
    Layout::create([
      'id' => 'test_layout',
      'label' => 'Test Layout',
      'targetEntityType' => 'node',
      'targetBundle' => 'landing_page',
    ])->save();
    $this->getSession()->reload();
    $this->assertSession()->optionExists('Layout', 'Test Layout');
  }

}
