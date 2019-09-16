<?php

namespace Drupal\lightning_core\Commands;

use Drush\Commands\DrushCommands;

/**
 * Implements Drush command hooks.
 */
class Hooks extends DrushCommands {

  /**
   * Clears all caches before database updates begin.
   *
   * A common cause of errors during database updates is update hooks
   * inadvertently using stale data from the myriad caches in Drupal core and
   * contributed modules. Clearing all caches before updates begin ensures that
   * the system always has the freshest and most accurate data to work with,
   * which is especially helpful during major surgery like a database update.
   *
   * @hook pre-command updatedb
   */
  public function preUpdate() {
    drupal_flush_all_caches();
  }

}
