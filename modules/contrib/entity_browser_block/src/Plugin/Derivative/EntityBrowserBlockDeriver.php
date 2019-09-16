<?php

namespace Drupal\entity_browser_block\Plugin\Derivative;

use Drupal\Component\Plugin\Derivative\DeriverBase;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\Discovery\ContainerDeriverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Retrieves block plugin definitions for all entity browsers.
 */
class EntityBrowserBlockDeriver extends DeriverBase implements ContainerDeriverInterface {

  /**
   * The browser storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $browserStorage;

  /**
   * Constructs a EntityBrowserBlockDeriver object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $browser_storage
   *   The browser storage.
   */
  public function __construct(EntityStorageInterface $browser_storage) {
    $this->browserStorage = $browser_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, $base_plugin_id) {
    $entity_manager = $container->get('entity_type.manager');
    return new static(
      $entity_manager->getStorage('entity_browser')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getDerivativeDefinitions($base_plugin_definition) {
    /** @var \Drupal\entity_browser\EntityBrowserInterface[] $browsers */
    $browsers = $this->browserStorage->loadMultiple();
    // Reset the discovered definitions.
    $this->derivatives = [];
    foreach ($browsers as $browser) {
      $this->derivatives[$browser->id()] = $base_plugin_definition;
      $this->derivatives[$browser->id()]['admin_label'] = $browser->label();
      $this->derivatives[$browser->id()]['config_dependencies']['config'] = [
        $browser->getConfigDependencyName(),
      ];
    }
    return parent::getDerivativeDefinitions($base_plugin_definition);
  }

}
