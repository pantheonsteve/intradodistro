<?php

namespace Drupal\cms_content_sync\Event;

use Drupal\cms_content_sync\Entity\FlowInterface;
use Drupal\cms_content_sync\Entity\PoolInterface;
use Symfony\Component\EventDispatcher\Event;
use Drupal\Core\Entity\EntityInterface;

/**
 * The entity has been exported successfully.
 * Other modules can use this to react on successful export events.
 */
class AfterEntityExport extends Event {

  const EVENT_NAME = 'cms_content_sync.entity.export.after';

  /**
   * Entity.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The pool the entity got exported to.
   *
   * @var \Drupal\cms_content_sync\Entity\PoolInterface
   */
  protected $pool;

  /**
   * The flow that was used to export the entity.
   *
   * @var \Drupal\cms_content_sync\Entity\FlowInterface
   */
  protected $flow;

  /**
   * The reason the entity got exported.
   */
  protected $reason;

  /**
   * Action.
   */
  protected $action;

  /**
   * Constructs a entity export event.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\cms_content_sync\Entity\PoolInterface $pool
   * @param \Drupal\cms_content_sync\Entity\FlowInterface $flow
   * @param $reason
   * @param $action
   */
  public function __construct(EntityInterface $entity, PoolInterface $pool, FlowInterface $flow, $reason, $action) {
    $this->entity = $entity;
    $this->pool = $pool;
    $this->flow = $flow;
    $this->reason = $reason;
    $this->action = $action;
  }

  /**
   * Get the exported entity.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   */
  public function getEntity() {
    return $this->entity;
  }

}
