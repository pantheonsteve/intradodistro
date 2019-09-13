<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\cms_content_sync\SyncIntent;

/**
 * Providing an implementation for the "path" field type of content entities.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_path_handler",
 *   label = @Translation("Default Path"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultPathHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    return $field->getType() == "path";
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    $entity = $intent->getEntity();

    if ($this->settings['export'] != ExportIntent::EXPORT_AUTOMATICALLY) {
      return FALSE;
    }

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $value = $entity->get($this->fieldName)->getValue();

    // Support the pathauto module.
    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('pathauto')) {
      $value[0]['pathauto'] = $entity->path->pathauto;
    }

    if (!empty($value)) {
      unset($value[0]['pid']);
      unset($value[0]['source']);
      $intent->setField($this->fieldName, $value);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    return parent::import($intent);
  }

}
