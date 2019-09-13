<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;

/**
 * Class DefaultNodeHandler, providing proper handling for published/unpublished
 * content.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_node_handler",
 *   label = @Translation("Default Node"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultNodeHandler extends EntityHandlerBase {

  const USER_PROPERTY = 'uid';
  const USER_REVISION_PROPERTY = 'revision_uid';

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'node';
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
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = parent::getHandlerSettings();

    // TODO Move to default handler for all entities that can be published.
    $options['ignore_unpublished'] = [
      '#type' => 'checkbox',
      '#title' => 'Ignore unpublished content',
      '#default_value' => isset($this->settings['handler_settings']['ignore_unpublished']) && $this->settings['handler_settings']['ignore_unpublished'] === 0 ? 0 : 1,
    ];

    $options['allow_explicit_unpublishing'] = [
      '#type' => 'checkbox',
      '#title' => 'Allow explicit unpublishing',
      '#default_value' => isset($this->settings['handler_settings']['allow_explicit_unpublishing']) && $this->settings['handler_settings']['allow_explicit_unpublishing'] === 0 ? 0 : 1,
    ];

    return $options;
  }

  /**
   * @inheritdoc
   */
  public function ignoreImport(ImportIntent $intent) {
    // Not published? Ignore this revision then.
    if (empty($intent->getField('status')) && $this->settings['handler_settings']['ignore_unpublished'] && !$this->settings['handler_settings']['allow_explicit_unpublishing']) {
      // Unless it's a delete, then it won't have a status and is independent
      // of published state, so we don't ignore the import.
      if ($intent->getAction() != SyncIntent::ACTION_DELETE) {
        return TRUE;
      }
    }

    return parent::ignoreImport($intent);
  }

  /**
   * @inheritdoc
   */
  public function ignoreExport(ExportIntent $intent) {
    /**
     * @var \Drupal\node\NodeInterface $entity
     */
    $entity = $intent->getEntity();
    $node_storage = \Drupal::entityTypeManager()->getStorage('node');
    $node = $node_storage->load($entity->id());

    if (!$entity->isPublished() && $this->settings['handler_settings']['ignore_unpublished']) {
      if (!$this->settings['handler_settings']['allow_explicit_unpublishing'] || $node->isPublished() || ($entity->getRevisionId() == $node->getRevisionId() && !$intent->getEntityStatus()->getLastExport())) {
        return TRUE;
      }
    }

    return parent::ignoreExport($intent);
  }

}
