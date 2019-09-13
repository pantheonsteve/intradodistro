<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_user_reference_handler",
 *   label = @Translation("Default User Reference"),
 *   weight = 80
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultUserReferenceHandler extends DefaultEntityReferenceHandler {

  const IDENTIFICATION_NAME = 'name';
  const IDENTIFICATION_EMAIL = 'mail';

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    if (!in_array($field->getType(), ["entity_reference", "entity_reference_revisions"])) {
      return FALSE;
    }

    $type = $field->getSetting('target_type');
    return $type == 'user';
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [
      'identification' => [
        '#type' => 'select',
        '#title' => 'Identification',
        '#options' => [
          self::IDENTIFICATION_EMAIL => 'Mail',
          self::IDENTIFICATION_NAME => 'Name',
        ],
        '#default_value' => isset($this->settings['handler_settings']['identification']) && $this->settings['handler_settings']['identification'] === self::IDENTIFICATION_NAME ? self::IDENTIFICATION_NAME : self::IDENTIFICATION_EMAIL,
      ],
    ];

    return $options;
  }

  /**
   * @inheritdoc
   */
  protected function loadReferencedEntity(ImportIntent $intent, $definition) {
    $property = $this->settings['handler_settings']['identification'];

    if (empty($definition[$property])) {
      return NULL;
    }

    /**
     * @var \Drupal\user\Entity\User[] $entities
     */
    $entities = \Drupal::entityTypeManager()
      ->getStorage('user')
      ->loadByProperties([$property => $definition[$property]]);

    return reset($entities);
  }

  /**
   * @inheritdoc
   */
  protected function serializeReference(ExportIntent $intent, EntityInterface $reference, $value) {
    return [
      self::IDENTIFICATION_EMAIL => $reference->get('mail')->value,
      self::IDENTIFICATION_NAME  => $reference->get('name')->value,
    ];
  }

}
