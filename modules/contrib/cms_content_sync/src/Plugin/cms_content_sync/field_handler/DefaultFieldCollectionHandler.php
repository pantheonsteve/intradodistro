<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_field_collection_handler",
 *   label = @Translation("Default Field Collection"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultFieldCollectionHandler extends DefaultEntityReferenceHandler {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    if (!in_array($field->getType(), ["field_collection"])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  protected function forceReferencedEntityEmbedding() {
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  protected function getReferencedEntityType() {
    return 'field_collection_item';
  }

  /**
   * @var \Drupal\cms_content_sync\Plugin\FieldHandlerInterface
   */
  public static $currentFieldHandler;

  /**
   * @var \Drupal\cms_content_sync\ImportIntent
   */
  public static $currentImportIntent;

  /**
   * @inheritdoc
   */
  protected function loadReferencedEntity(ImportIntent $intent, $definition) {
    $previousFieldHandler = self::$currentFieldHandler;
    $previousImportIntent = self::$currentImportIntent;

    // Expose current field and intent (to reference host entity)
    // As field collections require this when being created.
    self::$currentFieldHandler = $this;
    self::$currentImportIntent = $intent;

    $entity = parent::loadReferencedEntity($intent, $definition);

    self::$currentFieldHandler = $previousFieldHandler;
    self::$currentImportIntent = $previousImportIntent;

    return $entity;
  }

  /**
   * {@inheritdoc}
   */
  protected function loadReferencedEntityFromFieldValue($value) {
    if (empty($value['revision_id'])) {
      return NULL;
    }

    $field_collection_item = \Drupal::entityTypeManager()->getStorage('field_collection_item')->loadRevision($value['revision_id']);

    return $field_collection_item;
  }

  /**
   *
   */
  protected function getInvalidExportSubfields() {
    return ['_accessCacheability', '_attributes', '_loaded', 'top', 'target_revision_id', 'subform', 'value', 'revision_id'];
  }

  /**
   * @param $reference
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @return array
   */
  protected function getFieldValuesForReference($reference, $intent) {
    $entity = $intent->getEntity();

    $reference->host_type = $entity->getEntityTypeId();
    $reference->host_id = $entity->id();
    $reference->host_entity = $entity;
    $reference->field_name = $this->fieldName;

    $reference->save(TRUE);

    return [
      'value' => $reference->id(),
      'revision_id' => $reference->getRevisionId(),
    ];
  }

}
