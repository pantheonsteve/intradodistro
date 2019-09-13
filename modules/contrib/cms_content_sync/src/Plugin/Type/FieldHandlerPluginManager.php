<?php

namespace Drupal\cms_content_sync\Plugin\Type;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Plugin\DefaultPluginManager;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Manages discovery and instantiation of field handler plugins.
 *
 * @see \Drupal\cms_content_sync\Annotation\FieldHandler
 * @see \Drupal\cms_content_sync\Plugin\FieldHandlerBase
 * @see \Drupal\cms_content_sync\Plugin\FieldHandlerInterface
 * @see plugin_api
 */
class FieldHandlerPluginManager extends DefaultPluginManager {

  /**
   * Constructor.
   *
   * Constructs a new
   * \Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager object.
   *
   * @param \Traversable $namespaces
   *   An object that implements \Traversable which contains the root paths
   *   keyed by the corresponding namespace to look for plugin implementations.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   Cache backend instance to use.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler to invoke the alter hook with.
   */
  public function __construct(\Traversable $namespaces, CacheBackendInterface $cache_backend, ModuleHandlerInterface $module_handler) {
    parent::__construct('Plugin/cms_content_sync/field_handler', $namespaces, $module_handler, 'Drupal\cms_content_sync\Plugin\FieldHandlerInterface', 'Drupal\cms_content_sync\Annotation\FieldHandler');

    $this->setCacheBackend($cache_backend, 'cms_content_sync_field_handler_plugins');
    $this->alterInfo('cms_content_sync_field_handler');
  }

  /**
   * {@inheritdoc}
   *
   * @deprecated in Drupal 8.2.0.
   *   Use Drupal\rest\Plugin\Type\ResourcePluginManager::createInstance()
   *   instead.
   *
   * @see https://www.drupal.org/node/2874934
   */
  public function getInstance(array $options) {
    if (isset($options['id'])) {
      return $this->createInstance($options['id']);
    }
    return NULL;
  }

  /**
   * {@inheritdoc}
   */
  protected function findDefinitions() {
    $definitions = parent::findDefinitions();
    uasort($definitions, function ($a, $b) {
      return $a['weight'] <=> $b['weight'];
    });
    return $definitions;
  }

  /**
   * @param string $entity_type
   *   The entity type of the processed entity.
   * @param string $bundle
   *   The bundle of the processed entity.
   * @param string $field_name
   *   The name of the processed field.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field
   *   The definition of the processed field.
   * @param bool $labels_only
   *   Whether to return labels instead of the whole definition.
   *
   * @return array
   *   An associative array $id=>$label|$handlerDefinition to display options
   */
  public function getHandlerOptions($entity_type, $bundle, $field_name, FieldDefinitionInterface $field, $labels_only = FALSE) {
    $options = [];

    foreach ($this->getDefinitions() as $id => $definition) {
      if (!$definition['class']::supports($entity_type, $bundle, $field_name, $field)) {
        continue;
      }
      $options[$id] = $labels_only ? $definition['label']->render() : $definition;
    }

    return $options;
  }

}
