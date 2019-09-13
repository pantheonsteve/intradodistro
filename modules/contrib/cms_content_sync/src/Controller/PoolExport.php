<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cms_content_sync\SyncCorePoolExport;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Push changes controller.
 */
class PoolExport extends ControllerBase {

  /**
   * Export pool.
   */
  public function export($cms_content_sync_pool, Request $request) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Pool $pool
     */
    $pool = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_pool')
      ->load($cms_content_sync_pool);

    $exporter = new SyncCorePoolExport($pool);

    if ($request->query->get('force', 'false') === 'false' && $exporter->siteIdExists()) {
      $url = Url::fromRoute('entity.cms_content_sync_pool.export', ['cms_content_sync_pool' => $pool->id()], ['query' => ['force' => 'true']])->toString();
      $messenger = \Drupal::messenger();
      $messenger->addMessage($this->t('Another site is already using this site ID for this pool. If you changed the site URL and want to force the export, <a href="@url">click here</a>.', ['@url' => Markup::create($url)]), $messenger::TYPE_ERROR);
      return new RedirectResponse(Url::fromRoute('entity.cms_content_sync_pool.collection')->toString());
    }

    $steps      = $exporter->prepareBatch();
    $operations = [];
    foreach ($steps as $step) {
      $operations[] = [
        '\Drupal\cms_content_sync\Controller\PoolExport::batchExport',
        [$cms_content_sync_pool, $step],
      ];
    }

    $batch = [
      'title' => t('Export configuration'),
      'operations' => $operations,
      'finished' => '\Drupal\cms_content_sync\Controller\PoolExport::batchExportFinished',
    ];
    batch_set($batch);

    return batch_process(Url::fromRoute('entity.cms_content_sync_pool.collection'));
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
      $message = t('Pool has been exported.');
    }
    else {
      $message = t('Pool export failed.');
    }

    drupal_set_message($message);
  }

  /**
   * Batch export callback for the pool export.
   *
   * @param $cms_content_sync_pool
   * @param $operation
   */
  public static function batchExport($cms_content_sync_pool, $operation, &$context) {
    $message = 'Exporting...';
    $results = [];
    if (isset($context['results'])) {
      $results = $context['results'];
    }

    /**
     * @var \Drupal\cms_content_sync\Entity\Pool $pool
     */
    $pool = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_pool')
      ->load($cms_content_sync_pool);

    $exporter = new SyncCorePoolExport($pool);
    $results[] = $exporter->executeBatch($operation);

    $context['message'] = $message;
    $context['results'] = $results;
  }

}
