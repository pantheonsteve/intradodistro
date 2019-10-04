<?php

namespace Drupal\bootstrap_ce\Plugin\Field\FieldFormatter;

use Drupal\bootstrap_ce\Plugin\Field\FieldFormatter\BootstrapCEFormatterTrait;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\EntityReferenceEntityFormatter;

/**
 * Plugin implementation of the 'Bootstrap Carousel entities and medias' formatter.
 *
 * @FieldFormatter(
 *   id = "bootstrap_ce_entity",
 *   label = @Translation("Boostrap Carousel"),
 *   field_types = {
 *     "entity_reference",
 *   }
 * )
 */
class BootstrapCEEntityFormatter extends EntityReferenceEntityFormatter {

  use BootstrapCEFormatterTrait;
  
}
