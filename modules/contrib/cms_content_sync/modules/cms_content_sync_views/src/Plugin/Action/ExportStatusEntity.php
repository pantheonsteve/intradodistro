<?php

namespace Drupal\cms_content_sync_views\Plugin\Action;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Session\AccountInterface;

/**
 * Export entity of status entity.
 *
 * @Action(
 *   id = "export_status_entity",
 *   label = @Translation("Force Export"),
 *   type = "cms_content_sync_entity_status"
 * )
 */
class ExportStatusEntity extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\cms_content_sync\Entity\EntityStatus $entity */
    if (!is_null($entity)) {
      $source = $entity->getEntity();
      if (empty($source)) {
        \drupal_set_message(\t('The Entity @type @uuid doesn\'t exist locally, export skipped.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '@uuid' => $entity->get('entity_uuid')->getValue()[0]['value'],
        ]), 'warning');
        return;
      }

      $flow = $entity->getFlow();
      if (empty($flow)) {
        \drupal_set_message(\t('The Flow for @type %label doesn\'t exist anymore, export skipped.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '%label' => $source->label(),
        ]), 'warning');
        return;
      }

      $pool = $entity->getPool();
      if (empty($pool)) {
        \drupal_set_message(\t('The Pool for @type %label doesn\'t exist anymore, export skipped.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '%label' => $source->label(),
        ]), 'warning');
        return;
      }

      if (!$flow->canExportEntity($source, ExportIntent::EXPORT_ANY, SyncIntent::ACTION_CREATE, $pool)) {
        \drupal_set_message(\t('The Flow @flow for @type %label doesn\'t allow export to the pool @pool.', [
          '@flow' => $flow->id,
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '%label' => $source->label(),
          '@pool' => $pool->id,
        ]), 'warning');
        return;
      }

      ExportIntent::exportEntityFromUi($source, ExportIntent::EXPORT_FORCED, SyncIntent::ACTION_CREATE, $flow, $pool);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

}
