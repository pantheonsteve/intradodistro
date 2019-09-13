<?php

namespace Drupal\cms_content_sync_views\Plugin\views\filter;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\views\ViewExecutable;
use Drupal\views\Views;

/**
 * Provides a view filter to filter on the sync state entity.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("cms_content_sync_sync_state_filter")
 */
class SyncState extends InOperator implements ContainerFactoryPluginInterface {

  /**
   * @var base_field
   */
  protected $base_field;

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
    $this->base_field = $view->storage->get('base_field');
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $this->valueTitle = $this->t('Content synchronization');
      $this->valueOptions = [
        'exported' => $this->t('Is exported'),
        'exported_update' => $this->t('Is exported - Update waiting'),
        'imported' => $this->t('Is imported'),
        'overridden_locally' => $this->t('Is overridden locally'),
      ];
    }
    return $this->valueOptions;
  }

  /**
   *
   */
  public function operators() {
    $operators = [
      'in' => [
        'title' => $this->t('Is one of'),
        'short' => $this->t('in'),
        'short_single' => $this->t('='),
        'method' => 'opSimple',
        'values' => 1,
      ],
    ];

    return $operators;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $values = $this->value;
    $operator = $this->operator;
    $entity_table = $this->ensureMyTable();

    // Join the entity status table.
    $configuration_entity_status = [
      'table' => 'cms_content_sync_entity_status',
      'field' => 'entity_uuid',
      'left_table' => $entity_table,
      'left_field' => 'uuid',
      'operator' => '=',
    ];

    $join_entity_status = Views::pluginManager('join')
      ->createInstance('standard', $configuration_entity_status);
    $this->query->addRelationship('cms_content_sync_entity_status', $join_entity_status, $entity_table);

    // Add filter.
    // @ToDo: Provide more operators.
    if ($operator == 'in') {
      if (in_array('exported', $values)) {
        $this->query->addWhere('', 'cms_content_sync_entity_status.last_export', '', 'IS NOT NULL');
      }
      if (in_array('exported_update', $values)) {
        $this->query->addWhere('', 'cms_content_sync_entity_status.last_export', '', 'IS NOT NULL');

        // @ToDo: This is not working correctly with translations.
        $this->query->addWhereExpression($this->options['group'], 'cms_content_sync_entity_status.last_export != ' . $entity_table . '_field_data' . '.changed');
      }
      if (in_array('imported', $values)) {
        $this->query->addWhere('', 'cms_content_sync_entity_status.last_import', '', 'IS NOT NULL');
      }
      if (in_array('overridden_locally', $values)) {
        $this->query->addWhereExpression($this->options['group'], 'cms_content_sync_entity_status.flags&' . EntityStatus::FLAG_EDIT_OVERRIDE . '=' . EntityStatus::FLAG_EDIT_OVERRIDE);
      }
    }
  }

}
