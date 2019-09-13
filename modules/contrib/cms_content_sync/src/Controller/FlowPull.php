<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cms_content_sync\SyncCoreFlowExport;

/**
 * Pull controller.
 */
class FlowPull extends ControllerBase {

  /**
   * Export flow.
   */
  public function pull($cms_content_sync_flow, $pull_mode) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Flow $flow
     */
    $flow = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->load($cms_content_sync_flow);

    $force_pull = FALSE;
    if ($pull_mode == 'all_entities') {
      $force_pull = TRUE;
    }

    $exporter = new SyncCoreFlowExport($flow);
    $result = $exporter->startSync(FALSE, $force_pull);

    $steps = [];

    foreach ($result as $url => $response) {
      if (!$response) {
        $steps[] = [
          'type' => 'FAILURE',
        ];
        continue;
      }

      if (empty($response['total'])) {
        $steps[] = [
          'type' => 'EMPTY',
        ];
        continue;
      }

      for ($i = 1; $i <= $response['total']; $i++) {
        $steps[] = [
          'type' => 'REQUEST',
          'goal' => $i,
          'url' => $url . '/synchronize/' . $response['id'] . '/status',
        ];
      }
    }

    $operations = [];
    foreach ($steps as $step) {
      $operations[] = [
        '\Drupal\cms_content_sync\Controller\FlowPull::batchExport',
        [$step],
      ];
    }

    $batch = [
      'title' => t('Pull all'),
      'operations' => $operations,
      'finished' => '\Drupal\cms_content_sync\Controller\FlowPull::batchExportFinished',
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
      $message = t('Flow has been pulled.');
    }
    else {
      $message = t('Flow pull failed.');
    }

    drupal_set_message($message);

    $failed = 0;
    $empty = 0;
    $synchronized = 0;
    foreach ($results as $result) {
      if ($result['type'] == 'FAILURE') {
        $failed++;
      }
      elseif ($result['type'] == 'EMPTY') {
        $empty++;
      }
      else {
        $synchronized += $result['total'];
      }
    }

    if ($failed) {
      drupal_set_message(t('Failed to export %failed synchronizations.', ['%failed' => $failed]));
    }
    if ($empty) {
      drupal_set_message(t('%empty synchronizations had no entities to synchronize.', ['%empty' => $empty]));
    }
    if ($synchronized) {
      drupal_set_message(t('%synchronized entities have been pulled.', ['%synchronized' => $synchronized]));
    }
  }

  /**
   * Batch export callback for the flow export.
   *
   * @param $ids
   * @param $context
   */
  public static function batchExport($operation, &$context) {
    $message = 'Pulling...';
    $results = [];
    if (isset($context['results'])) {
      $results = $context['results'];
    }

    if ($operation['type'] == 'FAILURE' || $operation['type'] == 'EMPTY') {
      $results[] = $operation;
    }
    else {
      $client = \Drupal::httpClient();

      $url = $operation['url'];
      if (!isset($context['url_progress'][$url])) {
        $context['url_progress'][$url] = 0;
      }

      $goal = $operation['goal'];

      while ($context['url_progress'][$url] < $goal) {
        if ($context['url_progress'][$url] > 0) {
          sleep(5);
        }

        $context['url_progress'][$url] = json_decode($client->get($url)->getBody(), TRUE)['processed'];

        if ($context['url_progress'][$url] == $goal) {
          $results[] = ['type' => 'success', 'total' => $goal];
        }
      }
    }

    $context['message'] = $message;
    $context['results'] = $results;
  }

}
