<?php

namespace Drupal\bootstrap_ce\Plugin\Field\FieldFormatter;

use Drupal\bootstrap_ce\Plugin\Field\FieldFormatter\BootstrapCEFormatterTrait;
use Drupal\image\Plugin\Field\FieldFormatter\ImageFormatter;

/**
 * Plugin implementation of the 'Bootstrap Carousel entities and medias' formatter.
 *
 * @FieldFormatter(
 *   id = "bootstrap_ce_image",
 *   label = @Translation("Boostrap Carousel"),
 *   field_types = {
 *     "image",
 *   }
 * )
 */
class BootstrapCEImageFormatter extends ImageFormatter {

   use BootstrapCEFormatterTrait;
}
