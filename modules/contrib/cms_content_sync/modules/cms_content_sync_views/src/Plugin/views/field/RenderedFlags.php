<?php

namespace Drupal\cms_content_sync_views\Plugin\views\field;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * Views Field handler to check if a entity is imported.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("cms_content_sync_rendered_flags")
 */
class RenderedFlags extends FieldPluginBase {

  const FLAG_DESCRIPTION = [
    'export_failed' => 'Last Export failed (%error)',
    'export_failed_soft' => 'No export (%error)',
    'import_failed' => 'Last import failed (%error)',
    'import_failed_soft' => 'No import (%error)',
    'last_export_reset' => 'Reset: Requires export',
    'last_import_reset' => 'Reset: Requires import',
    'is_source_entity' => 'Created at this site',
    'edit_override' => 'Imported and overwritten',
    'is_deleted' => 'Deleted',
  ];

  /**
   *
   */
  public static function describeFlag($name, $error = NULL) {
    $description = self::FLAG_DESCRIPTION[$name];
    if (empty($error)) {
      $description = str_replace(' (%error)', '', $description);
    }
    else {
      $description = str_replace(' (%error)', $error, $description);
    }
    return $description;
  }

  const ERROR_DESCRIPTION = [
    ExportIntent::EXPORT_FAILED_REQUEST_FAILED => 'Sync Core not available',
    ExportIntent::EXPORT_FAILED_REQUEST_INVALID_STATUS_CODE => 'invalid status code',
    ExportIntent::EXPORT_FAILED_INTERNAL_ERROR => 'Drupal API error',
    ExportIntent::EXPORT_FAILED_DEPENDENCY_EXPORT_FAILED => 'dependency failed to export',
    ExportIntent::EXPORT_FAILED_HANDLER_DENIED => 'as configured',
    ExportIntent::EXPORT_FAILED_UNCHANGED => 'no changes',

    ImportIntent::IMPORT_FAILED_DIFFERENT_VERSION => 'different version',
    ImportIntent::IMPORT_FAILED_CONTENT_SYNC_ERROR => 'module failure',
    ImportIntent::IMPORT_FAILED_INTERNAL_ERROR => 'Drupal API failure',
    ImportIntent::IMPORT_FAILED_UNKNOWN_POOL => 'unknown Pool',
    ImportIntent::IMPORT_FAILED_NO_FLOW => 'no matching Flow',
  ];

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
   * @param string $flag
   * @param array $details
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   */
  protected function renderError($flag, $details) {
    if (empty(self::FLAG_DESCRIPTION[$flag])) {
      $message = $flag . ' (%error)';
    }
    else {
      $message = self::FLAG_DESCRIPTION[$flag];
    }

    if (empty($details['error'])) {
      $error = 'unknown';
    }
    elseif (empty(self::ERROR_DESCRIPTION[$details['error']])) {
      $error = $details['error'];
    }
    else {
      $error = self::ERROR_DESCRIPTION[$details['error']];
    }

    return $this->t($message, [
      '%error' => $this->t($error),
    ]);
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
     * @var \Drupal\cms_content_sync\Entity\EntityStatus $entity
     */
    $entity = $values->_entity;

    $flags = [
      'export_failed' => $entity->didExportFail(),
      'export_failed_soft' => $entity->didExportFail(NULL, TRUE),
      'import_failed' => $entity->didImportFail(),
      'import_failed_soft' => $entity->didImportFail(NULL, TRUE),
      'last_export_reset' => $entity->wasLastExportReset(),
      'last_import_reset' => $entity->wasLastImportReset(),
      'is_source_entity' => $entity->isSourceEntity(),
      'edit_override' => $entity->isOverriddenLocally(),
      'is_deleted' => $entity->isDeleted(),
    ];

    $messages = [];
    if ($flags['export_failed']) {
      $details = $entity->getData(EntityStatus::DATA_EXPORT_FAILURE);
      $messages['export_failed'] = $this->renderError('export_failed', $details);
    }
    if ($flags['export_failed_soft']) {
      $details = $entity->getData(EntityStatus::DATA_EXPORT_FAILURE);
      $messages['export_failed_soft'] = $this->renderError('export_failed', $details);
    }
    if ($flags['import_failed']) {
      $details = $entity->getData(EntityStatus::DATA_IMPORT_FAILURE);
      $messages['import_failed'] = $this->renderError('import_failed', $details);
    }
    if ($flags['import_failed_soft']) {
      $details = $entity->getData(EntityStatus::DATA_IMPORT_FAILURE);
      $messages['import_failed_soft'] = $this->renderError('import_failed', $details);
    }
    if ($flags['last_export_reset']) {
      $messages['last_export_reset'] = $this->t(self::FLAG_DESCRIPTION['last_export_reset']);
    }
    if ($flags['last_import_reset']) {
      $messages['last_import_reset'] = $this->t(self::FLAG_DESCRIPTION['last_import_reset']);
    }
    if ($flags['is_source_entity']) {
      $messages['is_source_entity'] = $this->t(self::FLAG_DESCRIPTION['is_source_entity']);
    }
    if ($flags['edit_override']) {
      $messages['edit_override'] = $this->t(self::FLAG_DESCRIPTION['edit_override']);
    }
    if ($flags['is_deleted']) {
      $messages['is_deleted'] = $this->t(self::FLAG_DESCRIPTION['is_deleted']);
    }

    $renderable = [
      '#theme' => 'rendered_flags',
      '#messages' => $messages,
      '#cache' => [
        'max-age' => 0,
      ],
    ];
    $rendered = \Drupal::service('renderer')->render($renderable);

    return $rendered;
  }

}
