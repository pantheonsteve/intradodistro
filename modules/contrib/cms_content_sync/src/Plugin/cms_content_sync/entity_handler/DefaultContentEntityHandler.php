<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\cms_content_sync\Plugin\EntityHandlerBase;

/**
 * Class DefaultContentEntityHandler, providing a minimalistic implementation
 * for any content entity type.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_entity_handler",
 *   label = @Translation("Default Content"),
 *   weight = 100
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultContentEntityHandler extends EntityHandlerBase {

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    // Whitelist supported entity types.
    $entity_types = [
      'block_content',
      'config_pages',
      'paragraph',
      'bricks',
    ];

    $moduleHandler = \Drupal::service('module_handler');
    $eck_exists = $moduleHandler->moduleExists('eck');
    if ($eck_exists) {
      $eck_entity_type = \Drupal::entityTypeManager()->getStorage('eck_entity_type')->load($entity_type);

      if (!empty($eck_entity_type)) {
        return TRUE;
      }
    }

    return in_array($entity_type, $entity_types);
  }

  /**
   * Check whether the entity type supports having a label.
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function hasLabelProperty() {
    $moduleHandler = \Drupal::service('module_handler');
    $eck_exists = $moduleHandler->moduleExists('eck');
    if ($eck_exists) {
      $entity_type = \Drupal::entityTypeManager()->getStorage('eck_entity_type')->load($this->entityTypeName);

      if ($entity_type) {
        return $entity_type->hasTitleField();
      }
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

}
