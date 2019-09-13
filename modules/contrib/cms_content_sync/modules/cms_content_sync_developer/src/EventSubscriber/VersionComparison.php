<?php

namespace Drupal\cms_content_sync_developer\EventSubscriber;

use Drupal\Core\Config\ConfigCrudEvent;
use Drupal\Core\Config\ConfigEvents;
use Drupal\field\Entity\FieldStorageConfig;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\Core\Config\ConfigFactory;

/**
 * A subscriber triggering a config when certain configuration changes.
 */
class VersionComparison implements EventSubscriberInterface {

  /**
   * The config Factory.
   *
   * @var \Drupal\Core\Config\ConfigFactory
   */
  protected $config_factory;

  /**
   * MyModuleService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   */
  public function __construct(ConfigFactory $config_factory) {
    $this->config_factory = $config_factory;
  }

  /**
   * Check for config changes on create.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function doComparisonOnCreate(ConfigCrudEvent $event) {
    // Comparison is not done if config got changed by CLI.
    if (in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg'])) {
      return;
    }

    $saved_config = $event->getConfig();
    $new_config = $saved_config->getRawData();
    $old_config = $saved_config->getOriginal();

    // Check if the config has changed and that we got a new configuration.
    if ($new_config === $old_config || empty($new_config)) {
      return;
    }

    // Entity Type and bundle are not set consistent between the entity types.
    $entity_type = $this->getEntityTypeFromConfig($new_config);
    $bundle = $this->getBundleFromConfig($new_config);

    if (!isset($entity_type) || !isset($bundle)) {
      return;
    }

    $flows = Flow::getAll();
    $mismatching_flows = [];
    foreach ($flows as $flow_id => $flow) {

      // We only need to compare the version for the effected entity type.
      $entity_type_config = $flow->getEntityTypeConfig($entity_type, $bundle);

      if ($entity_type_config && $entity_type_config['handler'] != Flow::HANDLER_IGNORE) {
        $current_version = $entity_type_config['version'];
        $new_version = $flow->getEntityTypeVersion($entity_type_config['entity_type_name'], $entity_type_config['bundle_name']);
        if ($current_version != $new_version) {
          $mismatching_flows[$flow_id] = $flow->label();
        }
      }
      else {
        // If no entity type config exists for now,
        // we assume that it is a new entity type.
        $configs = $flow->sync_entities;
        foreach ($configs as $config_id => $config) {
          if (isset($config['handler_settings']['export_referenced_entities']) && $config['handler_settings']['export_referenced_entities']) {

            // Check if there is a reference field handler that
            // automatically exports other bundles of this entity type.
            preg_match('/^([^-]+)-([^-]+)-([^-]+)$/', $config_id, $matches);
            $config_entity_type_name = $matches[1];
            $config_field_name       = $matches[3];
            $field_storage           = \Drupal::entityTypeManager()->getStorage('field_storage_config')->load($config_entity_type_name . '.' . $config_field_name);

            if ($field_storage instanceof FieldStorageConfig) {
              $settings = $field_storage->get('settings');
              if ($settings['target_type'] == $entity_type) {
                $mismatching_flows[$flow_id] = $flow->label();
              }
            }
          }
        }
      }

    }

    // Set the mismatching flows.
    if (!empty($mismatching_flows)) {
      $this->setMismatchingFlows($mismatching_flows);
    }
  }

  /**
   * Check for config changes on delete.
   *
   * @param \Drupal\Core\Config\ConfigCrudEvent $event
   *   The Event to process.
   */
  public function doComparisonOnDelete(ConfigCrudEvent $event) {
    // Very comparison is not done if config got changed by CLI.
    if (!in_array(PHP_SAPI, ['cli', 'cli-server', 'phpdbg'])) {
      $saved_config = $event->getConfig();
      $old_config = $saved_config->getOriginal();

      if (isset($old_config['entity_type']) && isset($old_config['bundle'])) {
        $flows = Flow::getAll();
        $mismatching_flows = [];
        foreach ($flows as $flow_id => $flow) {

          // Entity Type and bundle are not set consistent between the entity types.
          $entity_type = $this->getEntityTypeFromConfig($old_config);
          $bundle = $this->getBundleFromConfig($old_config);
          if (isset($entity_type) && isset($bundle)) {
            $entity_type_config = $flow->getEntityTypeConfig($entity_type, $bundle);

            if (isset($entity_type_config['handler'])) {
              $mismatching_flows[$flow_id] = $flow->label();
              $this->setMismatchingFlows($mismatching_flows);
            }
          }
        }
      }
    }
  }

  /**
   * Set current mismatching flows.
   *
   * @param $mismatching_flows
   */
  public function setMismatchingFlows($mismatching_flows) {
    $config = $this->config_factory;
    $developer_config = $config->getEditable('cms_content_sync.developer');
    $version_mismatch_config = $developer_config->get('version_mismatch');

    if (empty($version_mismatch_config)) {
      $developer_config->set('version_mismatch', $mismatching_flows);
    }
    else {
      $new_mismatching_config = array_unique(array_merge($mismatching_flows, $version_mismatch_config));
      $developer_config->set('version_mismatch', $new_mismatching_config);
    }

    $developer_config->save();
  }

  /**
   * Get the entity type.
   *
   * The entity type is not set consistent between the entity types.
   *
   * @param $config
   *
   * @return $entity_type or NULL
   */
  public function getEntityTypeFromConfig($config) {
    if (isset($config['entity_type'])) {
      $entity_type = $config['entity_type'];
    }
    elseif (isset($config['target_entity_type_id'])) {
      $entity_type = $config['target_entity_type_id'];
    }

    return isset($entity_type) ? $entity_type : NULL;
  }

  /**
   * Get the bundle.
   *
   * The bundle is not set consistent between the entity types.
   *
   * @param $config
   *
   * @return $entity_type or NULL
   */
  public function getBundleFromConfig($config) {
    if (isset($config['bundle'])) {
      $bundle = $config['bundle'];
    }
    elseif (isset($config['target_bundle'])) {
      $bundle = $config['target_bundle'];
    }

    return isset($bundle) ? $bundle : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[ConfigEvents::SAVE][] = ['doComparisonOnCreate'];
    $events[ConfigEvents::DELETE][] = ['doComparisonOnDelete'];
    return $events;
  }

}
