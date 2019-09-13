<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\Plugin\EntityReferenceHandlerBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Implements webform references.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_webform_handler",
 *   label = @Translation("Default Webform"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultWebformHandler extends EntityReferenceHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    if (!in_array($field->getType(), ["webform"])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Don't expose option, but force export.
   *
   * @return bool
   */
  protected function forceReferencedEntityExport() {
    return FALSE;
  }

  /**
   * @return bool
   */
  protected function allowExportReferencedEntities() {
    return TRUE;
  }

  /**
   * Don't expose option, but force export.
   *
   * @return bool
   */
  protected function forceReferencedEntityEmbedding() {
    return FALSE;
  }

  /**
   * @return string
   */
  protected function getReferencedEntityType() {
    return 'webform';
  }

  /**
   * Get the values to be set to the $entity->field_*.
   *
   * @param $reference
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @return array
   */
  protected function getFieldValuesForReference($reference, $intent) {
    return [
      'target_id' => $reference->id(),
    ];
  }

  /**
   * @param \Drupal\cms_content_sync\ExportIntent $intent
   * @param \Drupal\Core\Entity\EntityInterface $reference
   * @param $value
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function serializeReference(ExportIntent $intent, EntityInterface $reference, $value) {
    if ($this->shouldEmbedReferencedEntities()) {
      return $intent->embedEntity($reference, SyncIntent::ENTITY_REFERENCE_EMBED, $value);
    }
    elseif ($this->shouldExportReferencedEntities()) {
      return $intent->embedEntity($reference, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $value);
    }
    else {
      return $intent->embedEntityDefinition(
        $reference->getEntityTypeId(),
        $reference->bundle(),
        $reference->uuid(),
        $reference->id(),
        SyncIntent::ENTITY_REFERENCE_RESOLVE_IF_EXISTS,
        $value
      );
    }
  }

}
