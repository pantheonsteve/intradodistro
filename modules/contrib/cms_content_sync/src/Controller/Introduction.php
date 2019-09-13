<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\Core\Controller\ControllerBase;

/**
 * Class Introduction provides a static page describing how
 * CMS Content Sync can be used.
 */
class Introduction extends ControllerBase {

  /**
   * @return array The content array to theme the introduction.
   */
  public function content() {
    $supported_entity_types = [];

    $entity_types = \Drupal::service('entity_type.bundle.info')->getAllBundleInfo();
    ksort($entity_types);
    foreach ($entity_types as $type_key => $entity_type) {
      if (substr($type_key, 0, 16) == 'cms_content_sync') {
        continue;
      }
      ksort($entity_type);

      foreach ($entity_type as $entity_bundle_name => $entity_bundle) {
        $supported_entity_types[] = EntityHandlerPluginManager::getEntityTypeInfo($type_key, $entity_bundle_name);
      }
    }

    return [
      '#supported_entity_types' => $supported_entity_types,
      '#theme' => 'cms_content_sync_introduction',
    ];
  }

}
