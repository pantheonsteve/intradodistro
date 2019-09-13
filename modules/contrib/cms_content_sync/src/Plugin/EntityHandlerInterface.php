<?php

namespace Drupal\cms_content_sync\Plugin;

use Drupal\Component\Plugin\PluginInspectionInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Form\FormStateInterface;

/**
 * Specifies the publicly available methods of an entity handler plugin that can
 * be used to export and import entities with Sync Core.
 *
 * @see \Drupal\cms_content_sync\Annotation\EntityHandler
 * @see \Drupal\cms_content_sync\Plugin\EntityHandlerBase
 * @see \Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager
 * @see \Drupal\cms_content_sync\Entity\Flow
 * @see plugin_api
 *
 * @ingroup third_party
 */
interface EntityHandlerInterface extends PluginInspectionInterface {

  /**
   * Check if this handler supports the given entity type.
   *
   * @param string $entity_type
   * @param string $bundle
   *
   * @return bool
   */
  public static function supports($entity_type, $bundle);

  /**
   * Get the allowed export options.
   *
   * Get a list of all allowed export options for this entity.
   *
   * @see Flow::EXPORT_*
   *
   * @return string[]
   */
  public function getAllowedExportOptions();

  /**
   * Get the allowed import options.
   *
   * Get a list of all allowed import options for this field.
   *
   * @see Flow::IMPORT_*
   *
   * @return string[]
   */
  public function getAllowedImportOptions();

  /**
   * @return string[]
   *   Provide the allowed preview options used for display when manually
   *   importing entities.
   */
  public function getAllowedPreviewOptions();

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
   * Update the entity type definition.
   *
   * Advanced entity type definition settings for the Sync Core. You
   * can usually ignore these.
   *
   * @param $definition
   *   The definition to be sent to Sync Core.
   *   {@see SyncCoreExport}
   */
  public function updateEntityTypeDefinition(&$definition);

  /**
   * Provide a list of fields that are not allowed to be exported or imported.
   * These fields typically contain all label fields that are exported
   * separately anyway (we don't want to set IDs and revision IDs of entities
   * for example, but only use the UUID for references).
   *
   * @return string[]
   */
  public function getForbiddenFields();

  /**
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been imported. FALSE is a desired state,
   *   meaning nothing should be imported according to config.
   */
  public function import(ImportIntent $intent);

  /**
   * @param \Drupal\cms_content_sync\ExportIntent $intent
   *   The request to store all relevant info at.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Whether or not the content has been exported. FALSE is a desired state,
   *   meaning nothing should be exported according to config.
   */
  public function export(ExportIntent $intent);

}
