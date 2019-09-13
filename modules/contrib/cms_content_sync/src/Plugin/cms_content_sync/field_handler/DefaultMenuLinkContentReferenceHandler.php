<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Reference menu references and make sure they're published as the content
 * comes available.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_menu_link_content_reference_handler",
 *   label = @Translation("Default Menu Link Content Reference"),
 *   weight = 80
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultMenuLinkContentReferenceHandler extends DefaultEntityReferenceHandler {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    return $entity_type == 'menu_link_content' && $field_name == 'parent';
  }

  /**
   * @inheritdoc
   */
  protected function forceReferencedEntityExport() {
    return TRUE;
  }

  /**
   * @inheritdoc
   */
  protected function loadReferencedEntityFromFieldValue($value) {
    if (empty($value) || empty($value['value'])) {
      return NULL;
    }

    list($entity_type, $uuid) = explode(':', $value['value']);
    if ($entity_type != 'menu_link_content' || empty($uuid)) {
      return NULL;
    }

    $entity = \Drupal::service('entity.repository')->loadEntityByUuid(
      'menu_link_content',
      $uuid
    );

    return $entity;
  }

  /**
   * @inheritdoc
   */
  protected function getFieldValuesForReference($reference, $intent) {
    return 'menu_link_content:' . $reference->uuid();
  }

  /**
   * @inheritdoc
   */
  protected function getReferencedEntityType() {
    return 'menu_link_content';
  }

}
