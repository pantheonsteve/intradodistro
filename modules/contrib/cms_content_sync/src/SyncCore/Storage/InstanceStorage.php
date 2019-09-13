<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class InstanceStorage
 * Implement Storage for the Sync Core "Instance" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class InstanceStorage extends Storage {
  const ID = 'api_unify-api_unify-instance-0_1';

  /**
   * @var string POOL_SITE_ID
   *   The virtual site id for the pool and it's connections / synchronizations.
   */
  const POOL_SITE_ID = '_pool';

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

}
