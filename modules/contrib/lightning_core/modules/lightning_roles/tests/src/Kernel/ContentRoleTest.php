<?php

namespace Drupal\Tests\lightning_roles\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\NodeType;
use Drupal\user\Entity\Role;

/**
 * @group lightning
 * @group lightning_roles
 * @group orca_public
 */
class ContentRoleTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'lightning_roles',
    'node',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('lightning_roles');
    $this->installEntitySchema('user');
  }

  public function test() {
    $node_type = NodeType::create([
      'type' => $this->randomMachineName(),
    ]);
    $node_type->save();

    $role_ids = [
      $node_type->id() . '_creator',
      $node_type->id() . '_reviewer',
    ];
    $roles = Role::loadMultiple($role_ids);
    $this->assertCount(2, $roles);

    foreach ($roles as $role) {
      $this->assertSame(FALSE, $role->get('is_admin'));
    }

    $node_type->delete();
    $this->assertEmpty(Role::loadMultiple($role_ids));
  }

}
