<?php

namespace Drupal\cms_content_sync_developer\Commands;

use Drupal\cms_content_sync\Entity\Flow;
use Drush\Commands\DrushCommands;

/**
 * CMS Content Sync Developer Drush Commands.
 */
class CMSContentSyncDeveloperCommands extends DrushCommands {

  /**
   * Export the configuration to the Sync Core.
   *
   * @command cms_content_sync_developer:update-flows
   * @aliases csuf
   */
  public function configuration_export() {
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

    $this->output()->writeln('Flows updated');
  }

}
