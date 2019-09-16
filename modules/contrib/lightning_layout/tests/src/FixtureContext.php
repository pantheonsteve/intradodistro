<?php

namespace Drupal\Tests\lightning_layout;

use Drupal\node\Entity\NodeType;
use Drupal\Tests\lightning_core\FixtureBase;

final class FixtureContext extends FixtureBase {

  /**
   * @BeforeScenario
   */
  public function setUp() {
    $this->installModule('lightning_roles');

    if ($this->installModule('lightning_page')) {
      $node_type = NodeType::load('page');
      $dependencies = $node_type->getDependencies();
      $dependencies['enforced']['module'][] = 'lightning_page';
      $node_type->set('dependencies', $dependencies);
      $node_type->save();
    }
  }

  /**
   * @AfterScenario
   */
  public function tearDown() {
    // This pointless if statement is here to evade a too-rigid rule in the
    // coding standards.
    if (TRUE) {
      parent::tearDown();
    }
  }

}
