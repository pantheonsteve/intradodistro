<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class ParentCondition
 * A nested condition. This condition contains one or more child conditions.
 * These are just basic logical operators.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class ParentCondition extends Condition {

  /**
   * @var string MATCH_ALL
   *    All child conditions must match.
   */
  const MATCH_ALL = 'and';

  /**
   * @var string MATCH_ANY
   *    Any child condition must match.
   */
  const MATCH_ANY = 'or';

  /**
   * @var string MATCH_NONE
   *    None of the child conditions may match.
   */
  const MATCH_NONE = 'nor';

  /**
   * DataCondition constructor.
   *
   * @param string $operator
   * @param \Drupal\cms_content_sync\SyncCore\Condition[] $conditions
   *
   * @throws \Exception
   */
  public function __construct($operator, $conditions) {
    if (!in_array($operator, [
      self::MATCH_ALL,
      self::MATCH_ANY,
      self::MATCH_NONE,
    ])) {
      throw new \Exception('Unknown operator ' . $operator);
    }

    $this->condition['conditions'] = $conditions;

    parent::__construct($operator);
  }

  /**
   * @inheritdoc
   */
  public function toArray() {
    $result = $this->condition;

    /**
     * @var \Drupal\cms_content_sync\SyncCore\Condition $condition
     */
    foreach ($result['conditions'] as $i => $condition) {
      $result['conditions'][$i] = $condition->toArray();
    }

    return $result;
  }

  /**
   * Create an instance.
   *
   * @param string $operator
   * @param \Drupal\cms_content_sync\SyncCore\Condition[] $conditions
   *
   * @return \Drupal\cms_content_sync\SyncCore\ParentCondition
   *
   * @throws \Exception
   */
  public static function create($operator, $conditions) {
    return new ParentCondition($operator, $conditions);
  }

}
