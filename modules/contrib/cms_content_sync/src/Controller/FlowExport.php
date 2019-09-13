<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cms_content_sync\SyncCoreFlowExport;

/**
 * Push changes controller.
 */
class FlowExport extends ControllerBase {

  /**
   * Export flow.
   */
  public function export($cms_content_sync_flow) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->load($cms_content_sync_flow);

    $exporter = new SyncCoreFlowExport($flow);

    $steps      = $exporter->prepareBatch();
    $operations = [];
    foreach ($steps as $step) {
      $operations[] = [
        '\Drupal\cms_content_sync\Controller\FlowExport::batchExport',
        [$cms_content_sync_flow, $step],
      ];
    }

    $batch = [
      'title' => t('Export configuration'),
      'operations' => $operations,
      'finished' => '\Drupal\cms_content_sync\Controller\FlowExport::batchExportFinished',
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
      $message = t('Flow has been exported.');
    }
    else {
      $message = t('Flow export failed.');
    }

    drupal_set_message($message);
  }

  /**
   * Batch export callback for the flow export.
   *
   * @param $ids
   * @param $context
   */
  public static function batchExport($cms_content_sync_flow, $operation, &$context) {
    $message = 'Exporting...';
    $results = [];
    if (isset($context['results'])) {
      $results = $context['results'];
    }

    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->load($cms_content_sync_flow);

    $exporter = new SyncCoreFlowExport($flow);
    $results[] = $exporter->executeBatch($operation);

    $context['message'] = $message;
    $context['results'] = $results;
  }

}
