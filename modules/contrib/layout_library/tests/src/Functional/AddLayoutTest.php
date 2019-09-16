<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\node\Traits\ContentTypeCreationTrait;

/**
 * Tests adding a layout to the library.
 *
 * @group layout_library
 */
class AddLayoutTest extends BrowserTestBase {

  use ContentTypeCreationTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['layout_library', 'block', 'node', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalPlaceBlock('local_actions_block');

    $this->createContentType([
      'type' => 'my_little_dinosaur',
      'name' => 'My Little Dinosaur',
    ]);
    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'create my_little_dinosaur content',
      'administer node display',
    ]));
  }

  /**
   * Tests adding a layout to the library.
   */
  public function testAddLayout() {
    $session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $this->drupalGet('admin/structure/layouts');
    $this->clickLink('Add layout');

    $page->fillField('label', 'Archaeopteryx');
    $page->fillField('id', 'archaeopteryx');
    $page->selectFieldOption('_entity_type', 'node:my_little_dinosaur');
    $page->pressButton('Save');

    $session->pageTextContains('Edit layout for Archaeopteryx');

    $this->drupalGet('admin/structure/types/manage/my_little_dinosaur/display');
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');
    $page->checkField('layout[allow_custom]');
    $page->pressButton('Save');

    $this->drupalGet('node/add/my_little_dinosaur');
    $session->optionExists('Layout', 'Archaeopteryx');

    $this->drupalGet('admin/structure/types/manage/my_little_dinosaur/display');
    $page->uncheckField('layout[allow_custom]');
    $page->uncheckField('layout[library]');
    $page->pressButton('Save');

    $this->drupalGet('node/add/my_little_dinosaur');
    $session->fieldNotExists('Layout');
  }

}
