<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler\DefaultFieldCollectionHandler;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;

/**
 * Class DefaultFieldCollectionItemHandler.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_field_collection_item_handler",
 *   label = @Translation("Default Field Collection Item"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultFieldCollectionItemHandler extends EntityHandlerBase {

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'field_collection_item';
  }

  /**
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      ExportIntent::EXPORT_DISABLED,
      ExportIntent::EXPORT_AS_DEPENDENCY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedImportOptions() {
    return [
      ImportIntent::IMPORT_DISABLED,
      ImportIntent::IMPORT_AS_DEPENDENCY,
    ];
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

  /**
   * @inheritdoc
   */
  public function getForbiddenFields() {
    $forbidden = parent::getForbiddenFields();
    $forbidden[] = 'host_type';
    return $forbidden;
  }

  /**
   * @inheritdoc
   */
  protected function createNew(ImportIntent $intent) {
    $entity = parent::createNew($intent);

    $parent = DefaultFieldCollectionHandler::$currentImportIntent->getEntity();

    // Respect nested entities.
    if ($parent->isNew()) {
      $parent->save();
    }

    /**
     * @var \Drupal\field_collection\Entity\FieldCollectionItem $entity
     */
    $entity->setHostEntity($parent);

    return $entity;
  }

  /**
   * @inheritdoc
   */
  protected function saveEntity($entity, $intent) {
    // Field collections are automatically saved when their host entity is saved.
  }

}
