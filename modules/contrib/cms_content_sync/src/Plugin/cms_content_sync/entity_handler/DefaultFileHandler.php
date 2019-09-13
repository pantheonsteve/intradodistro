<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Exception\SyncException;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class DefaultFileHandler, providing proper file handling capabilities.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_file_handler",
 *   label = @Translation("Default File"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultFileHandler extends EntityHandlerBase {

  const USER_PROPERTY = 'uid';

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'file';
  }

  /**
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      ExportIntent::EXPORT_DISABLED,
      ExportIntent::EXPORT_AUTOMATICALLY,
      ExportIntent::EXPORT_AS_DEPENDENCY,
      ExportIntent::EXPORT_MANUALLY,
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
  public function updateEntityTypeDefinition(&$definition) {
    parent::updateEntityTypeDefinition($definition);

    $definition['new_properties']['apiu_file_content'] = [
      'type' => 'string',
      'default_value' => NULL,
    ];
    $definition['new_property_lists']['details']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['filesystem']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['modifiable']['apiu_file_content'] = 'value';
    $definition['new_property_lists']['required']['apiu_file_content'] = 'value';

    $definition['new_properties']['uri'] = [
      'type' => 'object',
      'default_value' => NULL,
      'multiple' => TRUE,
    ];
    $definition['new_property_lists']['details']['uri'] = 'value';
    $definition['new_property_lists']['database']['uri'] = 'value';
    $definition['new_property_lists']['required']['uri'] = 'value';
  }

  /**
   * @inheritdoc
   */
  public function getForbiddenFields() {
    return array_merge(
      parent::getForbiddenFields(),
      [
        'uri',
        'filemime',
        'filesize',
      ]
    );
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    /**
     * @var \Drupal\file\FileInterface $entity
     */
    $entity = $intent->getEntity();
    $action = $intent->getAction();

    if ($action == SyncIntent::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity);
      }
      return FALSE;
    }

    $uri = $intent->getField('uri');
    if (empty($uri)) {
      throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
    }
    if (!empty($uri[0]['value'])) {
      $uri = $uri[0]['value'];
    }

    $content = $intent->getField('apiu_file_content');
    if (!$content) {
      throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
    }

    if ($action == SyncIntent::ACTION_UPDATE || $entity) {
      $content = $intent->getField('apiu_file_content');
      if (!$content) {
        throw new SyncException(SyncException::CODE_INVALID_IMPORT_REQUEST);
      }

      if ($entity = file_save_data(base64_decode($content), $uri, FILE_EXISTS_REPLACE)) {
        // Drupal will re-use the existing file entity and keep it's ID, but
        // *change the UUID* of the file entity to a new random value
        // So we have to tell Drupal we actually want to keep it so references
        // to it keep working for us.
        $entity->setPermanent();
        $entity->set('uuid', $intent->getUuid());
        $entity->set('filename', $intent->getField('title'));
        $entity->save();
        return TRUE;
      }

      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
    }
    else {
      $directory = \Drupal::service('file_system')->dirname($uri);
      $was_prepared = file_prepare_directory($directory, FILE_CREATE_DIRECTORY);

      if ($was_prepared) {
        $entity = file_save_data(base64_decode($content), $uri);
        $entity->setPermanent();
        $entity->set('uuid', $intent->getUuid());
        $entity->set('filename', $intent->getField('title'));
        $entity->save();
        return TRUE;
      }

      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
    }
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    /**
     * @var \Drupal\file\FileInterface $entity
     */
    if (!$entity) {
      $entity = $intent->getEntity();
    }

    if (!parent::export($intent)) {
      return FALSE;
    }

    // Base Info.
    $uri = $entity->getFileUri();
    $intent->setField('apiu_file_content', base64_encode(file_get_contents($uri)));
    $intent->setField('uri', [['value' => $uri]]);
    $intent->setField('title', $entity->getFilename());

    // Preview.
    $view_mode = $this->flow->getPreviewType($entity->getEntityTypeId(), $entity->bundle());
    if ($view_mode != Flow::PREVIEW_DISABLED) {
      $intent->setField('preview', '<img style="max-height: 200px" src="' . file_create_url($uri) . '"/>');
    }
    else {
      $intent->setField('preview', '<em>Previews are disabled for this entity.</em>');
    }

    // No Translations, No Menu items compared to EntityHandlerBase.
    // Source URL.
    $this->setSourceUrl($intent, $entity);

    return TRUE;
  }

}
