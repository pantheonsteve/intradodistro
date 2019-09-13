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
 *   id = "cms_content_sync_default_video_handler",
 *   label = @Translation("Default Video"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultVideoHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = ["video"];
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
      $result = [];
      foreach ($data as $value) {
        $meta = $intent->getEmbeddedEntityData($value);
        $file = NULL;

        if (empty($value['uri']) || empty($value['uuid'])) {
          $file = $intent->loadEmbeddedEntity($value);
        }
        else {
          $file = \Drupal::service('entity.repository')->loadEntityByUuid(
            'file',
            $value['uuid']
          );

          if (empty($file)) {
            $file = File::create([
              'uuid' => $value['uuid'],
              'uri' => $value['uri'],
              'filemime' => $value['mimetype'],
              'filesize' => 1,
            ]);
            $file->setPermanent();
            $file->save();
          }
        }

        if ($file) {
          $meta['target_id'] = $file->id();
          $result[] = $meta;
        }
      }

      $entity->set($this->fieldName, $result);
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

    $data = $entity->get($this->fieldName)->getValue();

    foreach ($data as $value) {
      if (empty($value['target_id'])) {
        continue;
      }

      /**
       * @var \Drupal\file\FileInterface $file
       */
      $file = File::load($value['target_id']);
      if ($file) {
        unset($value['target_id']);
        $uri = $file->getFileUri();
        if (substr($uri, 0, 9) == 'public://' || substr($uri, 0, 10) == 'private://') {
          $result[] = $intent->embedEntity($file, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $value);
        }
        else {
          $value['uri'] = $uri;
          $value['uuid'] = $file->uuid();
          $value['mimetype'] = $file->getMimeType();
          $result[] = $value;
        }
      }
    }

    $intent->setField($this->fieldName, $result);

    return TRUE;
  }

}
