<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class DefaultMediaHandler, providing a minimalistic implementation for the
 * media entity type.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_media_entity_handler",
 *   label = @Translation("Default Media"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultMediaHandler extends EntityHandlerBase {

  const USER_PROPERTY = 'uid';
  const USER_REVISION_PROPERTY = 'revision_user';

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'media';
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    if (!parent::export($intent, $entity)) {
      return FALSE;
    }

    if (!$entity) {
      $entity = $intent->getEntity();
    }

    /**
     * @var \Drupal\node\NodeInterface $entity
     */
    $intent->setField('created', intval($entity->getCreatedTime()));
    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function getForbiddenFields() {
    return array_merge(
      parent::getForbiddenFields(),
      [
        // Must be recreated automatically on remote site.
        'thumbnail',
      ]
    );
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

}
