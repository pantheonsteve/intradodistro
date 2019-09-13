<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\cms_content_sync\Exception\SyncException;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;
use Drupal\cms_content_sync\SyncIntent;

/**
 * Class DefaultTaxonomyHandler.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_taxonomy_handler",
 *   label = @Translation("Default Taxonomy"),
 *   weight = 90
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultTaxonomyHandler extends EntityHandlerBase {

  const MAP_BY_LABEL_SETTING = 'map_by_label';

  const USER_REVISION_PROPERTY = 'revision_user';

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'taxonomy_term';
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = parent::getHandlerSettings();

    $options[self::MAP_BY_LABEL_SETTING] = [
      '#type' => 'checkbox',
      '#title' => 'Map by name',
      '#default_value' => $this->shouldMapByLabel() ? 1 : 0,
    ];

    return $options;
  }

  /**
   * If set, terms will not be imported if an identical term already exists. Instead, this term will be mapped when
   * importing content that references it.
   */
  protected function shouldMapByLabel() {
    return isset($this->settings['handler_settings'][self::MAP_BY_LABEL_SETTING]) && $this->settings['handler_settings'][self::MAP_BY_LABEL_SETTING] == 1;
  }

  /**
   * @inheritdoc
   */
  public function getAllowedExportOptions() {
    return [
      ExportIntent::EXPORT_DISABLED,
      ExportIntent::EXPORT_AUTOMATICALLY,
      ExportIntent::EXPORT_AS_DEPENDENCY,
      ExportIntent::EXPORT_MANUALLY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
      'preview_mode' => 'Preview mode',
    ];
  }

  /**
   * @inheritdoc
   */
  public function updateEntityTypeDefinition(&$definition) {
    parent::updateEntityTypeDefinition($definition);
    $definition['new_properties']['parent'] = [
      'type' => 'object',
      'default_value' => NULL,
    ];
    $definition['new_property_lists']['details']['parent'] = 'value';
    $definition['new_property_lists']['modifiable']['parent'] = 'value';
    $definition['new_property_lists']['database']['parent'] = 'value';
  }

  /**
   * @inheritdoc
   */
  public function getForbiddenFields() {
    return array_merge(
      parent::getForbiddenFields(),
      [
        'parent',
      ]
    );
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();

    if ($this->ignoreImport($intent)) {
      return FALSE;
    }

    /**
     * @var \Drupal\Core\Entity\FieldableEntityInterface $entity
     */
    $entity = $intent->getEntity();

    if ($action == SyncIntent::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity);
      }
      return FALSE;
    }

    if (!$entity) {
      $entity_type = \Drupal::entityTypeManager()->getDefinition($intent->getEntityType());

      $label_property = $entity_type->getKey('label');
      if ($this->shouldMapByLabel()) {
        $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties([
          $label_property => $intent->getField('title'),
        ]);
        $existing = reset($existing);

        if (!empty($existing)) {
          return TRUE;
        }
      }

      $base_data = [
        $entity_type->getKey('bundle') => $intent->getBundle(),
        $label_property => $intent->getField('title'),
      ];

      $base_data[$entity_type->getKey('uuid')] = $intent->getUuid();

      $storage = \Drupal::entityTypeManager()->getStorage($intent->getEntityType());
      $entity = $storage->create($base_data);

      if (!$entity) {
        throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
      }

      $intent->setEntity($entity);
    }

    $parent_reference = $intent->getField('parent');
    if ($parent_reference && ($parent = $intent->loadEmbeddedEntity($parent_reference))) {
      $entity->set('parent', ['target_id' => $parent->id()]);
    }
    else {
      $entity->set('parent', ['target_id' => 0]);
      if (!empty($parent_reference)) {
        $intent->saveUnresolvedDependency($parent_reference, 'parent');
      }
    }

    if (!$this->setEntityValues($intent)) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    /**
     * @var \Drupal\file\FileInterface $entity
     */
    if (!$entity) {
      $entity = $intent->getEntity();
    }

    if (!parent::export($intent)) {
      return FALSE;
    }

    $term_storage = \Drupal::entityTypeManager()->getStorage('taxonomy_term');
    $parents = $term_storage->loadParents($entity->id());

    if (count($parents)) {
      $parent_term = reset($parents);
      $parent = $intent->embedEntity($parent_term, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY);
      $intent->setField('parent', $parent);
    }

    // Since taxonomy terms ain't got a created date, we set the changed
    // date instead during the first export.
    $status_entity = $intent->getEntityStatus();
    if (is_null($status_entity->getLastExport())) {
      $intent->setField('created', (int) $entity->getChangedTime());
    }

    return TRUE;
  }

}
