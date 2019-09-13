<?php

namespace Drupal\cms_content_sync_views\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Views Field handler for the flow label.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cms_content_sync_parent_entity")
 */
class ParentEntity extends FieldPluginBase {

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
    $result = [];

    /**
     * @var \Drupal\cms_content_sync\Entity\EntityStatus $entity
     */
    $entity = $values->_entity;
    $source = $entity->getEntity();
    if ($source) {
      // Ignored: node, block_content
      // TODO-.
      switch ($source->getEntityTypeId()) {
        // Custom.
        case 'field_collection_item':
        case 'menu_link_content':
        case 'brick':
        case 'file':
          // Default.
        case 'paragraph':
        case 'media':
        case 'taxonomy_term':
          break;
      }
    }

    if (empty($result)) {
      return '-';
    }

    $html = '<ul>';
    foreach ($result as $markup) {
      $html .= $markup->render();
    }
    $html .= '</ul>';

    return Markup::create($html);
  }

}
