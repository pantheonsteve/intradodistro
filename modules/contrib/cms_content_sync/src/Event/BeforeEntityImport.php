<?php

namespace Drupal\cms_content_sync\Event;

use Drupal\cms_content_sync\ImportIntent;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * An entity is being imported.
 * Modules can use this to append additional field values or process other
 * information for different use cases.
 */
class BeforeEntityImport extends Event {

  const EVENT_NAME = 'cms_content_sync.entity.import.before';

  /**
   * Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  public $entity;

  /**
   * @var intent
   */
  public $intent;

  /**
   * Constructs a entity export event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   */
  public function __construct(EntityInterface $entity, ImportIntent $intent) {
    $this->entity = $entity;
    $this->intent = $intent;
  }

}
