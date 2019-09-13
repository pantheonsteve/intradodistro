<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_formatted_text_handler",
 *   label = @Translation("Default Formatted Text"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultFormattedTextHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = ["text_with_summary", "text_long"];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  /**
   * Replace all "/node/..." links with their correct ID for the current site.
   *
   * @TODO If a new entity is added, we should scan the database for existing
   * references to it that can now be resolved.
   *
   * @param $text
   *
   * @return string
   */
  protected function replaceEntityReferenceLinks($text) {
    $entity_repository = \Drupal::service('entity.repository');

    // Simple image embedding (default ckeditor + IMCE images)
    $text = preg_replace_callback(
      '@(<img[^>]+src=)"/sites/[^/]+/files/([^"]+)"@',
      function ($matches) {
        $path = $matches[2];
        $uri = 'public://' . $path;

        /* @var \Drupal\file\FileInterface[] $files */
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $uri]);

        if (!count($files)) {
          return '';
        }

        /**
         * @var \Drupal\file\FileInterface $file
         */
        $file = reset($files);

        $url = $file->url();

        return $matches[1] . '"' . $url . '"';
      },
      $text
    );

    // Other file embedding (IMCE files)
    $text = preg_replace_callback(
      '@(<a[^>]+href=)"/sites/[^/]+/files/([^"]+)"@',
      function ($matches) {
        $path = $matches[2];
        $uri = 'public://' . $path;

        /* @var \Drupal\file\FileInterface[] $files */
        $files = \Drupal::entityTypeManager()
          ->getStorage('file')
          ->loadByProperties(['uri' => $uri]);

        if (!count($files)) {
          return '';
        }

        /**
         * @var \Drupal\file\FileInterface $file
         */
        $file = reset($files);

        $url = $file->url();

        return $matches[1] . '"' . $url . '"';
      },
      $text
    );

    // Entity embedding (especially media)
    $text = preg_replace_callback(
      '@data-entity-uuid="([0-9a-z-]+)" href="/node/([0-9]+)"@',
      function ($matches) use ($entity_repository) {
        $uuid = $matches[1];
        $id   = $matches[2];

        try {
          $node = $entity_repository->loadEntityByUuid('node', $uuid);
          if ($node) {
            $id = $node->id();
          }
        }
        catch (\Exception $e) {
        }

        return 'data-entity-uuid="' . $uuid . '" href="/node/' . $id . '"';
      },
      $text
    );

    return $text;
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

      foreach ($data as $item) {
        if (!empty($item['value'])) {
          // Replace node links correctly.
          $item['value'] = $this->replaceEntityReferenceLinks($item['value']);
        }
        $result[] = $item;
      }

      $entity->set($this->fieldName, $result);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    if (!parent::export($intent)) {
      return FALSE;
    }

    $entity = $intent->getEntity();

    foreach ($entity->get($this->fieldName)->getValue() as $item) {
      $text = $item['value'];

      // Simple image embedding (default ckeditor + IMCE images)
      preg_replace_callback(
        '@<img[^>]+src="/sites/[^/]+/files/([^"]+)"@',
        function ($matches) use ($intent) {
          $path = $matches[1];
          $uri = 'public://' . $path;

          /* @var \Drupal\file\FileInterface[] $files */
          $files = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->loadByProperties(['uri' => $uri]);

          if (!count($files)) {
            return '';
          }

          $file = reset($files);

          $intent->embedEntity($file, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY);

          return '';
        },
        $text
      );

      // Other file embedding (IMCE files)
      preg_replace_callback(
        '@<a[^>]+href="/sites/[^/]+/files/([^"]+)"@',
        function ($matches) use ($intent) {
          $path = $matches[1];
          $uri = 'public://' . $path;

          /* @var \Drupal\file\FileInterface[] $files */
          $files = \Drupal::entityTypeManager()
            ->getStorage('file')
            ->loadByProperties(['uri' => $uri]);

          if (!count($files)) {
            return '';
          }

          $file = reset($files);

          $intent->embedEntity($file, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY);

          return '';
        },
        $text
      );

      // Entity embedding (especially media)
      preg_replace_callback(
        '@<drupal-entity[^>]+data-entity-type="([^"]+)"\s+data-entity-uuid="([^"]+)"@',
        function ($matches) use ($intent) {
          $type = $matches[1];
          $uuid = $matches[2];

          $entity = \Drupal::service('entity.repository')->loadEntityByUuid($type, $uuid);

          if (!$entity) {
            return '';
          }

          $intent->embedEntity($entity, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY);

          return '';
        },
        $text
      );
    }

    return TRUE;
  }

}
