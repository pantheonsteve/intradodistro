<?php

namespace Drupal\cms_content_sync_views\EventSubscriber;

use Drupal\Core\Field\FieldStorageDefinitionEvent;
use Drupal\Core\Field\FieldStorageDefinitionEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * React on field storage changes.
 */
class FieldStorageSubscriber implements EventSubscriberInterface {

  /**
   * If data for the entity status entities already exists, it gets migrated
   * to the dynamic reference field.
   *
   * @param \Drupal\Core\Field\FieldStorageDefinitionEvent $event
   *   The entity storage object.
   */
  public function onCreate(FieldStorageDefinitionEvent $event) {

    // Ensure to only react when the the entity field provided by this module
    // is added.
    $field_storage_definition = $event->getFieldStorageDefinition();
    $field_name = $field_storage_definition->getName();
    $provider = $field_storage_definition->getProvider();
    if ($field_name == 'entity' && $provider == 'cms_content_sync_views') {
      $entity_status = \Drupal::entityQuery('cms_content_sync_entity_status')->execute();
      if (!empty($entity_status)) {
        $status_entity_storage = $node_storage = \Drupal::entityTypeManager()->getStorage('cms_content_sync_entity_status');
        foreach ($entity_status as $entity_id) {
          $status_info_entity = $status_entity_storage->load($entity_id);
          $referenced_entity = \Drupal::service('entity.repository')
            ->loadEntityByUuid($status_info_entity->get('entity_type')->value, $status_info_entity->get('entity_uuid')->value);
          $status_info_entity->set('entity', $referenced_entity);
          $status_info_entity->save();
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[FieldStorageDefinitionEvents::CREATE][] = ['onCreate'];
    return $events;
  }

}
