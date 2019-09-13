<?php

/**
 * @file
 * Contains Drush commands for CMS Content Sync.
 */

use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\SyncCorePoolExport;
use Drupal\cms_content_sync\SyncCoreFlowExport;

/**
 * Implements hook_drush_command().
 */
function cms_content_sync_drush_command() {
  $items['content-sync-configuration-export'] = [
    'description' => dt('Export configuration to the Sync Core.'),
    'aliases' => ['cse', 'csce'],
    'options' => [
      'force' => 'Whether to ignore that another site is already using the same site ID. Useful if you change the URL of a site.',
    ],
  ];

  $items['content-sync-sync-core-login'] = [
    'description' => dt('Export configuration to the Sync Core.'),
    'aliases' => ['csscl'],
  ];

  $items['content-sync-pull-entities'] = [
    'description' => dt('Pull entities from Sync Core.'),
    'aliases' => ['cspe'],
    'options' => [
      'flow_id' => 'The flow the entities should be pulled for.',
      'force' => 'Also update entities which have already been imported.',
    ],
    'examples' => [
      'drush cspe' => 'Pull all entities from all flows',
      'drush cspe --flow_id="example_flow"' => 'Pull all entities from the "example_flow".',
      'drush cspe --flow_id="example_flow" --force' => 'Pull all entities from the "example_flow" and force entities which already have been imported to be updated as well.',
    ],
  ];

  $items['content-sync-reset-status-entities'] = [
    'description' => dt('Reset the status entities for a specific or all pool/s.'),
    'aliases' => ['csrse'],
    'options' => [
      'pool_id' => 'The machine name of the pool the status entities should be reset for.',
    ],
    'examples' => [
      'drush csrse' => 'Reset all status entities for all pools',
      'drush csrse --flow_id="example_pool"' => 'Reset all status entities for the "example_pool".',
    ],
  ];

  $items['content-sync-check-entity-flags'] = [
    'description' => dt('Check the flags for an entity.'),
    'aliases' => ['cscef'],
    'arguments' => [
      'entity_uuid' => 'The related entities uuid.',
    ],
    'options' => [
      'flag' => 'The flag to check for, allowed values are: FLAG_IS_SOURCE_ENTITY, FLAG_EXPORT_ENABLED, FLAG_DEPENDENCY_EXPORT_ENABLED, FLAG_EDIT_OVERRIDE, FLAG_USER_ALLOWED_EXPORT, FLAG_DELETED',
    ],
    'examples' => [
      'drush cscef 16cc0d54-d93d-45b8-adf2-071de9d2d32b' => 'Get all flags for the entity having the uuid = "16cc0d54-d93d-45b8-adf2-071de9d2d32b".',
      'drush cscef 16cc0d54-d93d-45b8-adf2-071de9d2d32b --flag="FLAG_EDIT_OVERRIDE"' => 'Check if the entity having the uuid = "16cc0d54-d93d-45b8-adf2-071de9d2d32b" is overridden locally.',
    ],
  ];

  return $items;
}

/**
 * Export configuration to the Content Sync backend.
 *
 * @options force Whether to ignore that another site is already using the
 *     same site ID. Useful if you change the URL of a site.
 *
 * @throws \Exception
 */
function drush_cms_content_sync_content_sync_configuration_export($force = FALSE) {
  $force = drush_get_option('force', FALSE);
  drush_print('Started validation of pools.');
  // As Flows also export all used Pools, this is just to check that the configured site ID is valid in length.
  foreach (Pool::getAll() as $pool) {
    $exporter = new SyncCorePoolExport($pool);

    if (!$force && $exporter->siteIdExists()) {
      throw new \Exception('Another site is already using this site ID for the pool ' . $pool->id);
    }
  }
  drush_print('Finished validation of pools.');

  drush_print('Started export of flows and their pools.');
  foreach (Flow::getAll() as $flow) {
    $exporter = new SyncCoreFlowExport($flow);
    $steps    = $exporter->prepareBatch();
    foreach ($steps as $step) {
      $exporter->executeBatch($step);
    }
  }
  drush_print('Finished export of flows and their pools.');
}

/**
 * Kindly ask the Sync Core to login again.
 */
function drush_cms_content_sync_content_sync_sync_core_login() {
  drush_print('Calling /login for all connections.');

  $result = [];
  foreach (Flow::getAll() as $flow) {
    $exporter = new SyncCoreFlowExport($flow);
    $result = array_merge($result, $exporter->login());
  }

  foreach ($result as $url => $success) {
    if (!$success) {
      $url = preg_replace('%https?://(.*)@(.*)$%', '$2', $url);
      drush_print('FAILED to login from ' . $url);
    }
  }

  drush_print('Finished login.');
}

/**
 * Pull entities from the Sync Core.
 *
 * @param string $flow_id
 * @param bool $force
 *
 * @internal param array $options
 */
function drush_cms_content_sync_content_sync_pull_entities($flow_id = '', $force = FALSE) {

  $flow_id = drush_get_option('flow_id', '');
  $flow_id = empty($flow_id) ? NULL : $flow_id;

  $force = drush_get_option('force', FALSE);

  $flows = Flow::getAll();
  $client = \Drupal::httpClient();

  foreach ($flows as $id => $flow) {
    if ($flow_id && $id != $flow_id) {
      continue;
    }

    $exporter = new SyncCoreFlowExport($flow);

    $result = $exporter->startSync(FALSE, $force);

    if (empty($result)) {
      drush_print('No automated import configured for Flow: ' . $flow->label());
      continue;
    }

    drush_print('Started pull of entities for Flow: ' . $flow->label());

    foreach ($result as $url => $response) {
      if (!$response) {
        drush_print('> Failed to pull! Sync URL: ' . $url);
        continue;
      }

      if (empty($response['total'])) {
        drush_print('> Nothing to do for: ' . $url);
        continue;
      }

      $url .= '/synchronize/' . $response['id'] . '/status';

      $goal = $response['total'];
      $progress = 0;

      while ($progress < $goal) {
        if ($progress > 0) {
          sleep(5);
        }

        $body = json_decode($client->get($url)->getBody(), TRUE);

        $progress = $body['processed'];
        if ($progress == $goal) {
          drush_print('> Pulled ' . $goal . ' entities.');
        }
        elseif ($progress == 0) {
          sleep(5);
        }
        else {
          drush_print('Pulled ' . $progress . ' of ' . $goal . ' entities: ' . floor($progress / $goal * 100) . '%');
        }
      }
    }
  }
}

/**
 * Reset the status entities for a specific or all pool/s.
 */
function drush_cms_content_sync_content_sync_reset_status_entities($pool_id = '') {

  $pool_id = drush_get_option('pool_id', '');
  $pool_id = empty($pool_id) ? NULL : $pool_id;

  if (empty($pool_id)) {
    drush_print('Are you sure you want to reset the status entities for all pools?');
  }
  else {
    drush_print('Are you sure you want to reset the status entities for the pool: ' . $pool_id . '?');
  }

  drush_print('By resetting the status of all entities, the date of the last import and the date of the last export will be reset. The dates will no longer be displayed until the content is imported or exported again and all entities will be exported / imported again at the next synchronization regardless of whether they have changed or not..');

  if (drush_confirm('Do you want to continue?')) {
    empty($pool_id) ? Pool::resetStatusEntities() : Pool::resetStatusEntities($pool_id);
    drush_print('Status entities have been reset and entity caches are invalidated.');
  }
  else {
    drush_user_abort();
  }
}

/**
 * Check the flags for an entity.
 */
function drush_cms_content_sync_content_sync_check_entity_flags($entity_uuid) {
  $entity_status = \Drupal::entityTypeManager()->getStorage('cms_content_sync_entity_status')->loadByProperties(['entity_uuid' => $entity_uuid]);
  if (empty($entity_status)) {
    drush_print('There is no status entity existent yet for this UUID.');
  }
  else {
    foreach ($entity_status as $status) {
      $flag = drush_get_option('flag');
      $result = '';
      drush_print('Flow: ' . $status->get('flow')->value);

      if (empty($flag)) {
        $result .= 'FLAG_IS_SOURCE_ENTITY: ' . ($status->isSourceEntity() ? 'TRUE' : 'FALSE') . PHP_EOL;
        $result .= 'FLAG_EXPORT_ENABLED: ' . ($status->isExportEnabled() ? 'TRUE' : 'FALSE') . PHP_EOL;
        $result .= 'FLAG_DEPENDENCY_EXPORT_ENABLED: ' . ($status->isDependencyExportEnabled() ? 'TRUE' : 'FALSE') . PHP_EOL;
        $result .= 'FLAG_EDIT_OVERRIDE: ' . ($status->isOverriddenLocally() ? 'TRUE' : 'FALSE') . PHP_EOL;
        $result .= 'FLAG_USER_ALLOWED_EXPORT: ' . ($status->didUserAllowExport() ? 'TRUE' : 'FALSE') . PHP_EOL;
        $result .= 'FLAG_DELETED: ' . ($status->isDeleted() ? 'TRUE' : 'FALSE') . PHP_EOL;
      }
      else {
        switch ($flag) {
          case 'FLAG_IS_SOURCE_ENTITY':
            $status->isSourceEntity() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;

          case 'FLAG_EXPORT_ENABLED':
            $status->isExportEnabled() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;

          case 'FLAG_DEPENDENCY_EXPORT_ENABLED':
            $status->isDependencyExportEnabled() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;

          case 'FLAG_EDIT_OVERRIDE':
            $status->isOverriddenLocally() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;

          case 'FLAG_USER_ALLOWED_EXPORT':
            $status->didUserAllowExport() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;

          case 'FLAG_DELETED':
            $status->isDeleted() ? $result .= 'TRUE' : $result .= 'FALSE';
            break;
        }
      }
      drush_print($result);
    }
  }
}
