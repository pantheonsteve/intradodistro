<?php

namespace Drupal\cms_content_sync\Plugin;

use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\Core\Config\Entity\ConfigEntityStorage;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Providing a base implementation for any reference field type.
 */
abstract class EntityReferenceHandlerBase extends FieldHandlerBase {

  /**
   * Don't expose option, but force export.
   *
   * @return bool
   */
  protected function forceReferencedEntityExport() {
    return FALSE;
  }

  /**
   * Don't expose option, but force export.
   *
   * @return bool
   */
  protected function forceReferencedEntityEmbedding() {
    return FALSE;
  }

  /**
   * Check if referenced entities should be embedded automatically.
   *
   * @param bool $default
   *   Whether to get the default value (TRUE) if none is set
   *   yet.
   *
   * @return bool
   */
  protected function shouldEmbedReferencedEntities($default = FALSE) {
    if ($this->forceReferencedEntityEmbedding()) {
      return TRUE;
    }

    if (isset($this->settings['handler_settings']['embed_referenced_entities'])) {
      return !!$this->settings['handler_settings']['embed_referenced_entities'];
    }

    if ($default) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Check if referenced entties should be exported automatically.
   *
   * @param bool $default
   *   Whether to get the default value (TRUE) if none is set
   *   yet.
   *
   * @return bool
   */
  protected function shouldExportReferencedEntities($default = FALSE) {
    if ($this->forceReferencedEntityExport()) {
      return TRUE;
    }

    if (isset($this->settings['handler_settings']['export_referenced_entities'])) {
      return !!$this->settings['handler_settings']['export_referenced_entities'];
    }

    if ($default) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * @return bool
   */
  protected function allowExportReferencedEntities() {
    $referenced_entity_type = \Drupal::entityTypeManager()->getStorage($this->getReferencedEntityType());
    return !$referenced_entity_type instanceof ConfigEntityStorage;
  }

  /**
   * @return bool
   */
  protected function allowSubscribeFilter() {
    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [];

    // Will be added in an upcoming release and recommended for paragraphs
    // and bricks.
    // Other entity types like media or taxonomy can use this as a performance
    // improvement as well.
    /*if(!$this->forceReferencedEntityEmbedding()) {
    $options = [
    'embed_referenced_entities' => [
    '#type' => 'checkbox',
    '#title' => 'Embed referenced entities',
    '#default_value' => $this->shouldEmbedReferencedEntities(),
    ],
    ];
    }*/

    // Do not add the option for references to config entities.
    $referenced_entity_type = \Drupal::entityTypeManager()->getStorage($this->getReferencedEntityType());
    if (!$referenced_entity_type instanceof ConfigEntityStorage) {
      if (!$this->forceReferencedEntityExport() && !$this->forceReferencedEntityEmbedding()) {
        $options['export_referenced_entities'] = [
          '#type' => 'checkbox',
          '#title' => 'Export referenced entities',
          '#default_value' => $this->shouldExportReferencedEntities(TRUE),
        ];
      }
    }

    if ($this->allowSubscribeFilter() && $this->flow) {
      $type = $this->fieldDefinition->getSetting('target_type');
      $bundles = $this->fieldDefinition->getSetting('target_bundles');
      if (!$bundles) {
        $field_settings = $this->fieldDefinition->getSettings();
        $bundles = $field_settings['handler_settings']['target_bundles'];
      }

      global $config;
      $config_key = $this->entityTypeName . "-" . $this->bundleName . "-" . $this->fieldName;
      $disabled = !empty($config["cms_content_sync.flow." . $this->flow->id()]["sync_entities"][$config_key]["handler_settings"]["subscribe_only_to"]);

      $entities = [];
      $current = $disabled ? $config["cms_content_sync.flow." . $this->flow->id()]["sync_entities"][$config_key]["handler_settings"]["subscribe_only_to"] : (empty($this->settings['handler_settings']['subscribe_only_to']) ? NULL : $this->settings['handler_settings']['subscribe_only_to']);
      if (!empty($current)) {
        $storage = \Drupal::entityTypeManager()->getStorage($type);
        $repository = \Drupal::service('entity.repository');

        foreach ($current as $ref) {
          if (isset($ref['uuid'])) {
            $entity = $repository->loadEntityByUuid($ref['type'], $ref['uuid']);
          }
          else {
            $entity = $storage->load($ref['target_id']);
          }
          if ($entity) {
            $entities[] = $entity;
          }
        }
      }

      $options['subscribe_only_to'] = [
        '#type' => 'entity_autocomplete',
        '#target_type' => $type,
        '#tags' => TRUE,
        '#selection_settings' => [
          'target_bundles' => $bundles,
        ],
        '#title' => 'Subscribe only to',
        '#disabled' => $disabled,
        '#description' => $disabled ? $this->t("Value provided via settings.php.") : '',
        '#default_value' => $entities,
      ];
    }

    return $options;
  }

  /**
   * @inheritdoc
   */
  public function validateHandlerSettings(array &$form, FormStateInterface $form_state, $settings_key) {
    if (!$this->shouldExportReferencedEntities() && !$this->shouldEmbedReferencedEntities()) {
      return;
    }

    $reference_type = $this->getReferencedEntityType();

    foreach ($form['sync_entities'] as $key => $config) {
      // Ignore field definitions.
      if (substr_count($key, '-') != 1) {
        continue;
      }
      $values = $form_state->getValue(['sync_entities', $key]);
      // Ignore ignored configs.
      if ($values['handler'] == Flow::HANDLER_IGNORE) {
        continue;
      }
      // Ignore exports that are not automatic or referenced.
      if ($values['export'] == ExportIntent::EXPORT_MANUALLY) {
        continue;
      }

      list($entity_type_id,) = explode('-', $key);

      // Ignore configs that don't match our entity type.
      if ($entity_type_id != $reference_type) {
        continue;
      }

      // One has an export handler, so we can ignore this.
      return;
    }

    // No fitting handler was found- inform the user that he's missing some
    // configuration.
    if ($this->forceReferencedEntityExport() || $this->forceReferencedEntityEmbedding()) {
      $element = &$form['sync_entities'][$settings_key]['handler'];
    }
    else {
      $element = &$form['sync_entities'][$settings_key]['handler_settings']['export_referenced_entities'];
    }

    $form_state->setError(
      $element,
      \t(
        'You want to export referenced %label\'s automatically, but you have not defined any handler for this entity type. Please scroll to the bundles of this entity type, add a handler and set "export" to "referenced" there.',
        ['%label' => $reference_type]
      )
    );
  }

  /**
   * @return string
   */
  protected function getReferencedEntityType() {
    $reference_type = $this->fieldDefinition
      ->getFieldStorageDefinition()
      ->getPropertyDefinition('entity')
      ->getTargetDefinition()
      ->getEntityTypeId();

    return $reference_type;
  }

  /**
   * Load the entity that is either referenced or embedded by $definition.
   *
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   * @param $definition
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  protected function loadReferencedEntity(ImportIntent $intent, $definition) {
    return $intent->loadEmbeddedEntity($definition);
  }

  /**
   * @inheritdoc
   */
  protected function setValues(ImportIntent $intent) {
    if ($intent->shouldMergeChanges()) {
      return FALSE;
    }
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $intent->getEntity();

    $data = $intent->getField($this->fieldName);

    $values = [];
    foreach ($data ? $data : [] as $value) {
      $reference = $this->loadReferencedEntity($intent, $value);

      if ($reference) {
        $info = $intent->getEmbeddedEntityData($value);

        $attributes = $this->getFieldValuesForReference($reference, $intent);

        if (is_array($attributes)) {
          $values[] = array_merge($info, $attributes);
        }
        else {
          $values[] = $attributes;
        }
      }
      elseif (!$this->shouldEmbedReferencedEntities()) {
        // Shortcut: If it's just one value and a normal entity_reference field, the MissingDependencyManager will
        // directly update the field value of the entity and save it. Otherwise it will request a full import of the
        // entity. So this saves some performance for simple references.
        if ($this->fieldDefinition->getType() === 'entity_reference' && !$this->fieldDefinition->getFieldStorageDefinition()->isMultiple()) {
          $intent->saveUnresolvedDependency($value, $this->fieldName);
        }
        else {
          $intent->saveUnresolvedDependency($value);
        }
      }
    }

    $entity->set($this->fieldName, $values);

    return TRUE;
  }

  /**
   * Get the values to be set to the $entity->field_*.
   *
   * @param $reference
   * @param $intent
   *
   * @return array
   */
  protected function getFieldValuesForReference($reference, $intent) {
    if ($this->fieldDefinition->getType() == 'entity_reference_revisions') {
      $attributes = [
        'target_id' => $reference->id(),
        'target_revision_id' => $reference->getRevisionId(),
      ];
    }
    else {
      $attributes = [
        'target_id' => $reference->id(),
      ];
    }

    return $attributes;
  }

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    return $this->setValues($intent);
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent) {
    $action = $intent->getAction();
    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $intent->getEntity();

    // Deletion doesn't require any action on field basis for static data.
    if ($action == SyncIntent::ACTION_DELETE) {
      return FALSE;
    }

    $data = $entity->get($this->fieldName)->getValue();

    $result = [];

    foreach ($data as $delta => $value) {
      $reference = $this->loadReferencedEntityFromFieldValue($value);

      if (!$reference || $reference->uuid() == $intent->getUuid()) {
        continue;
      }

      unset($value['target_id']);

      $result[] = $this->serializeReference($intent, $reference, $value);
    }

    $intent->setField($this->fieldName, $result);

    return TRUE;
  }

  /**
   * Load the referenced entity, given the $entity->field_* value.
   *
   * @param $value
   *
   * @return \Drupal\Core\Entity\EntityInterface|null
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function loadReferencedEntityFromFieldValue($value) {
    if (empty($value['target_id'])) {
      return NULL;
    }

    $entityTypeManager = \Drupal::entityTypeManager();
    $reference_type = $this->getReferencedEntityType();
    $storage = $entityTypeManager
      ->getStorage($reference_type);

    $target_id = $value['target_id'];
    $reference = $storage
      ->load($target_id);

    return $reference;
  }

  /**
   * @param \Drupal\cms_content_sync\ExportIntent $intent
   * @param \Drupal\Core\Entity\EntityInterface $reference
   * @param $value
   *
   * @return array
   * @throws \Drupal\Core\Entity\EntityStorageException
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  protected function serializeReference(ExportIntent $intent, EntityInterface $reference, $value) {
    foreach ($this->getInvalidExportSubfields() as $field) {
      unset($value[$field]);
    }
    foreach ($value as $key => $data) {
      if (substr($key, 0, 6) == 'field_') {
        unset($value[$key]);
      }
    }

    // Allow mapping by label.
    if ($reference->getEntityTypeId() == 'taxonomy_term') {
      $value[SyncIntent::LABEL_KEY] = $reference->label();
    }

    if ($this->shouldEmbedReferencedEntities()) {
      return $intent->embedEntity($reference, SyncIntent::ENTITY_REFERENCE_EMBED, $value);
    }
    elseif ($this->shouldExportReferencedEntities()) {
      return $intent->embedEntity($reference, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $value);
    }
    else {
      return $intent->embedEntityDefinition(
        $reference->getEntityTypeId(),
        $reference->bundle(),
        $reference->uuid(),
        EntityHandlerPluginManager::isEntityTypeConfiguration($reference->getEntityType()) ? $reference->id() : NULL,
        SyncIntent::ENTITY_REFERENCE_RESOLVE_IF_EXISTS,
        $value
      );
    }
  }

}
