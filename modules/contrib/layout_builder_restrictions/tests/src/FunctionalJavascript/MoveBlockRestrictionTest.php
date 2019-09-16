<?php

namespace Drupal\Tests\layout_builder_restrictions\FunctionalJavascript;

use Drupal\block_content\Entity\BlockContent;
use Drupal\block_content\Entity\BlockContentType;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\contextual\FunctionalJavascript\ContextualLinkClickTrait;

/**
 * Tests moving blocks via the form.
 *
 * @group layout_builder_restrictions
 */
class MoveBlockRestrictionTest extends WebDriverTestBase {

  use ContextualLinkClickTrait;

  /**
   * Path prefix for the field UI for the test bundle.
   *
   * @var string
   */
  const FIELD_UI_PREFIX = 'admin/structure/types/manage/bundle_with_section_field';

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'block_content',
    'contextual',
    'node',
    'layout_builder',
    'layout_builder_restrictions',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $this->createContentType(['type' => 'bundle_with_section_field']);

    $this->drupalLogin($this->drupalCreateUser([
      'configure any layout',
      'administer blocks',
      'administer node display',
      'administer node fields',
      'access contextual links',
      'create and edit custom blocks',
    ]));

    // Enable Layout Builder.
    $this->drupalPostForm(
      static::FIELD_UI_PREFIX . '/display/default',
      ['layout[enabled]' => TRUE],
      'Save'
    );
    $this->getSession()->resizeWindow(1200, 2000);
  }

  /**
   * Tests moving a plugin block.
   */
  public function testMovePluginBlock() {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $page->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');
    $expected_block_order = [
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(0, 'content', $expected_block_order);

    // Add a top section using the Two column layout.
    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add section"]'));
    $page->pressButton('Add section');
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order);
    // Add a 'Powered by Drupal' block in the 'first' region of the new section.
    $first_region_block_locator = '[data-layout-delta="0"].layout--twocol-section [data-region="first"] [data-layout-block-uuid]';
    $assert_session->elementNotExists('css', $first_region_block_locator);
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block')->click();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas a:contains("Powered by Drupal")'));
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Powered by Drupal');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add block"]'));
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add block');
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', $first_region_block_locator));

    // Ensure the request has completed before the test starts.
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();

    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Add a block restriction after the fact to test basic restriction.
    // Restrict all 'Content' fields from options.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-restricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-restricted"]');
    $element->click();
    $page->pressButton('Save');

    $page->clickLink('Manage layout');
    $expected_block_order_1 = [
      '.block-extra-field-blocknodebundle-with-section-fieldlinks',
      '.block-field-blocknodebundle-with-section-fieldbody',
    ];
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order_1);
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');

    // Attempt to reorder body field in current region.
    $this->openMoveForm(1, 'content', 'block-field-blocknodebundle-with-section-fieldbody', ['Links', 'Body (current)']);
    $this->moveBlockWithKeyboard('up', 'Body (current)', ['Body (current)*', 'Links']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Body placement in the layout_onecol content region for bundle_with_section_field content.", trim($modal->getText()));

    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    // The order should not have changed after save.
    $this->assertRegionBlocksOrder(1, 'content', $expected_block_order_1);

    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-restricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-content-fields-restriction-restricted"]');
    $element->click();
    $page->pressButton('Save');
    $this->assertSession()->assertWaitOnAjaxRequest();

    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default/layout");
    // Move the body block into the first region above existing block.
    $this->openMoveForm(1, 'content', 'block-field-blocknodebundle-with-section-fieldbody', ['Links', 'Body (current)']);
    $page->selectFieldOption('Region', '0:first');
    $this->assertBlockTable(['Powered by Drupal', 'Body (current)']);
    $this->moveBlockWithKeyboard('up', 'Body', ['Body (current)*', 'Powered by Drupal']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $modal = $page->find('css', '#drupal-off-canvas p');
    // Content cannot be moved between sections if a restriction exists.
    $this->assertSame("There is a restriction on Body placement in the layout_twocol_section first region for bundle_with_section_field content.", trim($modal->getText()));

  }

  /**
   * Tests moving a content block.
   */
  public function testMoveContentBlock() {
    // Create 2 custom block types, with 3 block instances.
    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
    ]);
    $bundle->save();
    $bundle = BlockContentType::create([
      'id' => 'alternate',
      'label' => 'Alternate',
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());
    // Add custom blocks.
    $blocks = [
      'Basic Block 1' => 'basic',
      'Basic Block 2' => 'basic',
      'Alternate Block 1' => 'alternate',
    ];
    foreach ($blocks as $info => $type) {
      $block = BlockContent::create([
        'info' => $info,
        'type' => $type,
        'body' => [
          [
            'value' => 'This is the block content',
            'format' => filter_default_format(),
          ],
        ],
      ]);
      $block->save();
      $blocks[$info] = $block->uuid();
    }
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();
    $page->clickLink('Manage layout');
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');

    // Add a top section using the Two column layout.
    $page->clickLink('Add section');
    $assert_session->waitForElementVisible('css', '#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Two column');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', 'input[value="Add section"]'));
    $page->pressButton('Add section');
    $assert_session->assertWaitOnAjaxRequest();

    // Add Basic Block 1 to the 'first' region.
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block')->click();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas a:contains("Basic Block 1")'));
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Basic Block 1');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add block');
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();

    // Add Alternate Block 1 to the 'first' region.
    $assert_session->elementExists('css', '[data-layout-delta="0"].layout--twocol-section [data-region="first"] .layout-builder__add-block')->click();
    $this->assertNotEmpty($assert_session->waitForElementVisible('css', '#drupal-off-canvas a:contains("Alternate Block 1")'));
    $assert_session->assertWaitOnAjaxRequest();
    $page->clickLink('Alternate Block 1');
    $assert_session->assertWaitOnAjaxRequest();
    $page->pressButton('Add block');
    $this->waitForNoElement('#drupal-off-canvas');
    $assert_session->assertWaitOnAjaxRequest();
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    // Restrict all Custom blocks.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $assert_session->checkboxChecked('edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all');
    $assert_session->checkboxNotChecked('edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-restricted');
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-restricted"]');
    $element->click();
    $page->pressButton('Save');

    $page->clickLink('Manage layout');
    $expected_block_order = [
      '.block-block-content' . $blocks['Basic Block 1'],
      '.block-block-content' . $blocks['Alternate Block 1'],
    ];
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');

    // Attempt to reorder Alternate Block 1.
    $this->openMoveForm(0, 'first', 'block-block-content' . $blocks['Alternate Block 1'], ['Basic Block 1', 'Alternate Block 1 (current)']);
    $this->moveBlockWithKeyboard('up', 'Alternate Block 1', ['Alternate Block 1 (current)*', 'Basic Block 1']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Alternate Block 1 placement in the layout_twocol_section first region for bundle_with_section_field content.", trim($modal->getText()));

    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();

    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    // The order should not have changed after save.
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);

    // Allow Basic Block, but not Alternate Block.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    // Do not apply individual block level restrictions.
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-restricted"]');
    $element->click();
    // Whitelist all "Alternate" block types.
    $page->checkField('layout_builder_restrictions[allowed_blocks][Custom block types][alternate]');
    $page->pressButton('Save');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Reorder Alternate block.
    $page->clickLink('Manage layout');
    $expected_block_order_moved = [
      '.block-block-content' . $blocks['Alternate Block 1'],
      '.block-block-content' . $blocks['Basic Block 1'],
    ];
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->openMoveForm(0, 'first', 'block-block-content' . $blocks['Alternate Block 1'], ['Basic Block 1', 'Alternate Block 1 (current)']);
    $this->moveBlockWithKeyboard('up', 'Alternate Block 1', ['Alternate Block 1 (current)*', 'Basic Block 1']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);

    // Demonstrate that Basic block types are still restricted.
    $this->openMoveForm(0, 'first', 'block-block-content' . $blocks['Basic Block 1'], ['Alternate Block 1', 'Basic Block 1 (current)']);
    $this->moveBlockWithKeyboard('up', 'Basic Block 1', ['Basic Block 1 (current)*', 'Alternate Block 1']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    // Verify that a validation error is provided.
    $modal = $page->find('css', '#drupal-off-canvas p');
    $this->assertSame("There is a restriction on Basic Block 1 placement in the layout_twocol_section first region for bundle_with_section_field content.", trim($modal->getText()));
    $dialog_div = $this->assertSession()->waitForElementVisible('css', 'div.ui-dialog');
    $close_button = $dialog_div->findButton('Close');
    $this->assertNotNull($close_button);
    $close_button->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');

    // Allow all Custom block types.
    $this->drupalGet(static::FIELD_UI_PREFIX . "/display/default");
    $element = $page->find('xpath', '//*[@id="edit-layout-layout-builder-restrictions-allowed-blocks"]/summary');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-block-types-restriction-all"]');
    $element->click();
    $element = $page->find('xpath', '//*[@id="edit-layout-builder-restrictions-allowed-blocks-custom-blocks-restriction-all"]');
    $element->click();
    $page->pressButton('Save');
    $this->assertSession()->assertWaitOnAjaxRequest();

    // Reorder both Alternate & Basic block block.
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);
    $assert_session->addressEquals(static::FIELD_UI_PREFIX . '/display/default/layout');
    $this->openMoveForm(0, 'first', 'block-block-content' . $blocks['Basic Block 1'], ['Alternate Block 1', 'Basic Block 1 (current)']);
    $this->moveBlockWithKeyboard('up', 'Basic Block 1', ['Basic Block 1 (current)*', 'Alternate Block 1']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    // Reorder Alternate block.
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order);
    $this->openMoveForm(0, 'first', 'block-block-content' . $blocks['Alternate Block 1'], ['Basic Block 1', 'Alternate Block 1 (current)']);
    $this->moveBlockWithKeyboard('up', 'Alternate Block 1', ['Alternate Block 1 (current)*', 'Basic Block 1']);
    $page->pressButton('Move');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $page->pressButton('Save layout');
    $page->clickLink('Manage layout');
    $this->assertRegionBlocksOrder(0, 'first', $expected_block_order_moved);

  }

  /**
   * Asserts the correct block labels appear in the draggable tables.
   *
   * @param string[] $expected_block_labels
   *   The expected block labels.
   */
  protected function assertBlockTable(array $expected_block_labels) {
    $page = $this->getSession()->getPage();
    $this->assertSession()->assertWaitOnAjaxRequest();
    $block_tds = $page->findAll('css', '.layout-builder-components-table__block-label');
    $this->assertCount(count($block_tds), $expected_block_labels);
    /** @var \Behat\Mink\Element\NodeElement $block_td */
    foreach ($block_tds as $block_td) {
      $this->assertSame(array_shift($expected_block_labels), trim($block_td->getText()));
    }
  }

  /**
   * Waits for an element to be removed from the page.
   *
   * @param string $selector
   *   CSS selector.
   * @param int $timeout
   *   (optional) Timeout in milliseconds, defaults to 10000.
   *
   * @todo Remove in https://www.drupal.org/node/2892440.
   */
  protected function waitForNoElement($selector, $timeout = 10000) {
    $condition = "(typeof jQuery !== 'undefined' && jQuery('$selector').length === 0)";
    $this->assertJsCondition($condition, $timeout);
  }

  /**
   * Moves a block in the draggable table.
   *
   * @param string $direction
   *   The direction to move the block in the table.
   * @param string $block_label
   *   The block label.
   * @param array $updated_blocks
   *   The updated blocks order.
   */
  protected function moveBlockWithKeyboard($direction, $block_label, array $updated_blocks) {
    $keys = [
      'up' => 38,
      'down' => 40,
    ];
    $key = $keys[$direction];
    $handle = $this->findRowHandle($block_label);

    $handle->keyDown($key);
    $handle->keyUp($key);

    $handle->blur();
    $this->assertBlockTable($updated_blocks);
  }

  /**
   * Finds the row handle for a block in the draggable table.
   *
   * @param string $block_label
   *   The block label.
   *
   * @return \Behat\Mink\Element\NodeElement
   *   The row handle element.
   */
  protected function findRowHandle($block_label) {
    $assert_session = $this->assertSession();
    return $assert_session->elementExists('css', "[data-drupal-selector=\"edit-components\"] td:contains(\"$block_label\") a.tabledrag-handle");
  }

  /**
   * Asserts that blocks are in the correct order for a region.
   *
   * @param int $section_delta
   *   The section delta.
   * @param string $region
   *   The region.
   * @param array $expected_block_selectors
   *   The block selectors.
   */
  protected function assertRegionBlocksOrder($section_delta, $region, array $expected_block_selectors) {
    $page = $this->getSession()->getPage();
    $assert_session = $this->assertSession();

    $assert_session->assertWaitOnAjaxRequest();
    $this->waitForNoElement('#drupal-off-canvas');

    $region_selector = "[data-layout-delta=\"$section_delta\"] [data-region=\"$region\"]";

    // Get all blocks currently in the region.
    $blocks = $page->findAll('css', "$region_selector [data-layout-block-uuid]");
    $this->assertCount(count($expected_block_selectors), $blocks);

    /** @var \Behat\Mink\Element\NodeElement $block */
    foreach ($blocks as $block) {
      $block_selector = array_shift($expected_block_selectors);
      $assert_session->elementsCount('css', "$region_selector $block_selector", 1);
      $expected_block = $page->find('css', "$region_selector $block_selector");
      $this->assertSame($expected_block->getAttribute('data-layout-block-uuid'), $block->getAttribute('data-layout-block-uuid'));
    }
  }

  /**
   * Open block for the body field.
   *
   * @param int $delta
   *   The section delta where the field should be.
   * @param string $region
   *   The region where the field should be.
   * @param string $field
   *   The field class that should be targeted.
   * @param array $initial_blocks
   *   The initial blocks that should be shown in the draggable table.
   */
  protected function openMoveForm($delta, $region, $field, array $initial_blocks) {
    $assert_session = $this->assertSession();
    $body_field_locator = "[data-layout-delta=\"$delta\"] [data-region=\"$region\"] ." . $field;
    $this->clickContextualLink($body_field_locator, 'Move');
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertNotEmpty($assert_session->waitForElementVisible('named', ['select', 'Region']));
    $assert_session->fieldValueEquals('Region', "$delta:$region");
    $this->assertBlockTable($initial_blocks);
  }

}
