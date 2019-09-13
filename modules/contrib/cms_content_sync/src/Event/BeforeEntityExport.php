<?php

namespace Drupal\cms_content_sync\Event;

use Drupal\cms_content_sync\ExportIntent;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * An entity is about to be exported.
 * Other modules can use this to interact with the export, primarily to add,
 * change or remove field values.
 */
class BeforeEntityExport extends Event {

  const EVENT_NAME = 'cms_content_sync.entity.export.before';

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
   * Constructs a extend entity export event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\cms_content_sync\ExportIntent $intent
   */
  public function __construct(EntityInterface $entity, ExportIntent $intent) {
    $this->entity = $entity;
    $this->intent = $intent;
  }

}
