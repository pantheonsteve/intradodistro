<?php

namespace Drupal\Tests\lightning_core;

use Drupal\block\Entity\Block;
use Drupal\node\Entity\NodeType;
use Drupal\search_api\Entity\Index;
use Drupal\user\Entity\Role;

/**
 * Performs set-up and tear-down tasks before and after test scenarios.
 */
final class FixtureContext extends FixtureBase {

  /**
   * @BeforeScenario
   */
  public function setUp() {
    // Create the administrator role if it does not already exist.
    if (!Role::load('administrator')) {
      $role = Role::create([
        'id' => 'administrator',
        'label' => 'Administrator',
      ])->setIsAdmin(TRUE);

      $this->save($role);
    }

    // Install the Seven theme if not already installed.
    $this->installTheme('seven');

    // Use Seven as both the default and administrative theme.
    $this->config('system.theme')
      ->set('admin', 'seven')
      ->set('default', 'seven')
      ->save();

    // Place the main content block if it's not already there.
    if (!Block::load('seven_content')) {
      $block = Block::create([
        'id' => 'seven_content',
        'theme' => 'seven',
        'region' => 'content',
        'plugin' => 'system_main_block',
        'settings' => [
          'label_display' => '0',
        ],
      ]);
      $this->save($block);
    }

    // Create a test content type to be automatically cleaned up at the end of
    // the scenario.
    $node_type = NodeType::create([
      'type' => 'test',
      'name' => 'Test',
    ]);
    $this->save($node_type);

    $this->installModule('views');

    if ($this->installModule('lightning_search')) {
      /** @var \Drupal\search_api\IndexInterface $index */
      $index = Index::load('content');
      $dependencies = $index->getDependencies();
      $dependencies['enforced']['module'][] = 'lightning_search';
      $index->set('dependencies', $dependencies)->save();
    }

    /** @var \Drupal\block\BlockInterface $block */
    if (!Block::load('seven_search')) {
      $block = Block::create([
        'id' => 'seven_search',
        'theme' => 'seven',
        'region' => 'content',
        'plugin' => 'views_exposed_filter_block:search-page',
      ])
        ->setVisibilityConfig('request_path', [
          'pages' => '/search',
        ]);
      $this->save($block);
    }

    $this->config('views.view.search')
      ->set('display.default.display_options.cache', [
        'type' => 'none',
        'options' => [],
      ])
      ->save();
  }

  /**
   * @AfterScenario
   */
  public function tearDown() {
    // This pointless if statement is here to evade a too-strict coding
    // standards rule.
    if (TRUE) {
      parent::tearDown();
    }
  }

}
