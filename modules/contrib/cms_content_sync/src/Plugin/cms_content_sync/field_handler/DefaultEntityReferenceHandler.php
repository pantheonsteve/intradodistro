<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\Plugin\EntityReferenceHandlerBase;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_entity_reference_handler",
 *   label = @Translation("Default Entity Reference"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultEntityReferenceHandler extends EntityReferenceHandlerBase {

  const SUPPORTED_CONFIG_ENTITY_TYPES = [
    'block',
    'view',
  ];

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    if (!in_array($field->getType(), ["entity_reference", "entity_reference_revisions"])) {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    if (in_array($type, ['user', 'brick_type', 'paragraph', 'menu_link_content'])) {
      return FALSE;
    }

    // Limit support for supported config entity types.
    $field_storage = $field->getFieldStorageDefinition();
    $target = $field_storage->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getEntityTypeId();
    $referenced_entity_type = \Drupal::entityTypeManager()->getStorage($target);
    if ($referenced_entity_type instanceof ConfigEntityStorage && !in_array($type, self::SUPPORTED_CONFIG_ENTITY_TYPES)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @return bool
   */
  protected function allowSubscribeFilter() {
    $type = $this->fieldDefinition->getSetting('target_type');
    return $type == 'taxonomy_term';
  }

  /**
   * Get a list of array keys from $entity->field_* values that should be
   * ignored (unset before export).
   *
   * @return array
   */
  protected function getInvalidExportSubfields() {
    return ['_accessCacheability', '_attributes', '_loaded', 'top', 'target_revision_id', 'subform'];
  }

  /**
   * Save the export settings the user selected for paragraphs.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param null $parent_entity
   * @param array $tree_position
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public static function saveEmbeddedExportPools(EntityInterface $entity, $parent_entity = NULL, $tree_position = []) {
    // Make sure paragraph export settings are saved as well..
    $entityTypeManager = \Drupal::entityTypeManager();
    $entityFieldManager = \Drupal::service('entity_field.manager');
    /** @var \Drupal\Core\Field\FieldDefinitionInterface[] $fields */
    $fields = $entityFieldManager->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());
    foreach ($fields as $name => $definition) {
      if ($definition->getType() == 'entity_reference_revisions') {
        $reference_type = $definition
          ->getFieldStorageDefinition()
          ->getPropertyDefinition('entity')
          ->getTargetDefinition()
          ->getEntityTypeId();
        $storage = $entityTypeManager
          ->getStorage($reference_type);

        $data = $entity->get($name)->getValue();
        foreach ($data as $delta => $value) {
          if (empty($value['target_id'])) {
            continue;
          }

          $target_id = $value['target_id'];
          $reference = $storage
            ->load($target_id);

          if (!$reference) {
            continue;
          }

          // In case the values are still present, favor those.
          if (!empty($value['subform']['cms_content_sync_export_group'])) {
            $set = $value['subform']['cms_content_sync_export_group'];
            EntityStatus::accessTemporaryExportPoolInfoForField($entity->getEntityTypeId(), $entity->uuid(), $name, $delta, $tree_position, $set['cms_content_sync_flow'], $set['cms_content_sync_pool'], !empty($set['cms_content_sync_uuid']) ? $set['cms_content_sync_uuid'] : NULL);
          }

          EntityStatus::saveSelectedExportPoolInfoForField($entity, $name, $delta, $reference, $tree_position);

          self::saveEmbeddedExportPools($reference, $entity, array_merge($tree_position, [$name, $delta, 'subform']));
        }
      }
    }
  }

}
