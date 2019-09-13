<?php

namespace Drupal\cms_content_sync_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Views Field handler for the flow label.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cms_content_sync_flow_label")
 */
class FlowLabel extends FieldPluginBase {

  /**
   * @{inheritdoc}
   */
  public function query() {
    // Leave empty to avoid a query on this field.
  }

  /**
   * Define the available options.
   *
   * @return array
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    return $options;
  }

  /**
   * Provide the options form.
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * @{inheritdoc}
   *
   * @param \Drupal\views\ResultRow $values
   *
   * @return \Drupal\Component\Render\MarkupInterface|\Drupal\Core\StringTranslation\TranslatableMarkup|\Drupal\views\Render\ViewsRenderPipelineMarkup|string
   */
  public function render(ResultRow $values) {
    /**
     * @var \Drupal\cms_content_sync\Entity\Pool $entity
     */
    $entity = $values->_entity;
    $flow = \Drupal::entityTypeManager()
      ->getStorage('cms_content_sync_flow')
      ->load($entity->get('flow')->value);

    if (isset($flow)) {
      return $flow->label();
    }
    else {
      return '';
    }
  }

}
