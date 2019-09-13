<?php

namespace Drupal\cms_content_sync_views\Plugin\Action;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Action\ActionBase;
use Drupal\Core\Config\Entity\ConfigEntityInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Import entity of status entity.
 *
 * @Action(
 *   id = "import_status_entity",
 *   label = @Translation("Force Import"),
 *   type = "cms_content_sync_entity_status"
 * )
 */
class ImportStatusEntity extends ActionBase {

  /**
   * {@inheritdoc}
   */
  public function execute($entity = NULL) {
    /** @var \Drupal\cms_content_sync\Entity\EntityStatus $entity */
    if (!is_null($entity)) {
      $source = $entity->getEntity();
      if (empty($source)) {
        \drupal_set_message(\t('The Entity @type @uuid doesn\'t exist locally, import skipped.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '@uuid' => $entity->get('entity_uuid')->getValue()[0]['value'],
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

      $entity_type_name = $source->getEntityTypeId();
      $entity_bundle = $source->bundle();

      $manual = FALSE;

      $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, ImportIntent::IMPORT_AUTOMATICALLY);
      if (!$flow) {
        $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, ImportIntent::IMPORT_MANUALLY);
        if ($flow) {
          $manual = TRUE;
        }
        else {
          $flow = Flow::getFlowForApiAndEntityType($pool, $entity_type_name, $entity_bundle, ImportIntent::IMPORT_AS_DEPENDENCY);
          if (!$flow) {
            \drupal_set_message(\t('No Flow exists to import @type %label, import skipped.', [
              '@type' => $entity->get('entity_type')->getValue()[0]['value'],
              '%label' => $source->label(),
            ]), 'warning');
            return;
          }
        }
      }

      /**
       * @var \Drupal\cms_content_sync\SyncCore\Entity\ConnectionSynchronization $sync
       */
      $sync = $pool->getConnectionSynchronizationForEntityType(
        $entity_type_name,
        $entity_bundle,
        $entity->getEntityTypeVersion(),
        FALSE
      );

      if ($source instanceof ConfigEntityInterface) {
        $shared_entity_id = $source->id();
      }
      else {
        $shared_entity_id = $source->uuid();
      }

      $action = $sync
        ->synchronizeSingle()
        ->setItemId($shared_entity_id);

      if ($manual) {
        $action->isManual(TRUE);
      }

      $success = $action
        ->execute()
        ->succeeded();

      if ($success) {
        \drupal_set_message(\t('Import of @type %label has been triggered.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '%label' => $source->label(),
        ]));
      }
      else {
        \drupal_set_message(\t('Import of @type %label couldn\'t be triggered.', [
          '@type' => $entity->get('entity_type')->getValue()[0]['value'],
          '%label' => $source->label(),
        ]), 'warning');
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function access($object, AccountInterface $account = NULL, $return_as_object = FALSE) {
    return TRUE;
  }

}
