<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;

/**
 * Pull controller.
 */
class FlowPush extends ControllerBase {

  /**
   * Export flow.
   */
  public function push($cms_content_sync_flow) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = Flow::getAll()[$cms_content_sync_flow];

    /**
     * @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
     */
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $operations = [];
    foreach ($flow->getEntityTypeConfig(NULL, NULL, TRUE) as $config) {
      if ($config['export'] != ExportIntent::EXPORT_AUTOMATICALLY) {
        continue;
      }

      $storage = $entity_type_manager->getStorage($config['entity_type_name']);

      $ids = $storage
        ->getQuery()
        ->condition($storage->getEntityType()->getKey('bundle'), $config['bundle_name'])
        ->execute();

      foreach ($ids as $id) {
        $operations[] = [
          '\Drupal\cms_content_sync\Controller\FlowPush::batchExport',
          [$cms_content_sync_flow, $config['entity_type_name'], $id],
        ];
      }
    }

    $batch = [
      'title' => t('Push all'),
      'operations' => $operations,
      'finished' => '\Drupal\cms_content_sync\Controller\FlowPush::batchExportFinished',
    ];

    batch_set($batch);

    return batch_process(Url::fromRoute('entity.cms_content_sync_flow.collection'));
  }

  /**
   * Batch export finished callback.
   *
   * @param $success
   * @param $results
   * @param $operations
   */
  public static function batchExportFinished($success, $results, $operations) {
    if ($success) {
      $message = t('Flow has been pushed.');
    }
    else {
      $message = t('Flow push failed.');
    }

    drupal_set_message($message);

    $succeded = count(array_filter($results));
    $failed = count($results) - $succeded;
    drupal_set_message(t('%synchronized entities have been pushed.', ['%synchronized' => $succeded]));
    if ($failed) {
      drupal_set_message(t('%synchronized entities have not been exported.', ['%synchronized' => $failed]));
    }
  }

  /**
   * Batch export callback for the flow export.
   *
   * @param $ids
   * @param $context
   */
  public static function batchExport($cms_content_sync_flow, $entity_type, $entity_id, &$context) {
    $message = 'Pushing...';
    $results = [];
    if (isset($context['results'])) {
      $results = $context['results'];
    }

    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = Flow::getAll()[$cms_content_sync_flow];

    /**
     * @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
     */
    $entity_type_manager = \Drupal::service('entity_type.manager');

    $entity = $entity_type_manager
      ->getStorage($entity_type)
      ->load($entity_id);

    try {
      $status = ExportIntent::exportEntity($entity, ExportIntent::EXPORT_AUTOMATICALLY, SyncIntent::ACTION_CREATE, $flow);
    }
    catch (\Exception $exception) {
      $status = FALSE;
    }

    $results[] = $status;

    $context['message'] = $message;
    $context['results'] = $results;
  }

}
