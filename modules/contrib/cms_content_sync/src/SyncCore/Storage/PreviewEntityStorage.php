<?php

namespace Drupal\cms_content_sync\SyncCore\Storage;

/**
 * Class PreviewEntityStorage
 * Implement Storage for the Sync Core "Preview" entity type.
 *
 * @package Drupal\cms_content_sync\SyncCore\Storage
 */
class PreviewEntityStorage extends Storage {

  /**
   * @var string EXTERNAL_PREVIEW_PATH
   *   The path to find the preview entities at.
   */
  const EXTERNAL_PREVIEW_PATH = 'drupal/cms-content-sync/preview';

  /**
   * @var string PREVIEW_ENTITY_ID
   *   The entity type ID from Sync Core used to store preview entities as.
   */
  const PREVIEW_ENTITY_ID = 'drupal-synchronization-entity_preview-0_1';

  /**
   * @var string PREVIEW_CONNECTION_ID
   *   The unique connection ID in Sync Core used to store preview entities at.
   */
  const ID = 'drupal_cms-content-sync_preview';

  /**
   * @var string PREVIEW_ENTITY_VERSION
   *   The preview entity version (see above).
   */
  const PREVIEW_ENTITY_VERSION = '0.1';

  /**
   * @inheritdoc
   */
  public function getId() {
    return self::ID;
  }

  /**
   * @inheritdoc
   */
  public function getPath() {
    return '/' . self::EXTERNAL_PREVIEW_PATH;
  }

}
