<?php

namespace Drupal\layout_builder_st\DependencyInjection;

use Drupal\Core\DependencyInjection\ClassResolver as CoreClassResolver;
use Drupal\layout_builder\InlineBlockEntityOperations as CoreInlineBlockEntityOperations;
use Drupal\layout_builder_st\InlineBlockEntityOperations;

/**
 * ClassResolver to load the extended InlineBlockEntityOperations class.
 */
final class ClassResolver extends CoreClassResolver {

  public function getInstanceFromDefinition($definition) {
    if ($definition === CoreInlineBlockEntityOperations::class) {
      $definition = InlineBlockEntityOperations::class;
    }
    return parent::getInstanceFromDefinition($definition);
  }


}
