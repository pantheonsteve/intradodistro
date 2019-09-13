<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class DataCondition
 * A condition that's applied to an individual field of the entity.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class DataCondition extends Condition {

  /**
   * @var string IS_EQUAL_TO
   *   The field must be equal to the value.
   */
  const IS_EQUAL_TO = '==';

  /**
   * @var string IS_NOT_EQUAL_TO
   *   The field must be different to the value.
   */
  const IS_NOT_EQUAL_TO = '!=';

  /**
   * @var string IS_GREATER_THAN
   *   The field must be greater than the value.
   */
  const IS_GREATER_THAN = '>';

  /**
   * @var string IS_LESS_THAN
   *   The field must be less than the value.
   */
  const IS_LESS_THAN = '<';

  /**
   * @var string IS_GREATER_THAN_OR_EQUAL_TO
   *   The field must be greater than or equal to the value.
   */
  const IS_GREATER_THAN_OR_EQUAL_TO = '>=';

  /**
   * @var string IS_LESS_THAN_OR_EQUAL_TO
   *   The field must be less than or equal to the value.
   */
  const IS_LESS_THAN_OR_EQUAL_TO = '<=';

  /**
   * @var string IS_IN
   *   The array field must contain the value.
   */
  const IS_IN = 'in';

  /**
   * @var string IS_NOT_IN
   *   The array field must not contain the value.
   */
  const IS_NOT_IN = 'not-in';

  /**
   * @var string MATCHES_REGEX
   *   The field must match the regular expression given as value.
   */
  const MATCHES_REGEX = 'regex';

  /**
   * DataCondition constructor.
   *
   * @param string $field
   *   The entity field to compare.
   * @param string $operator
   *   The operator (see constants above)
   * @param mixed $value
   *   The value to check against.
   *
   * @throws \Exception
   */
  public function __construct($field, $operator, $value) {
    if (!in_array($operator, [
      self::IS_EQUAL_TO,
      self::IS_NOT_EQUAL_TO,
      self::IS_GREATER_THAN,
      self::IS_LESS_THAN,
      self::IS_GREATER_THAN_OR_EQUAL_TO,
      self::IS_LESS_THAN_OR_EQUAL_TO,
      self::IS_IN,
      self::IS_NOT_IN,
      self::MATCHES_REGEX,
    ])) {
      throw new \Exception('Unknown operator ' . $operator);
    }

    $this->condition['values'] = [
      [
        'source' => 'data',
        'field' => $field,
      ],
      [
        'source' => 'value',
        'value' => $value,
      ],
    ];

    parent::__construct($operator);
  }

  /**
   * @param string $field
   *   The entity field to compare.
   * @param string $operator
   *   The operator (see constants above)
   * @param mixed $value
   *   The value to check against.
   *
   * @return \Drupal\cms_content_sync\SyncCore\DataCondition
   * @throws \Exception
   */
  public static function create($field, $operator, $value) {
    return new DataCondition($field, $operator, $value);
  }

}
