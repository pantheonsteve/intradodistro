<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class Condition
 * A condition in the format that the Sync Core expects. JSON'd and then used as
 * a query parameter at list requests.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
abstract class Condition {

  /**
   * @var array
   *    The actual condition object in a format as it's send to the Sync Core.
   */
  protected $condition = [];

  /**
   * Condition constructor.
   *
   * @param string $operator
   *   See subclasses.
   */
  public function __construct($operator) {
    $this->condition['operator'] = $operator;
  }

  /**
   * Get a flat array representation of this condition.
   *
   * @return array
   */
  public function toArray() {
    return $this->condition;
  }

  /**
   * Get a JSON version of the condition.
   *
   * @return string
   */
  public function serialize() {
    return json_encode($this->toArray());
  }

}
