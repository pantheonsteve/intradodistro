<?php

namespace Drupal\cms_content_sync\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * An entity type is about to be exported.
 * Other modules can use this to add additional fields to the entity type
 * definition, allowing them to process additional information during export
 * and import (by using BeforeEntityExport and BeforeEntityImport).
 * Check out the cms_content_sync_simple_sitemap submodule to see how it can
 * be used.
 */
class BeforeEntityTypeExport extends Event {

  const EVENT_NAME = 'cms_content_sync.entity_type.export.before';

  /**
   * Entity type.
   *
   * @var string
   */
  protected $entity_type_name;

  /**
   * Bundle.
   *
   * @var string
   */
  protected $bundle_name;

  /**
   * Entity type definition.
   *
   * @var array
   */
  protected $definition;

  /**
   * Constructs a entity export event.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   * @param array $definition
   */
  public function __construct($entity_type_name, $bundle_name, &$definition) {
    $this->entity_type_name = $entity_type_name;
    $this->bundle_name = $bundle_name;
    $this->definition = &$definition;
  }

  /**
   * @return string
   */
  public function getBundleName() {
    return $this->bundle_name;
  }

  /**
   * @return string
   */
  public function getEntityTypeName() {
    return $this->entity_type_name;
  }

  /**
   * @param string $name
   * @param string $type
   * @param bool $multiple
   * @param bool $required
   * @param bool $modifiable
   */
  public function addField($name, $type = 'object', $multiple = TRUE, $required = FALSE, $modifiable = TRUE) {
    if (isset($this->definition['new_properties'][$name])) {
      return;
    }

    $this->definition['new_properties'][$name] = [
      'type' => $type,
      'default_value' => NULL,
      'multiple' => $multiple,
    ];

    $this->definition['new_property_lists']['details'][$name] = 'value';
    $this->definition['new_property_lists']['database'][$name] = 'value';

    if ($required) {
      $this->definition['new_property_lists']['required'][$name] = 'value';
    }

    if ($modifiable) {
      $this->definition['new_property_lists']['modifiable'][$name] = 'value';
    }
  }

}
