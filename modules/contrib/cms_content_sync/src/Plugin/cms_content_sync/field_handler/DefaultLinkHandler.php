<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Url;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_link_handler",
 *   label = @Translation("Default Link"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultLinkHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = ["link"];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [];

    $options['export_as_absolute_url'] = [
      '#type' => 'checkbox',
      '#title' => 'Export as absolute URL',
      '#default_value' => $this->settings['handler_settings']['export_as_absolute_url'] ?? FALSE,
    ];

    return array_merge(parent::getHandlerSettings(), $options);
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    if ($intent->shouldMergeChanges()) {
      return FALSE;
    }

    $data = $intent->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $result = [];

      foreach ($data as &$link_element) {
        if (empty($link_element['uri'])) {
          if (!empty($link_element[SyncIntent::ENTITY_TYPE_KEY]) && !empty($link_element[SyncIntent::BUNDLE_KEY])) {
            $reference = $intent->loadEmbeddedEntity($link_element);
            if ($reference) {
              $result[] = [
                'uri' => 'entity:' . $reference->getEntityTypeId() . '/' . $reference->id(),
                'title' => $link_element['title'],
                'options' => $link_element['options'],
              ];
              if ($entity instanceof MenuLinkContent) {
                $intent->setField('enabled', [['value' => 1]]);
                $entity->set('enabled', 1);
              }
              elseif ($reference instanceof MenuLinkContent) {
                $reference->set('enabled', 1);
              }
            }
            // Menu items are created before the node as they are embedded
            // entities. For the link to work however the node must already
            // exist which won't work. So instead we're creating a temporary
            // uri that uses the entity UUID instead of it's ID. Once the node
            // is imported it will look for this link and replace it with the
            // now available entity reference by ID.
            elseif ($entity instanceof MenuLinkContent && $this->fieldName == 'link') {
              $result[] = [
                'uri' => 'internal:/' . $link_element[SyncIntent::ENTITY_TYPE_KEY] . '/' . $link_element[SyncIntent::UUID_KEY],
                'title' => $link_element['title'],
                'options' => $link_element['options'],
              ];
            }
          }
        }
        else {
          $result[] = [
            'uri'     => $link_element['uri'],
            'title'   => $link_element['title'],
            'options' => $link_element['options'],
          ];
        }
      }

      $entity->set($this->fieldName, $result);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $data = $entity->get($this->fieldName)->getValue();

    $absolute = !empty($this->settings['handler_settings']['export_as_absolute_url']);

    $result = [];

    foreach ($data as $key => $value) {
      $uri = &$data[$key]['uri'];
      // Find the linked entity and replace it's id with the UUID
      // References have following pattern: entity:entity_type/entity_id.
      preg_match('/^entity:(.*)\/(\d*)$/', $uri, $found);
      if (empty($found) || $absolute) {
        if ($absolute) {
          $uri = Url::fromUri($uri, ['absolute' => TRUE])->toString();
        }
        $result[] = [
          'uri'     => $uri,
          'title'   => isset($value['title']) ? $value['title'] : NULL,
          'options' => $value['options'],
        ];
      }
      else {
        $link_entity_type = $found[1];
        $link_entity_id   = $found[2];
        $entity_manager   = \Drupal::entityTypeManager();
        $link_entity      = $entity_manager->getStorage($link_entity_type)
          ->load($link_entity_id);

        if (empty($link_entity)) {
          continue;
        }

        if (!$this->flow->supportsEntity($link_entity)) {
          continue;
        }

        $result[] = $intent->getEmbedEntityDefinition(
          $link_entity->getEntityTypeId(),
          $link_entity->bundle(),
          $link_entity->uuid(),
          NULL,
          // @TODO Add option "auto export / import" just as reference fields do
          FALSE,
          [
            'title'   => $value['title'],
            'options' => $value['options'],
          ]
        );
      }
    }

    $intent->setField($this->fieldName, $result);

    return TRUE;
  }

}
