<?php

namespace Drupal\cms_content_sync_views\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a view filter to filter on the sync state entity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cms_content_sync_pool_filter")
 */
class Pool extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Pools');

      // @codingStandardsIgnoreStarts
      $pools = \Drupal\cms_content_sync\Entity\Pool::getAll();
      // @codingStandardsIgnoreEnd
      if (!empty($pools)) {
        foreach ($pools as $pool_name => $pool) {
          $this->valueOptions[$pool_name] = $pool->label();
        }
        return $this->valueOptions;
      }
    }
    return $this->valueOptions['none'] = $this->t('None');
  }

}
