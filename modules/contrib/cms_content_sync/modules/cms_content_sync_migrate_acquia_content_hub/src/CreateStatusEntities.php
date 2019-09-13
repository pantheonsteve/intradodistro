<?php

namespace Drupal\cms_content_sync_migrate_acquia_content_hub;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync_migrate_acquia_content_hub\Form\MigrationBase;
use Drupal\Core\Controller\ControllerBase;

/**
 *
 */
class CreateStatusEntities extends ControllerBase {

  /**
   * Collect relevant nodes.
   *
   * @param $flow_id
   * @param $flow_configurations
   * @param $pools
   * @param $type
   * @param bool $execute
   */
  public function prepare($flow_id, $flow_configurations, $pool_id, $type, $tags = '') {
    $operations = [];

    if ($type == 'export') {
      foreach ($flow_configurations as $type => $type_config) {
        foreach ($type_config as $bundle => $bundle_config) {
          if ($bundle_config['export_configuration']['behavior'] != ExportIntent::EXPORT_AUTOMATICALLY) {
            continue;
          }

          $entity_type = \Drupal::entityTypeManager()->getDefinition($type);
          $ids = \Drupal::entityQuery($type)->condition($entity_type->getKey('bundle'), $bundle)->execute();
          foreach ($ids as $id) {
            $operations[] = [
              __NAMESPACE__ . '\CreateStatusEntities::execute',
              [$type, $id, $flow_id, $pool_id, 'export'],
            ];
          }
        }
      }

      return $operations;
    }

    $tags = MigrationBase::getTermsFromFilter($tags);
    if (empty($tags)) {
      return $operations;
    }

    $ids = [];
    foreach ($tags as $tag) {
      $ids[] = $tag->id();
    }

    $query = \Drupal::database()->select('taxonomy_index', 'ti');
    $query->fields('ti', ['nid']);
    $query->condition('ti.tid', $ids, 'IN');
    $result = $query->execute()->fetchCol();

    foreach ($result as $nid) {
      $operations[] = [
        __NAMESPACE__ . '\CreateStatusEntities::execute',
        ['node', $nid, $flow_id, $pool_id, 'import'],
      ];
    }

    return $operations;
  }

  /**
   * Batch create Status Entities for collected nodes.
   *
   * @param $nid
   * @param $flow_id
   * @param $bundle_id
   * @param $pools
   * @param $field_name
   * @param $type
   */
  public static function execute($entity_type, $entity_id, $flow_id, $pool_id, $type) {
    $entity = \Drupal::entityTypeManager()->getStorage($entity_type)->load($entity_id);

    // If a node has a match, create a status entity.
    // Ensure that a status entity does not already exist.
    $entity_status = EntityStatus::getInfoForEntity($entity_type, $entity->uuid(), $flow_id, $pool_id);
    if (!$entity_status) {
      $data = [
        'flow' => $flow_id,
        'pool' => $pool_id,
        'entity_type' => $entity_type,
        'entity_uuid' => $entity->uuid(),
        'entity_type_version' => Flow::getEntityTypeVersion($entity_type, $entity->bundle()),
        'flags' => 0,
        'source_url' => NULL,
      ];

      if ($entity_type == 'node' && $type == 'import') {
        $data['last_' . $type] = $entity->getChangedTime();
      }

      $entity_status = EntityStatus::create($data);

      if ($type == 'export') {
        $entity_status->isExportEnabled(TRUE);
        $entity_status->isSourceEntity(TRUE);
      }

      $entity_status->save();
    }
  }

}
