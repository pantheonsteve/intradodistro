<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\file\Entity\File;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_file_handler",
 *   label = @Translation("Default File"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultFileHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = ["image", "file_uri", "file"];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    if ($intent->shouldMergeChanges()) {
      return FALSE;
    }

    $data = $intent->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $file_ids = [];
      foreach ($data as $value) {
        $file = $intent->loadEmbeddedEntity($value);
        $meta = $intent->getEmbeddedEntityData($value);
        if ($file) {
          $meta['target_id'] = $file->id();
          $file_ids[] = $meta;
        }
      }

      $entity->set($this->fieldName, $file_ids);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $result = [];

    if ($this->fieldDefinition->getType() == 'uri') {
      $data = $entity->get($this->fieldName)->getValue();

      foreach ($data as $value) {
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $value['value']]);
        $file = empty($files) ? NULL : reset($files);
        if ($file) {
          unset($value['value']);
          $result[] = $intent->embedEntity($file, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $value);
        }
      }
    }
    else {
      $data = $entity->get($this->fieldName)->getValue();

      foreach ($data as $value) {
        if (empty($value['target_id'])) {
          continue;
        }

        $file = File::load($value['target_id']);
        if ($file) {
          unset($value['target_id']);
          $result[] = $intent->embedEntity($file, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $value);
        }
      }
    }

    $intent->setField($this->fieldName, $result);

    return TRUE;
  }

}
