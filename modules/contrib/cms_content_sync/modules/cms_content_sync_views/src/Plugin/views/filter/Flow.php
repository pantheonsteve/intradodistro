<?php

namespace Drupal\cms_content_sync_views\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\InOperator;

/**
 * Provides a view filter to filter on the sync state entity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cms_content_sync_flow_filter")
 */
class Flow extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Flows');

      // @codingStandardsIgnoreStart
      $flows = \Drupal\cms_content_sync\Entity\Flow::getAll();
      // @codingStandardsIgnoreEnd
      foreach ($flows as $flow_name => $flow) {
        $this->valueOptions[$flow_name] = $flow->label();
      }
      if (!empty($this->valueOptions)) {
        return $this->valueOptions;
      }
    }
    return $this->valueOptions['none'] = $this->t('None');
  }

}
