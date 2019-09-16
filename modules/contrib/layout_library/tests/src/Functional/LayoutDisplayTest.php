<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Test using a layout from the library on a specific entity view display.
 *
 * @group layout_library
 */
class LayoutDisplayTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['layout_library', 'block', 'node', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');
    $this->drupalPlaceBlock('local_tasks_block');

    $this->createContentType([
      'type' => 'cats',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'create cats content',
      'edit own cats content',
      'administer node display',
    ]));
  }

  /**
   * Tests using the layout on a specific view display.
   */
  public function testUseLayoutOnDifferentViewDisplay() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $node = $this->createNode(['type' => 'cats']);

    // Add a layout to the library.
    $this->drupalGet('admin/structure/layouts');
    $this->clickLink('Add layout');
    $page->fillField('label', 'Lion');
    $page->fillField('id', 'lion');
    $page->selectFieldOption('_entity_type', 'node:cats');
    $page->pressButton('Save');
    $assert_session->pageTextContains('Edit layout for Lion');

    $page->clickLink('Add section');
    $page->clickLink('One column');
    $page->clickLink('Add block');
    $page->clickLink('Powered by Drupal');
    $page->fillField('settings[label]', 'This is from the library');
    $page->checkField('settings[label_display]');
    $page->pressButton('Add block');
    $page->pressButton('Save layout');

    // Enable full content display, then enable layout builder and library on
    // the default view display.
    $this->drupalGet('admin/structure/types/manage/cats/display');
    $page->checkField('display_modes_custom[full]');
    $page->pressButton('Save');
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());

    // Edit the page to use the layout from the library.
    $page->clickLink('Edit');
    $page->selectFieldOption('Layout', 'lion');
    $page->pressButton('Save');
    $assert_session->pageTextNotContains('This is from the library');

    // Disable the full content display
    $this->drupalGet('admin/structure/types/manage/cats/display');
    $page->uncheckField('display_modes_custom[full]');
    $page->pressButton('Save');

    $this->drupalGet($node->toUrl());
    $assert_session->pageTextContains('This is from the library');
  }
}
