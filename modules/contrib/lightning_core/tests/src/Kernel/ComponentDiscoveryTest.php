<?php

namespace Drupal\Tests\lightning_core\Kernel;

use Drupal\Core\Extension\Extension;
use Drupal\KernelTests\KernelTestBase;
use Drupal\lightning_core\ComponentDiscovery;

/**
 * @group lightning
 * @group lightning_core
 *
 * @coversDefaultClass \Drupal\lightning_core\ComponentDiscovery
 */
class ComponentDiscoveryTest extends KernelTestBase {

  /**
   * The ComponentDiscovery under test.
   *
   * @var \Drupal\lightning\ComponentDiscovery
   */
  protected $discovery;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->discovery = new ComponentDiscovery(
      $this->container->get('app.root')
    );
  }

  /**
   * @covers ::getAll
   */
  public function testGetAll() {
    $components = $this->discovery->getAll();

    $this->assertInstanceOf(Extension::class, $components['lightning_core']);
    $this->assertInstanceOf(Extension::class, $components['lightning_search']);
    $this->assertArrayNotHasKey('panels', $components);
    $this->assertArrayNotHasKey('views', $components);
  }

  /**
   * @covers ::getMainComponents
   */
  public function testGetMainComponents() {
    $components = $this->discovery->getMainComponents();

    $this->assertInstanceOf(Extension::class, $components['lightning_core']);

    $this->assertArrayNotHasKey('lightning_contact_form', $components);
    $this->assertArrayNotHasKey('lightning_page', $components);
    $this->assertArrayNotHasKey('lightning_roles', $components);
    $this->assertArrayNotHasKey('lightning_search', $components);
  }

  /**
   * @covers ::getSubComponents
   */
  public function testGetSubComponents() {
    $components = $this->discovery->getSubComponents();

    $this->assertInstanceOf(Extension::class, $components['lightning_contact_form']);
    $this->assertInstanceOf(Extension::class, $components['lightning_page']);
    $this->assertInstanceOf(Extension::class, $components['lightning_roles']);
    $this->assertInstanceOf(Extension::class, $components['lightning_search']);
    $this->assertArrayNotHasKey('lightning_core', $components);
  }

}
