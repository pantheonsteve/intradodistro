<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Entity\EntityInterface;

/**
 * Class DefaultConfigEntityHandler, providing a minimalistic implementation for
 * any config entity type.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_config_entity_handler",
 *   label = @Translation("Default Config"),
 *   weight = 100
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultConfigEntityHandler extends EntityHandlerBase {

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    // Whitelist supported entity types.
    $entity_types = [
      'webform',
    ];

    return in_array($entity_type, $entity_types);
  }

  /**
   * Get all config properties for this entity type.
   *
   * @return array
   */
  protected function getConfigProperties() {
    /**
     * @var \Drupal\Core\Config\Entity\ConfigEntityTypeInterface $entity_type
     */
    $entity_type = \Drupal::entityTypeManager()->getDefinition($this->entityTypeName);

    $properties = $entity_type->getPropertiesToExport();
    if (!$properties) {
      return [];
    }

    $config_definition = \Drupal::service('config.typed')->getDefinition($this->entityTypeName . '.' . $this->bundleName . '.*');
    if (empty($config_definition)) {
      return [];
    }

    $mapping = $config_definition['mapping'];

    $result = [];

    foreach ($properties as $property) {
      // Wrong information from webform schema definition...
      // Associative arrays are NOT sequences.
      if ($this->entityTypeName === 'webform' && $property === 'access') {
        $mapping[$property]['type'] = 'mapping';
      }

      $result[$property] = $mapping[$property];
    }

    return $result;
  }

  /**
   * @inheritdoc
   */
  public function updateEntityTypeDefinition(&$definition) {
    // Add properties that are listed as "config_export" keys from the entity
    // type annotation.
    $typeMapping = [
      'uuid' => 'string',
      'boolean' => 'boolean',
      'email' => 'string',
      'integer' => 'integer',
      'float' => 'float',
      'string' => 'string',
      'text' => 'string',
      'label' => 'string',
      'uri' => 'string',
      'mapping' => 'object',
      'sequence' => 'array',
    ];

    foreach ($this->getConfigProperties() as $key => $config) {
      if (isset($definition['new_properties'][$key])) {
        continue;
      }

      $type = $config['type'];
      if (empty($typeMapping[$type])) {
        continue;
      }

      $remoteType = $typeMapping[$type];
      $multiple = FALSE;
      if ($remoteType === 'array') {
        $type = $config['sequence']['type'];
        if (empty($typeMapping[$type])) {
          continue;
        }

        $remoteType = $typeMapping[$type];
        $multiple = TRUE;
      }

      $definition['new_properties'][$key] = [
        'type' => $remoteType,
        'default_value' => NULL,
        'multiple' => $multiple,
      ];

      $definition['new_property_lists']['details'][$key] = 'value';
      $definition['new_property_lists']['database'][$key] = 'value';
      $definition['new_property_lists']['modifiable'][$key] = 'value';
    }
  }

  /**
   * Check whether the entity type supports having a label.
   *
   * @return bool
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function hasLabelProperty() {
    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [];
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    if (!parent::export($intent, $entity)) {
      return FALSE;
    }

    if (!$entity) {
      $entity = $intent->getEntity();
    }

    /**
     * @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
     */

    foreach ($this->getConfigProperties() as $property => $config) {
      if (!empty($intent->getField($property))) {
        continue;
      }

      $intent->setField($property, $entity->get($property));
    }

    return TRUE;
  }

  /**
   * Import the remote entity.
   *
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();

    if (!parent::import($intent)) {
      return FALSE;
    }

    if ($action === SyncIntent::ACTION_DELETE) {
      return TRUE;
    }

    /**
     * @var \Drupal\Core\Config\Entity\ConfigEntityInterface $entity
     */
    $entity = $intent->getEntity();

    $forbidden_fields = $this->getForbiddenFields();

    foreach ($this->getConfigProperties() as $property => $config) {
      if (in_array($property, $forbidden_fields)) {
        continue;
      }

      $entity->set($property, $intent->getField($property));
    }

    $entity->save();

    return TRUE;
  }

}
