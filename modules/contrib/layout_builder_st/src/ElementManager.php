<?php

namespace Drupal\layout_builder_st;

use Drupal\Core\Render\ElementInfoManager;
use Drupal\layout_builder_st\Element\LayoutBuilder;

/**
 * ElementManager extended to alter LayoutBuilder Element.
 *
 * @todo Remove if https://www.drupal.org/node/2987208 is fixed.
 */
final class ElementManager extends ElementInfoManager {

  /**
   * {@inheritdoc}
   */
  protected function alterDefinitions(&$definitions) {
    parent::alterDefinitions($definitions);
    // Replace LayoutBuilder element class.
    if (isset($definitions['layout_builder'])) {
      $definitions['layout_builder']['class'] = LayoutBuilder::class;
      $definitions['layout_builder']['provider'] = 'layout_builder_st';
    }
  }

}
