<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the functionality of the layout_selection field.
 *
 * @group layout_library
 */
class LayoutSelectionTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['node', 'layout_library', 'options'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->drupalCreateContentType(['type' => 'alpha']);
    $this->drupalCreateContentType(['type' => 'beta']);

    $this->drupalPlaceBlock('local_tasks_block');
    $this->drupalPlaceBlock('local_actions_block');
  }

  /**
   * Tests that reference-able layouts are filtered by target bundle.
   */
  public function testReferenceableLayoutsFilteredByTargetBundle() {
    $assert = $this->assertSession();

    $account = $this->drupalCreateUser([
      'configure any layout',
      'create alpha content',
      'create beta content',
      'administer node display',
    ]);
    $this->drupalLogin($account);

    $this->createLayoutForNodeType('alpha');
    $this->createLayoutForNodeType('beta');

    $this->enableLayoutBuilderForNodeType('alpha');
    $this->enableLayoutBuilderForNodeType('beta');

    $this->drupalGet('/node/add/alpha');
    $assert->optionExists('Layout', 'alpha');
    $assert->optionNotExists('Layout', 'beta');

    $this->drupalGet('/node/add/beta');
    $assert->optionNotExists('Layout', 'alpha');
    $assert->optionExists('Layout', 'beta');
  }

  /**
   * Creates a stored layout for a node type.
   *
   * @param string $node_type
   *   The node type ID.
   */
  private function createLayoutForNodeType($node_type) {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $this->drupalGet('admin/structure/layouts');
    $page->clickLink('Add layout');
    $page->fillField('label', $node_type);
    $page->fillField('id', $node_type);
    $page->selectFieldOption('_entity_type', "node:$node_type");
    $page->pressButton('Save');
    $assert->pageTextContains("Edit layout for $node_type");
  }

  /**
   * Enables Layout Builder for the default display of a node type.
   *
   * @param string $node_type
   *   The node type ID.
   */
  private function enableLayoutBuilderForNodeType($node_type) {
    $page = $this->getSession()->getPage();
    $assert = $this->assertSession();

    $this->drupalGet("/admin/structure/types/manage/$node_type/display");
    $page->checkField('layout[enabled]');
    $page->checkField('layout[library]');
    $page->pressButton('Save');
    $assert->pageTextContains('Your settings have been saved.');
  }

}
