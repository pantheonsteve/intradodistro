<?php

namespace Drupal\Tests\openapi\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\openapi_test\Entity\OpenApiTestEntityType;

/**
 * @coversDefaultClass \Drupal\openapi\Plugin\openapi\OpenApiGenerator\JsonApiGenerator
 * @group openapi
 */
class JsonApiGeneratorTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'jsonapi',
    'link',
    'menu_link_content',
    'menu_ui',
    'openapi',
    'openapi_test',
    'schemata',
    'schemata_json_schema',
    'serialization',
    'system',
    'user',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->installEntitySchema('openapi_test_entity');

    OpenApiTestEntityType::create([
      'id' => 'test',
      'label' => 'Test',
    ])->save();
  }

  /**
   * @covers ::getPaths
   */
  public function testGetPaths() {
    // Assert that the menu_link field is defined on the test entity type.
    $field_definitions = $this->container
      ->get('entity_field.manager')
      ->getFieldDefinitions('openapi_test_entity', 'test');

    $this->assertArrayHasKey('menu_link', $field_definitions);

    // This should not cause any failures.
    $this->container->get('plugin.manager.openapi.generator')
      ->createInstance('jsonapi')
      ->getPaths();
  }

}
