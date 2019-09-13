<?php

namespace Drupal\cms_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Specifies the publicly available methods of a field handler plugin that can
 * be used to export and import fields with Sync Core.
 *
 * @see \Drupal\cms_content_sync\Annotation\FieldHandler
 * @see \Drupal\cms_content_sync\Plugin\FieldHandlerBase
 * @see \Drupal\cms_content_sync\Plugin\Type\FieldHandlerPluginManager
 * @see \Drupal\cms_content_sync\Entity\Flow
 * @see plugin_api
 *
 * @ingroup third_party
 */
interface FieldHandlerInterface extends PluginInspectionInterface {

  /**
   * Check if this handler supports the given field instance.
   *
   * @param string $entity_type
   * @param string $bundle
   * @param string $field_name
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *
   * @return bool
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field);

  /**
   * Get the allowed export options.
   *
   * Get a list of all allowed export options for this field. You can
   * either allow {@see ExportIntent::EXPORT_DISABLED} or
   * {@see ExportIntent::EXPORT_DISABLED} and
   * {@see ExportIntent::EXPORT_AUTOMATICALLY}.
   *
   * @return string[]
   */
  public function getAllowedExportOptions();

  /**
   * Get the allowed import options.
   *
   * Get a list of all allowed import options for this field. You can
   * either allow {@see ImportIntent::IMPORT_DISABLED} or
   * {@see ImportIntent::IMPORT_DISABLED} and
   * {@see ImportIntent::IMPORT_AUTOMATICALLY}.
   *
   * @return string[]
   */
  public function getAllowedImportOptions();

  /**
   * Get the handler settings.
   *
   * Return the actual form elements for any additional settings for this
   * handler.
   *
   * @return array
   */
  public function getHandlerSettings();

  /**
   * Validate the settings defined above. $form and $form_state are the same as
   * in the Form API. $settings_key is the index at $form['sync_entities'] for
   * this handler instance.
   *
   * @param array $form
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   * @param $settings_key
   *
   * @return mixed
   */
  public function validateHandlerSettings(array &$form, FormStateInterface $form_state, $settings_key);

  /**
   * @param \Drupal\cms_content_sync\SyncIntent $intent
   *   The request containing all exported data.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been imported. FALSE is a desired state,
   *   meaning the entity should not be imported according to config.
   */
  public function import(ImportIntent $intent);

  /**
   * @param \Drupal\cms_content_sync\SyncIntent $intent
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been exported. FALSE is a desired state,
   *   meaning the entity should not be exported according to config.
   */
  public function export(ExportIntent $intent);

  /**
   * @return string The field name this handler belongs to.
   */
  public function getFieldName();

}
