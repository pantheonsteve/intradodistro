<?php

namespace Drupal\schemata;

use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\DependencyInjection\ServiceModifierInterface;
use Drupal\Core\StackMiddleware\NegotiationMiddleware;

/**
 * Adds schema_json as known format.
 */
class SchemataServiceProvider implements ServiceModifierInterface {

  /**
   * {@inheritdoc}
   */
  public function alter(ContainerBuilder $container) {
    if ($container->has('http_middleware.negotiation') && is_a($container->getDefinition('http_middleware.negotiation')
      ->getClass(), NegotiationMiddleware::class, TRUE)
    ) {
      // @see https://www.ietf.org/id/draft-wright-json-schema-00.txt
      $container->getDefinition('http_middleware.negotiation')
        ->addMethodCall('registerFormat', [
          'schema_json',
          ['application/schema+json'],
        ]);
    }
  }

}
