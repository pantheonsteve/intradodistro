<?php

namespace Drupal\Tests\lightning_core\Kernel\Update;

use Drupal\field\Entity\FieldConfig;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\Update\Update360;

/**
 * @group lightning_core
 *
 * @covers \Drupal\lightning_core\Update\Update360
 */
class Update360Test extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'lightning_core',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installConfig('user');
    $this->installEntitySchema('user');
  }

  public function test() {
    $this->assertFalse($this->container->get('module_handler')->moduleExists('image'));
    $this->assertNull(FieldConfig::loadByName('user', 'user', 'user_picture'));
    $this->assertTrue(lightning_core_entity_get_display('user', 'user', 'compact')->isNew());

    $this->container->get('class_resolver')
      ->getInstanceFromDefinition(Update360::class)
      ->enableUserPictures();

    $this->assertTrue($this->container->get('module_handler')->moduleExists('image'));
    $this->assertInstanceOf(FieldConfig::class, FieldConfig::loadByName('user', 'user', 'user_picture'));

    $display = lightning_core_entity_get_display('user', 'user', 'compact');
    $this->assertFalse($display->isNew());
    $this->assertInternalType('array', $display->getComponent('name'));
    $this->assertInternalType('array', $display->getComponent('user_picture'));
  }

}
