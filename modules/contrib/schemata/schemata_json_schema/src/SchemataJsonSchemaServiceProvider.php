<?php

namespace Drupal\schemata_json_schema;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceProviderInterface;
use Symfony\Component\DependencyInjection\Reference;

/**
 * Provides schemata services that depend directly on HAL.
 */
class SchemataJsonSchemaServiceProvider implements ServiceProviderInterface {

  /**
   * {@inheritdoc}
   */
  public function register(ContainerBuilder $container) {
    $modules = $container->getParameter('container.modules');

    if (isset($modules['jsonapi'])) {
      // Define encoder for schema_json:jsonapi.
      $container->register('serializer.encoder.json_schema.jsonapi', 'Drupal\schemata_json_schema\Encoder\JsonSchemaEncoder')
        ->addArgument(new Reference('serializer.encoder.jsonapi'))
        ->addTag('encoder', [
          'priority' => 10,
          'format' => 'schema_json',
        ]);
    }
    if (isset($modules['hal'])) {
      // Provide the HAL+JSON version of the Data Reference normalizer here
      // because the hal.link_manager service argument requires HAL.
      $container->register('serializer.normalizer.data_reference_definition.schema_json.hal_json', 'Drupal\schemata_json_schema\Normalizer\hal\DataReferenceDefinitionNormalizer')
        ->addArgument(new Reference('entity_type.manager'))
        ->addArgument(new Reference('hal.link_manager'))
        ->addTag('normalizer', ['priority' => 30]);

      // Define encoder for schema_json:hal.
      $container->register('serializer.encoder.json_schema.hal_json', 'Drupal\schemata_json_schema\Encoder\JsonSchemaEncoder')
        ->addArgument(new Reference('serializer.encoder.hal'))
        ->addTag('encoder', [
          'priority' => 10,
          'format' => 'schema_json',
        ]);
    }

  }

}
