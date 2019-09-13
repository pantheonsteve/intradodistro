<?php

namespace Drupal\cms_content_sync\Plugin;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Psr\Log\LoggerInterface;

/**
 * Common base class for field handler plugins.
 *
 * @see \Drupal\cms_content_sync\Annotation\EntityHandler
 * @see \Drupal\cms_content_sync\Plugin\FieldHandlerInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
abstract class FieldHandlerBase extends PluginBase implements ContainerFactoryPluginInterface, FieldHandlerInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * @var string
   */
  protected $entityTypeName;

  /**
   * @var string
   */
  protected $bundleName;

  /**
   * @var string
   */
  protected $fieldName;

  /**
   * @var \Drupal\Core\Field\FieldDefinitionInterface
   */
  protected $fieldDefinition;

  /**
   * @var array
   *   Additional settings as provided by
   *   {@see FieldHandlerInterface::getHandlerSettings}.
   */
  protected $settings;

  /**
   * @var \Drupal\cms_content_sync\Entity\Flow
   */
  protected $flow;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   *   Must contain entity_type_name, bundle_name, field_name, field_definition,
   *   settings and sync (see above).
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger = $logger;
    $this->entityTypeName = $configuration['entity_type_name'];
    $this->bundleName = $configuration['bundle_name'];
    $this->fieldName = $configuration['field_name'];
    $this->fieldDefinition = $configuration['field_definition'];
    $this->settings = $configuration['settings'];
    $this->flow = $configuration['sync'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('cms_content_sync')
    );
  }

  /**
   * @return string
   */
  public function getFieldName() {
    return $this->fieldName;
  }

  /**
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      ExportIntent::EXPORT_DISABLED,
      ExportIntent::EXPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedImportOptions() {
    return [
      ImportIntent::IMPORT_DISABLED,
      ImportIntent::IMPORT_AUTOMATICALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    // Nothing special here.
    return [];
  }

  /**
   * @inheritdoc
   */
  public function validateHandlerSettings(array &$form, FormStateInterface $form_state, $settings_key) {
    // No settings means no validation.
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    if ($intent->shouldMergeChanges()) {
      return FALSE;
    }

    if ($this->settings['import'] != ImportIntent::IMPORT_AUTOMATICALLY) {
      return FALSE;
    }

    $data = $intent->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $entity->set($this->fieldName, $data);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    $entity = $intent->getEntity();

    if ($this->settings['export'] != ExportIntent::EXPORT_AUTOMATICALLY) {
      return FALSE;
    }

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $intent->setField($this->fieldName, $entity->get($this->fieldName)->getValue());

    return TRUE;
  }

}
