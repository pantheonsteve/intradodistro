<?php

namespace Drupal\cms_content_sync_views\Plugin\views\filter;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\cms_content_sync\Entity\Flow;

/**
 * Provides a view filter to filter on the sync state entity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cms_content_sync_entity_type_filter")
 */
class EntityType extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Entity Type');

      $this->valueOptions = [];
      $flows = Flow::getAll();

      if (!empty($flows)) {
        foreach ($flows as $flow) {
          foreach ($flow->getEntityTypeConfig(NULL, NULL, TRUE) as $config) {
            $type_name = $config['entity_type_name'];

            if (isset($this->valueOptions[$type_name])) {
              continue;
            }

            /**
             * @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
             */
            $entityTypeManager = \Drupal::service('entity_type.manager');
            $type = $entityTypeManager->getDefinition($type_name);

            $this->valueOptions[$type_name] = $type->getLabel();
          }
        }
        return $this->valueOptions;
      }
    }
    return $this->valueOptions['none'] = $this->t('None');
  }

}
