<?php

namespace Drupal\cms_content_sync\Entity;

use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Entity\EntityChangedInterface;

/**
 * Provides an interface for defining Sync entity entities.
 *
 * @ingroup cms_content_sync_entity_status
 */
interface EntityStatusInterface extends ContentEntityInterface, EntityChangedInterface {

}
