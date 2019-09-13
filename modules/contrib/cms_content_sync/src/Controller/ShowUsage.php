<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManager;

/**
 * Push changes controller.
 */
class ShowUsage extends ControllerBase {

  /**
   * @param $entity
   *
   * @param $entity_type
   *
   * @return array The content array to theme the introduction.
   */
  public function content($entity, $entity_type) {
    $entity_object = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity);
    return [
      '#usage' => _cms_content_sync_display_pool_usage($entity_object),
      '#theme' => 'cms_content_sync_show_usage',
    ];
  }

}
