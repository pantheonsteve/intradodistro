<?php

namespace Drupal\cms_content_sync\Commands;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\cms_content_sync\SyncCoreFlowExport;
use Drupal\cms_content_sync\SyncCorePoolExport;
use Drush\Commands\DrushCommands;
use Drush\Exceptions\UserAbortException;
use Symfony\Component\Console\Input\InputOption;

/**
 * CMS Content Sync Drush Commands.
 */
class CMSContentSyncCommands extends DrushCommands {

  /**
   * Export the configuration to the Sync Core.
   *
   * @command cms_content_sync:configuration-export
   * @aliases cse csce
   * @options force Whether to ignore that another site is already using the
   *     same site ID. Useful if you change the URL of a site.
   *
   * @throws \Exception
   */
  public function configuration_export($options = ['force' => FALSE]) {
    $this->output()->writeln('Started export of pools.');
    foreach (Pool::getAll() as $pool) {
      $exporter = new SyncCorePoolExport($pool);

      if (!$options['force'] && $exporter->siteIdExists()) {
        throw new \Exception('Another site is already using the site ID "' . $pool->getSiteId() . '" for the pool "' . $pool->id . '"');
      }

      $steps = $exporter->prepareBatch();
      foreach ($steps as $step) {
        $exporter->executeBatch($step);
      }
    }
    $this->output()->writeln('Finished export of pools.');
    $this->output()->writeln('Started export of flows.');
    foreach (Flow::getAll() as $flow) {
      $exporter = new SyncCoreFlowExport($flow);
      $steps    = $exporter->prepareBatch();
      foreach ($steps as $step) {
        $exporter->executeBatch($step);
      }
    }
    $this->output()->writeln('Finished export of flows.');
  }

  /**
   * Kindly ask the Sync Core to login again.
   *
   * @command cms_content_sync:sync-core-login
   * @aliases csscl
   */
  public function sync_core_login() {
    $this->output()->writeln('Calling /login for all connections.');

    $result = [];
    foreach (Flow::getAll() as $flow) {
      $exporter = new SyncCoreFlowExport($flow);
      $result = array_merge($result, $exporter->login());
    }

    foreach ($result as $url => $success) {
      if (!$success) {
        $url = preg_replace('%https?://(.*)@(.*)$%', '$2', $url);
        $this->output()->writeln('FAILED to login from ' . $url);
      }
    }

    $this->output()->writeln('Finished login.');
  }

  /**
   * Kindly ask the Sync Core to pull all entities.
   *
   * @param array $options
   *
   * @command cms_content_sync:pull-entities
   * @aliases cspe
   * @options flow_id The flow the entities should be pulled from.
   * @options force Also update entities which have already been imported.
   * @usage cms_content_sync:content-sync-pull
   *   Pulls all entities from all flows.
   * @usage cms_content_sync:pull-entities --flow_id='example_flow'
   *   Pulls all entities from the example flow.
   * œusage cms_content_sync:pull-entities --flow_id='example_flow' --force
   *   Pull all entities from the "example_flow" and force entities which already have been imported to be updated as well.
   */
  public function pull_entities($options = ['flow_id' => InputOption::VALUE_REQUIRED, 'force' => FALSE]) {

    $flow_id = empty($options['flow_id']) ? NULL : $options['flow_id'];
    $force = $options['force'];

    $flows = Flow::getAll();
    $client = \Drupal::httpClient();

    foreach ($flows as $id => $flow) {
      if ($flow_id && $id != $flow_id) {
        continue;
      }

      $exporter = new SyncCoreFlowExport($flow);

      $result = $exporter->startSync(FALSE, $force);

      if (empty($result)) {
        $this->output()->writeln('No automated import configured for Flow: ' . $flow->label());
        continue;
      }

      $this->output()->writeln('Started pull of entities for Flow: ' . $flow->label());

      foreach ($result as $url => $response) {
        if (!$response) {
          $this->output()->writeln('> Failed to pull! Sync URL: ' . $url);
          continue;
        }

        if (empty($response['total'])) {
          $this->output()->writeln('> Nothing to do for: ' . $url);
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
            $this->output()->writeln('> Pulled ' . $goal . ' entities.');
          }
          elseif ($progress == 0) {
            sleep(5);
          }
          else {
            $this->output()->writeln('Pulled ' . $progress . ' of ' . $goal . ' entities: ' . floor($progress / $goal * 100) . '%');
          }
        }
      }
    }
  }

  /**
   * Reset the status entities for a specific or all pool/s.
   *
   * @param array $options
   *
   * @command cms_content_sync:reset-status-entities
   * @aliases csrse
   * @options pool_id The machine name of the pool the status entities should be reset for.
   * @usage cms_content_sync:reset-status-entities
   *   Reset all status entities for all pools.
   * @usage cms_content_sync:reset-status-entities --pool_id='example_pool'
   *   Reset all status entities for the "example_pool".
   *
   * @throws \Drush\Exceptions\UserAbortException.
   */
  public function reset_status_entities($options = ['pool_id' => InputOption::VALUE_OPTIONAL]) {
    $pool_id = empty($options['pool_id']) ? NULL : $options['pool_id'];
    if (empty($pool_id)) {
      $this->output()->writeln(dt('Are you sure you want to reset the status entities for all pools?'));
    }
    else {
      $this->output()->writeln(dt('Are you sure you want to reset the status entities for the pool: ' . $pool_id . '?'));
    }
    $this->output()->writeln(dt('By resetting the status of all entities, the date of the last import and the date of the last export will be reset. The dates will no longer be displayed until the content is imported or exported again and all entities will be exported / imported again at the next synchronization regardless of whether they have changed or not..'));

    if (!$this->io()->confirm(dt('Do you want to continue?'))) {
      throw new UserAbortException();
    }

    empty($pool_id) ? Pool::resetStatusEntities() : Pool::resetStatusEntities($pool_id);
    $this->output()->writeln('Status entities have been reset and entity caches are invalidated.');
  }

  /**
   * Check the flags for an entity.
   *
   * @param array $options
   *
   * @command cms_content_sync:check-entity-flags
   * @aliases cscef
   * @options flow_id The related entities uuid.
   * @options flag The flag to check for, allowed values are: FLAG_IS_SOURCE_ENTITY, FLAG_EXPORT_ENABLED, FLAG_DEPENDENCY_EXPORT_ENABLED, FLAG_EDIT_OVERRIDE, FLAG_USER_ALLOWED_EXPORT, FLAG_DELETED
   * @usage cms_content_sync:check-entity-flags --entity_uuid="16cc0d54-d93d-45b8-adf2-071de9d2d32b"
   *   Get all flags for the entity having the uuid = "16cc0d54-d93d-45b8-adf2-071de9d2d32b".
   * @usage cms_content_sync:check-entity-flags --entity_uuid="16cc0d54-d93d-45b8-adf2-071de9d2d32b" --flag="FLAG_EDIT_OVERRIDE"
   *   Check if the entity having the uuid = "16cc0d54-d93d-45b8-adf2-071de9d2d32b" is overridden locally.
   */
  public function check_entity_flags($options = ['entity_uuid' => InputOption::VALUE_REQUIRED, 'flag' => InputOption::VALUE_OPTIONAL]) {
    $entity_uuid = empty($options['entity_uuid']) ? NULL : $options['entity_uuid'];
    $flag = empty($options['flag']) ? NULL : $options['flag'];

    $entity_status = \Drupal::entityTypeManager()->getStorage('cms_content_sync_entity_status')->loadByProperties(['entity_uuid' => $entity_uuid]);
    if (empty($entity_status)) {
      $this->output()->writeln(dt('There is no status entity existent yet for this UUID.'));
    }
    else {
      foreach ($entity_status as $status) {
        $result = '';
        $this->output()->writeln(dt('Flow: ' . $status->get('flow')->value));

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
        $this->output()->writeln(dt($result));
      }
    }
  }

}
