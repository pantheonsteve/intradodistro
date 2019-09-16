<?php

namespace Drupal\Tests\entity_browser_block\FunctionalJavascript;

use Drupal\Tests\entity_browser\FunctionalJavascript\EntityBrowserJavascriptTestBase;

/**
 * Tests the functionality of the Entity Browser block.
 *
 * @group entity_browser_block
 */
class EntityBrowserBlockTest extends EntityBrowserJavascriptTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['entity_browser_block'];

  /**
   * {@inheritdoc}
   */
  protected static $userPermissions = [
    'access test_entity_browser_file entity browser pages',
    'create article content',
    'access content',
    'administer blocks',
  ];

  /**
   * Tests the entity browser block form.
   */
  public function testEntityBrowserBlock() {
    $image = $this->createFile('llama');
    $image2 = $this->createFile('alpaca');

    // Load the block form.
    $this->drupalGet('admin/structure/block/add/entity_browser_block:test_entity_browser_file');
    $this->assertSession()->pageTextContains('Test entity browser file');

    // Open the entity browser iframe.
    $this->assertSession()->linkExists('Select entities');
    $this->getSession()->getPage()->clickLink('Select entities');
    $this->getSession()->switchToIFrame('entity_browser_iframe_test_entity_browser_file');

    // Select both files.
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image->id() . ']');
    $this->getSession()->getPage()->checkField('entity_browser_select[file:' . $image2->id() . ']');
    $this->getSession()->getPage()->pressButton('Select entities');

    // Ensure both files are present in the form (table).
    $this->getSession()->switchToIFrame();
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextContains('llama.jpg');
    $this->assertSession()->pageTextContains('alpaca.jpg');

    // Remove the first file.
    $this->assertSession()->buttonExists('remove_file:1');
    $this->getSession()->getPage()->pressButton('remove_file:1');
    $this->waitForAjaxToFinish();
    $this->assertSession()->pageTextNotContains('llama.jpg');
    $this->assertSession()->pageTextContains('alpaca.jpg');

    // Save the block.
    $this->drupalPostForm(NULL, ['region' => 'content'], 'Save block');
    $this->assertSession()->pageTextContains('The block configuration has been saved.');

    // Edit the block and ensure the configuration persists.
    $this->getSession()->getPage()->clickLink('Configure');
    $this->assertSession()->pageTextContains('Test entity browser file');
    $this->assertSession()->pageTextNotContains('llama.jpg');
    $this->assertSession()->pageTextContains('alpaca.jpg');
  }

}
