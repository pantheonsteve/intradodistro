<?php

namespace Drupal\cms_content_sync_health\Controller;

use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncCore\SyncCore;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\Pool;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\update\UpdateFetcher;

/**
 * Provides a listing of Flow.
 */
class SyncHealth extends ControllerBase {

  /**
   * Formats a database log message.
   *
   * @param object $row
   *   The record from the watchdog table. The object properties are: wid, uid,
   *   severity, type, timestamp, message, variables, link, name.
   *
   * @return string|\Drupal\Core\StringTranslation\TranslatableMarkup|false
   *   The formatted log message or FALSE if the message or variables properties
   *   are not set.
   */
  protected static function formatMessage($row) {
    // Check for required properties.
    if (isset($row->message, $row->variables)) {
      $variables = @unserialize($row->variables);
      // Messages without variables or user specified text.
      if ($variables === NULL) {
        $message = Xss::filterAdmin($row->message);
      }
      elseif (!is_array($variables)) {
        $message = \t('Log data is corrupted and cannot be unserialized: @message', ['@message' => Xss::filterAdmin($row->message)]);
      }
      // Message to translate with injected variables.
      else {
        $message = \t(Xss::filterAdmin($row->message), $variables);
      }
    }
    else {
      $message = FALSE;
    }
    return $message;
  }

  /**
   * Count status entities with the given flag.
   *
   * @param int $flag
   *   See EntityStatus::FLAG_*.
   * @param array $details
   *   Search the 'data' column to contain the given $value and save it in the result array at $key.
   *
   * @return array The counts, always having 'total'=>... and optionally the counts given by $details.
   */
  protected function countStatusEntitiesWithFlag($flag, $details = []) {
    $result['total'] = \Drupal::database()->select('cms_content_sync_entity_status')
      ->where('flags&:flag=:flag', [':flag' => $flag])
      ->countQuery()
      ->execute()
      ->fetchField();

    if ($result['total']) {
      foreach ($details as $name => $search) {
        $search = '%' . \Drupal::database()->escapeLike($search) . '%';
        $result[$name] = \Drupal::database()->select('cms_content_sync_entity_status')
          ->where('flags&:flag=:flag', [':flag' => $flag])
          ->condition('data', $search, 'LIKE')
          ->countQuery()
          ->execute()
          ->fetchField();
      }
    }

    return $result;
  }

  /**
   *
   */
  protected function getLocalLogMessages($levels, $count = 10) {
    $result = [];

    $connection = \Drupal::database();

    $query = $connection
      ->select('watchdog', 'w')
      ->fields('w', ['timestamp', 'severity', 'message', 'variables'])
      ->orderBy('timestamp', 'DESC')
      ->range(0, $count)
      ->condition('type', 'cms_content_sync')
      ->condition('severity', $levels, 'IN');
    $query = $query->execute();
    $rows = $query->fetchAll();
    foreach ($rows as $res) {
      $message =
        '<em>' .
        \Drupal::service('date.formatter')->format($res->timestamp, 'long') .
        '</em> ' .
        self::formatMessage($res)->render();

      $result[] = $message;
    }

    $result = SyncCore::obfuscateCredentials($result);

    return $result;
  }

  /**
   * Filter the given messages to only display those related to this site.
   *
   * @param array[] $messages
   *
   * @return array[]
   */
  protected function filterSyncCoreLogMessages($messages) {
    $result = [];

    $allowed_prefixes = [];
    foreach (Pool::getAll() as $pool) {
      $allowed_prefixes[] = 'drupal-' . $pool->id() . '-' . $pool->getSiteId() . '-';
    }

    foreach ($messages as $msg) {
      if (!isset($msg['connection_id'])) {
        continue;
      }

      $keep = FALSE;

      foreach ($allowed_prefixes as $allowed) {
        if (substr($msg['connection_id'], 0, strlen($allowed)) == $allowed) {
          $keep = TRUE;
          break;
        }
      }

      if ($keep) {
        $result[] = $msg;
      }
    }

    return array_slice($result, -20);
  }

  /**
   * Render the overview page.
   *
   * @return array
   */
  public function overview() {
    $sync_cores = [];
    foreach (SyncCore::getAll() as $host => $core) {
      $status = $core->getStatus();
      $status['error_log'] = $this->filterSyncCoreLogMessages($core->getLog(SyncCore::LOG_LEVEL_ERROR));
      $status['warning_log'] = $this->filterSyncCoreLogMessages($core->getLog(SyncCore::LOG_LEVEL_WARNING));
      $sync_cores[$host] = $status;
    }

    $module_info = system_get_info('module', 'cms_content_sync');

    $moduleHandler = \Drupal::service('module_handler');
    if ($moduleHandler->moduleExists('update')) {
      $config_factory = \Drupal::service('config.factory');
      $updates = new UpdateFetcher($config_factory, \Drupal::httpClient());
      $available = $updates->fetchProjectData([
        'name' => 'cms_content_sync',
        'info' => $module_info,
        'includes' => [],
        'project_type' => 'module',
        'project_status' => TRUE,
      ]);
      preg_match_all('@<version>\s*8.x-([0-9]+)\.([0-9]+)\s*</version>@i', $available, $versions, PREG_SET_ORDER);
      $newest_major = 0;
      $newest_minor = 0;
      foreach ($versions as $version) {
        if ($version[1] > $newest_major) {
          $newest_major = $version[1];
          $newest_minor = $version[2];
        }
        elseif ($version[1] == $newest_major && $version[2] > $newest_minor) {
          $newest_minor = $version[2];
        }
      }
      $newest_version = $newest_major . '.' . $newest_minor;
    }
    else {
      $newest_version = NULL;
    }

    if (isset($module_info['version'])) {
      $module_version = $module_info['version'];
      $module_version = preg_replace('@^\d\.x-(.*)$@', '$1', $module_version);
      if ($module_version != $newest_version) {
        if ($newest_version) {
          \drupal_set_message(t('There\'s an update available! The newest module version is @newest, yours is @current.', ['@newest' => $newest_version, '@current' => $module_version]));
        }
        else {
          \drupal_set_message(t('Please enable the "update" module to see if you\'re running the latest Content Sync version.'));
        }
      }
    }
    else {
      $module_version = NULL;
      if ($newest_version) {
        \drupal_set_message(t('You\'re running a dev release. The newest module version is @newest.', ['@newest' => $newest_version]));
      }
    }

    $export_failures_hard = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_EXPORT_FAILED);

    $export_failures_soft = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_EXPORT_FAILED_SOFT);

    $import_failures_hard = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_IMPORT_FAILED);

    $import_failures_soft = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_IMPORT_FAILED_SOFT);

    $version_differences['local'] = $this->getLocalVersionDifferences();

    $version_differences_remote = _cms_content_sync_display_all_entity_type_differences();
    $version_differences['remote'] = _cms_content_sync_display_entity_type_differences_recursively_render($version_differences_remote);

    $moduleHandler = \Drupal::service('module_handler');
    $dblog_enabled = $moduleHandler->moduleExists('dblog');
    if ($dblog_enabled) {
      $site_log_disabled = FALSE;
      $error_log = $this->getLocalLogMessages([
        RfcLogLevel::EMERGENCY,
        RfcLogLevel::ALERT,
        RfcLogLevel::CRITICAL,
        RfcLogLevel::ERROR,
      ]);
      $warning_log = $this->getLocalLogMessages([
        RfcLogLevel::WARNING,
      ]);
    }
    else {
      $site_log_disabled = TRUE;
      $error_log = NULL;
      $warning_log = NULL;
    }

    return [
      '#theme' => 'cms_content_sync_sync_health_overview',
      '#sync_cores' => $sync_cores,
      '#module_version' => $module_version,
      '#newest_version' => $newest_version,
      '#export_failures_hard' => $export_failures_hard,
      '#export_failures_soft' => $export_failures_soft,
      '#import_failures_hard' => $import_failures_hard,
      '#import_failures_soft' => $import_failures_soft,
      '#version_differences' => $version_differences,
      '#error_log' => $error_log,
      '#warning_log' => $warning_log,
      '#site_log_disabled' => $site_log_disabled,
    ];
  }

  /**
   *
   */
  protected function countStaleEntities() {
    $checked = [];
    $count = 0;

    foreach (Flow::getAll() as $flow) {
      foreach ($flow->getEntityTypeConfig(NULL, NULL, TRUE) as $id => $config) {
        if (in_array($id, $checked)) {
          continue;
        }

        if ($config['export'] != ExportIntent::EXPORT_AUTOMATICALLY) {
          continue;
        }

        if (!in_array(Pool::POOL_USAGE_FORCE, array_values($config['export_pools']))) {
          continue;
        }

        $checked[] = $id;

        $type_name = $config['entity_type_name'];
        $bundle_name = $config['bundle_name'];

        /**
         * @var \Drupal\Core\Entity\EntityTypeManager $entityTypeManager
         */
        $entityTypeManager = \Drupal::service('entity_type.manager');
        $type = $entityTypeManager->getDefinition($type_name);

        $query = \Drupal::database()->select($type->getBaseTable(), 'e');
        $query
          ->leftJoin('cms_content_sync_entity_status', 's', 'e.uuid=s.entity_uuid AND s.entity_type=:type', [':type' => $type_name]);
        $result = $query
          ->isNull('s.id')
          ->condition('e.' . $type->getKey('bundle'), $bundle_name)
          ->countQuery()
          ->execute();
        $count += (int) $result
          ->fetchField();
      }
    }

    return $count;
  }

  /**
   *
   */
  protected function getLocalVersionDifferences() {
    $result = [];

    foreach (Flow::getAll() as $flow) {
      foreach ($flow->getEntityTypeConfig(NULL, NULL, TRUE) as $id => $config) {
        $type_name = $config['entity_type_name'];
        $bundle_name = $config['bundle_name'];
        $version = $config['version'];

        $current = Flow::getEntityTypeVersion($type_name, $bundle_name);

        if ($version == $current) {
          continue;
        }

        $result[] = $flow->label() . ' uses entity type  ' . $type_name . '.' . $bundle_name . ' with version ' . $version . '. Current version is ' . $current . '. Please update the Flow.';
      }
    }

    return $result;
  }

  /**
   *
   */
  protected function countEntitiesWithChangedVersionForExport() {
    $checked = [];
    $versions = [];
    $types = [];

    foreach (Flow::getAll() as $flow) {
      foreach ($flow->getEntityTypeConfig(NULL, NULL, TRUE) as $id => $config) {
        if (in_array($id, $checked)) {
          continue;
        }

        $checked[] = $id;

        $type_name = $config['entity_type_name'];
        $version = $config['version'];
        if (!in_array($type_name, $types)) {
          $types[] = $type_name;
        }
        $versions[] = $version;
      }
    }

    $count = \Drupal::database()->select('cms_content_sync_entity_status')
      ->condition('entity_type', $types, 'IN')
      ->condition('entity_type_version', $versions, 'NOT IN')
      ->where('flags&:flag=:flag', [':flag' => EntityStatus::FLAG_IS_SOURCE_ENTITY])
      ->countQuery()
      ->execute()
      ->fetchField();

    return $count;
  }

  /**
   *
   */
  protected function countEntitiesWaitingForExport() {
    return 0;
  }

  /**
   * Render the overview page.
   *
   * @return array
   */
  public function export() {
    $export_failures_hard = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_EXPORT_FAILED, [
      'request_failed' => ExportIntent::EXPORT_FAILED_REQUEST_FAILED,
      'invalid_status_code' => ExportIntent::EXPORT_FAILED_REQUEST_INVALID_STATUS_CODE,
      'dependency_export_failed' => ExportIntent::EXPORT_FAILED_DEPENDENCY_EXPORT_FAILED,
      'internal_error' => ExportIntent::EXPORT_FAILED_INTERNAL_ERROR,
    ]);

    $export_failures_soft = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_EXPORT_FAILED_SOFT, [
      'handler_denied' => ExportIntent::EXPORT_FAILED_HANDLER_DENIED,
      'unchanged' => ExportIntent::EXPORT_FAILED_UNCHANGED,
    ]);

    $pending = [
      'stale_entities' => $this->countStaleEntities(),
      'version_changed' => $this->countEntitiesWithChangedVersionForExport(),
      'manual_export' => $this->countEntitiesWaitingForExport(),
    ];

    return [
      '#theme' => 'cms_content_sync_sync_health_export',
      '#export_failures_hard' => $export_failures_hard,
      '#export_failures_soft' => $export_failures_soft,
      '#pending' => $pending,
    ];
  }

  /**
   * Render the overview page.
   *
   * @return array
   */
  public function import() {
    $import_failures_hard = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_IMPORT_FAILED, [
      'different_version' => ImportIntent::IMPORT_FAILED_DIFFERENT_VERSION,
      'sync_error' => ImportIntent::IMPORT_FAILED_CONTENT_SYNC_ERROR,
      'internal_error' => ImportIntent::IMPORT_FAILED_INTERNAL_ERROR,
    ]);

    $import_failures_soft = $this->countStatusEntitiesWithFlag(EntityStatus::FLAG_IMPORT_FAILED_SOFT, [
      'handler_denied' => ImportIntent::IMPORT_FAILED_HANDLER_DENIED,
      'no_flow' => ImportIntent::IMPORT_FAILED_NO_FLOW,
      'unknown_pool' => ImportIntent::IMPORT_FAILED_UNKNOWN_POOL,
    ]);

    return [
      '#theme' => 'cms_content_sync_sync_health_import',
      '#import_failures_hard' => $import_failures_hard,
      '#import_failures_soft' => $import_failures_soft,
    ];
  }

}
