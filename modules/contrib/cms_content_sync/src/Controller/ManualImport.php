<?php

namespace Drupal\cms_content_sync\Controller;

use Drupal\cms_content_sync\Plugin\rest\resource\EntityResource;
use Drupal\cms_content_sync\SyncCore\Storage\ApiStorage;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Access\AccessResult;

/**
 * Provides a listing of Flow.
 */
class ManualImport extends ControllerBase {

  /**
   * Ensure that the import tab is just show if a flow exists which contains
   * and entity type that has its import set to "manual".
   */
  public function access() {
    $flows = Flow::getAll();
    $manual_importable_entity_types = [];
    foreach ($flows as $flow) {
      if (!empty($flow->getImportableEntityTypes(ImportIntent::IMPORT_MANUALLY))) {
        $manual_importable_entity_types[$flow->id()] = $flow->getImportableEntityTypes(ImportIntent::IMPORT_MANUALLY);
      }
    }

    return AccessResult::allowedIf(!empty($manual_importable_entity_types) ? TRUE : FALSE)
      ->addCacheableDependency($flows);
  }

  /**
   * Render the content synchronization Angular frontend.
   *
   * @return array
   */
  public function content() {
    $config = [
      'url' => EntityResource::getBaseUrl(),
      'pools' => [],
      'flows' => [],
      'api_version' => ApiStorage::CUSTOM_API_VERSION,
      'entity_types' => [],
    ];

    $pools = Pool::getAll();

    foreach (Flow::getAll() as $id => $flow) {
      $config['flows'][$flow->id] = [
        'id' => $flow->id,
        'name' => $flow->name,
      ];

      foreach ($flow->getEntityTypeConfig() as $definition) {
        if (!$flow->canImportEntity($definition['entity_type_name'], $definition['bundle_name'], ImportIntent::IMPORT_MANUALLY)) {
          continue;
        }

        foreach ($flow->getEntityTypeConfig($definition['entity_type_name'], $definition['bundle_name'])['import_pools'] as $id => $option) {
          if ($option != Pool::POOL_USAGE_ALLOW) {
            continue;
          }
          $pool = $pools[$id];
          $config['pools'][$pool->id] = [
            'id' => $pool->id,
            'label' => $pool->label,
            'site_id' => $pool->getSiteId(),
          ];
        }

        $index = $definition['entity_type_name'] . '.' . $definition['bundle_name'];
        if (!isset($config['entity_types'][$index])) {

          // Get the entity type and bundle name.
          $entity_type_storage = \Drupal::entityTypeManager()->getStorage($definition['entity_type_name']);
          $entity_type = $entity_type_storage->getEntityType();
          $entity_type_label = $entity_type->getLabel()->render();
          $bundle_info = \Drupal::service('entity_type.bundle.info')->getBundleInfo($definition['entity_type_name']);
          $bundle_label = $bundle_info[$definition['bundle_name']]['label'];

          $config['entity_types'][$index] = [
            'entity_type_name' => $definition['entity_type_name'],
            'entity_type_label' => $entity_type_label,
            'bundle_name' => $definition['bundle_name'],
            'bundle_label' => $bundle_label,
            'version' => $definition['version'],
            'pools' => [],
            'preview' => $definition['preview'],
          ];
        }
        else {
          if ($config['entity_types'][$index]['preview'] == Flow::PREVIEW_DISABLED || $definition['preview'] != Flow::PREVIEW_TABLE) {
            $config['entity_types'][$index]['preview'] = $definition['preview'];
          }
        }

        foreach ($definition['import_pools'] as $id => $action) {
          if (!isset($config['entity_types'][$index]['pools'][$id]) ||
            $action == Pool::POOL_USAGE_FORCE ||
            $config['entity_types'][$index]['pools'][$id] == Pool::POOL_USAGE_FORBID) {
            $config['entity_types'][$index]['pools'][$id] = $action;
          }
        }
      }
    }

    if (empty($config['entity_types'])) {
      drupal_set_message(t('There are no entity types to be imported manually.'));
    }

    return [
      '#theme' => 'cms_content_sync_content_dashboard',
      '#configuration' => $config,
      '#attached' => ['library' => ['cms_content_sync/import']],
    ];
  }

}
