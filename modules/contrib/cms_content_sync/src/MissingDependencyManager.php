<?php

namespace Drupal\cms_content_sync;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Class MissingDependencyManagement.
 *
 * Manage dependencies that couldn't be resolved. So if Content A references Content B and Content A is imported before
 * Content B, then the reference can't be resolved. This class ensures that as soon as Content B becomes available,
 * Content A is updated as well.
 *
 * This can have multiple causes:
 * - If your Flow is configured to export ALL of a specific entity type, that export is not ordered. So for taxonomies
 *   for example the child term may be imported before the parent term.
 * - If you don't use the "Export referenced entity automatically" functionality (e.g. with content that references
 *   other content), that content will also not arrive at the destination site in the required order (if ever).
 *
 * @package Drupal\cms_content_sync
 */
class MissingDependencyManager {
  /**
   * @var string COLLECTION_NAME
   *    The KeyValue store to use for saving unresolved dependencies.
   */
  const COLLECTION_NAME = 'cms_content_sync_dependency';

  const INDEX_ENTITY_TYPE = 'entity_type';
  const INDEX_ENTITY_ID = 'id';
  const INDEX_IMPORT_REASON = 'reason';
  const INDEX_SET_FIELD = 'field';
  const INDEX_DATA = 'data';

  /**
   * Save that an entity dependency could not be resolved so it triggers its import automatically whenever it can be
   * resolved.
   *
   * @param string $referenced_entity_type
   * @param string $referenced_entity_shared_id
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param string $reason
   * @param string|null $field
   * @param array|null $custom_data
   */
  public static function saveUnresolvedDependency($referenced_entity_type, $referenced_entity_shared_id, $entity, $reason, $field = NULL, $custom_data = NULL) {
    $storage = \Drupal::keyValue(self::COLLECTION_NAME);

    $id = $referenced_entity_type . ':' . $referenced_entity_shared_id;

    $missing = $storage->get($id);
    if (empty($missing)) {
      $missing = [];
    }

    // Skip if that entity has already been added (referencing the same entity multiple times)
    foreach ($missing as $sync) {
      if ($sync[self::INDEX_ENTITY_TYPE] === $entity->getEntityTypeId() && $sync[self::INDEX_ENTITY_ID] === $entity->uuid() && (isset($sync[self::INDEX_SET_FIELD]) ? $sync[self::INDEX_SET_FIELD] : NULL) === $field) {
        return;
      }
    }

    $data = [
      self::INDEX_ENTITY_TYPE => $entity->getEntityTypeId(),
      self::INDEX_ENTITY_ID => $entity->uuid(),
      self::INDEX_IMPORT_REASON => $reason,
    ];

    if ($field) {
      $data[self::INDEX_SET_FIELD] = $field;
    }

    if ($custom_data) {
      $data[self::INDEX_DATA] = $custom_data;
    }

    $missing[] = $data;

    $storage->set($id, $missing);
  }

  /**
   * Resolve any dependencies that were missing before for the given entity that is now available.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public static function resolveDependencies($entity) {
    $storage = \Drupal::keyValue(self::COLLECTION_NAME);

    if ($entity instanceof ConfigEntityInterface) {
      $shared_entity_id = $entity->id();
    }
    else {
      $shared_entity_id = $entity->uuid();
    }

    $id = $entity->getEntityTypeId() . ':' . $shared_entity_id;

    $missing = $storage->get($id);
    if (empty($missing)) {
      return;
    }

    foreach ($missing as $sync) {
      $infos = EntityStatus::getInfosForEntity($sync[self::INDEX_ENTITY_TYPE], $sync[self::INDEX_ENTITY_ID]);
      foreach ($infos as $info) {
        if ($info->isDeleted()) {
          break;
        }

        if (!$info->getPool() || !$info->getFlow()) {
          continue;
        }

        $referenced_entity = $info->getEntity();

        $flow = $info->getFlow();
        if (!$flow->canImportEntity($referenced_entity->getEntityTypeId(), $referenced_entity->bundle(), ImportIntent::IMPORT_FORCED)) {
          continue;
        }

        if (!empty($sync[self::INDEX_SET_FIELD])) {
          /**
           * @var \Drupal\Core\Entity\FieldableEntityInterface $referenced_entity
           */

          if ($sync[self::INDEX_SET_FIELD] === 'link' && $referenced_entity->getEntityTypeId() === 'menu_link_content') {
            if (isset($sync[self::INDEX_DATA]['enabled']) && $sync[self::INDEX_DATA]['enabled']) {
              $referenced_entity->set('enabled', [['value' => 1]]);
            }

            $data = 'entity:' . $entity->getEntityTypeId() . '/' . $entity->id();
          }
          else {
            $data = [
              'target_id' => $entity->id(),
            ];
          }

          $referenced_entity->set($sync[self::INDEX_SET_FIELD], $data);
          $referenced_entity->save();
          break;
        }

        /**
         * @var \Drupal\cms_content_sync\SyncCore\Entity\ConnectionSynchronization $sync
         */
        $connection = $info->getPool()->getConnectionSynchronizationForEntityType(
          $referenced_entity->id(),
          $referenced_entity->bundle(),
          $flow->getEntityTypeConfig($referenced_entity->getEntityTypeId(), $referenced_entity->bundle())['version'],
          FALSE
        );

        $action = $connection
          ->synchronizeSingle()
          ->setItemId($referenced_entity->uuid());

        if ($sync[self::INDEX_IMPORT_REASON] === ImportIntent::IMPORT_AS_DEPENDENCY) {
          $action->isDependency(TRUE);
        }
        elseif ($sync[self::INDEX_IMPORT_REASON] === ImportIntent::IMPORT_MANUALLY) {
          $action->isManual(TRUE);
        }

        // TODO: Add proper logging in case of failure.
        $success = $action
          ->execute()
          ->succeeded();
      }
    }

    $storage->delete($id);
  }

}
