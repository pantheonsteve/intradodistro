<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\ctools\Plugin\BlockPluginCollection;

/**
 * Providing a handler for the panelizer module.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_panelizer_field_handler",
 *   label = @Translation("Panelizer"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultPanelizerHandler extends FieldHandlerBase {

  const SUPPORTED_PROVIDERS = [
    'block_content',
    'ctools_block',
    'views',
    'system',
    'core',
    'language',
    'social_media',
  ];

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $allowed = [
      "panelizer",
    ];
    return in_array($field->getType(), $allowed) !== FALSE;
  }

  /**
   *
   */
  protected function shouldExportReferencedBlocks() {
    return (isset($this->settings['handler_settings']['export_referenced_custom_blocks']) && $this->settings['handler_settings']['export_referenced_custom_blocks'] === 0 ? 0 : 1);
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [
      'export_referenced_custom_blocks' => [
        '#type' => 'checkbox',
        '#title' => 'Export referenced custom blocks',
        '#default_value' => $this->shouldExportReferencedBlocks(),
      ],
    ];

    return $options;
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    if ($intent->shouldMergeChanges()) {
      return FALSE;
    }

    if ($this->settings['import'] != ImportIntent::IMPORT_AUTOMATICALLY) {
      return FALSE;
    }

    $data = $intent->getField($this->fieldName);

    if (empty($data)) {
      $entity->set($this->fieldName, NULL);
    }
    else {
      $blockManager = \Drupal::service('plugin.manager.block');
      foreach ($data as &$item) {
        $display = &$item['panels_display'];
        if (!empty($display['blocks'])) {
          $values = [];
          $blockCollection = new BlockPluginCollection($blockManager, $display['blocks']);
          foreach ($display['blocks'] as $uuid => $definition) {
            if ($definition['provider'] == 'block_content') {
              // Use entity ID, not config ID.
              list($type, $block_uuid) = explode(':', $definition['id']);
              $block = \Drupal::service('entity.repository')
                ->loadEntityByUuid($type, $block_uuid);
              if (!$block) {
                continue;
              }
            }
            elseif (!in_array($definition['provider'], self::SUPPORTED_PROVIDERS)) {
              continue;
            }

            if (!$blockCollection->get($uuid)) {
              $blockCollection->addInstanceId($uuid, $definition);
            }

            $values[$uuid] = $definition;
          }
          $display['blocks'] = $values;
        }
      }

      $entity->set($this->fieldName, $data);
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    $entity = $intent->getEntity();

    if ($this->settings['export'] != ExportIntent::EXPORT_AUTOMATICALLY) {
      return FALSE;
    }

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $data = $entity->get($this->fieldName)->getValue();

    foreach ($data as &$item) {
      $display = &$item['panels_display'];
      unset($display['storage_id']);

      if (!empty($display['blocks'])) {
        $blocks = [];
        foreach ($display['blocks'] as $uuid => $definition) {
          if ($definition['provider'] == 'block_content') {
            // Use entity ID, not config ID.
            list($type, $uuid) = explode(':', $definition['id']);
            $block = \Drupal::service('entity.repository')
              ->loadEntityByUuid($type, $uuid);
            $intent->embedEntity($block, $this->shouldExportReferencedBlocks());
          }
          elseif (!in_array($definition['provider'], self::SUPPORTED_PROVIDERS)) {
            continue;
          }

          $blocks[$uuid] = $definition;
        }

        $display['blocks'] = $blocks;
      }
    }

    $intent->setField($this->fieldName, $data);

    return TRUE;
  }

}
