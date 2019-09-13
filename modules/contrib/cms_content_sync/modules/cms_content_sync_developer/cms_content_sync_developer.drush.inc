<?php

/**
 * @file
 * Contains Drush commands for CMS Content Sync.
 */

use Drupal\cms_content_sync\Entity\Flow;

/**
 * Implements hook_drush_command().
 */
function cms_content_sync_developer_drush_command() {
  $items['content-sync-update-flows'] = [
    'description' => dt('Update all flows'),
    'aliases' => ['csuf'],
  ];
  return $items;

}

/**
 * Update flows.
 *
 * @throws \Exception
 */
function drush_cms_content_sync_content_sync_update_flows() {

  $flows = Flow::getAll(FALSE);
  foreach ($flows as $flow) {

    // Get all entity type configurations.
    $entity_type_bundle_configs = $flow->getEntityTypeConfig(NULL, NULL, TRUE);

    // Update versions.
    foreach ($entity_type_bundle_configs as $config) {
      $flow->updateEntityTypeBundleVersion($config['entity_type_name'], $config['bundle_name']);
      $flow->resetVersionWarning();
    }
  }

  drush_print('Flows updated');
}
