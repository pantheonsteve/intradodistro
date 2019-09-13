<?php

namespace Drupal\cms_content_sync\Plugin;

use Drupal\cms_content_sync\Event\BeforeEntityExport;
use Drupal\cms_content_sync\Event\BeforeEntityImport;
use Drupal\cms_content_sync\Plugin\Type\EntityHandlerPluginManager;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Entity\RevisionableEntityBundleInterface;
use Drupal\Core\Entity\TranslatableInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\cms_content_sync\Entity\Flow;
use Drupal\cms_content_sync\Entity\EntityStatus;
use Drupal\cms_content_sync\Exception\SyncException;
use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_link_content\Plugin\Menu\MenuLinkContent;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\Render\RenderContext;
use Psr\Log\LoggerInterface;

/**
 * Common base class for entity handler plugins.
 *
 * @see \Drupal\cms_content_sync\Annotation\EntityHandler
 * @see \Drupal\cms_content_sync\Plugin\EntityHandlerInterface
 * @see plugin_api
 *
 * @ingroup third_party
 */
abstract class EntityHandlerBase extends PluginBase implements ContainerFactoryPluginInterface, EntityHandlerInterface {

  /**
   * A logger instance.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  protected $entityTypeName;
  protected $bundleName;
  protected $settings;

  const USER_PROPERTY = NULL;
  const USER_REVISION_PROPERTY = NULL;

  /**
   * A sync instance.
   *
   * @var \Drupal\cms_content_sync\Entity\Flow
   */
  protected $flow;

  /**
   * Constructs a Drupal\rest\Plugin\ResourceBase object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->logger         = $logger;
    $this->entityTypeName = $configuration['entity_type_name'];
    $this->bundleName     = $configuration['bundle_name'];
    $this->settings       = $configuration['settings'];
    $this->flow           = $configuration['sync'];
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('cms_content_sync')
    );
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
  public function getAllowedImportOptions() {
    return [
      ImportIntent::IMPORT_DISABLED,
      ImportIntent::IMPORT_MANUALLY,
      ImportIntent::IMPORT_AUTOMATICALLY,
      ImportIntent::IMPORT_AS_DEPENDENCY,
    ];
  }

  /**
   * @inheritdoc
   */
  public function updateEntityTypeDefinition(&$definition) {
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $options = [];

    $no_menu_link_export = [
      'brick',
      'field_collection_item',
      'menu_link_content',
      'paragraph',
    ];

    if (!in_array($this->entityTypeName, $no_menu_link_export)) {
      $options['export_menu_items'] = [
        '#type' => 'checkbox',
        '#title' => 'Export menu items',
        '#default_value' => isset($this->settings['handler_settings']['export_menu_items']) && $this->settings['handler_settings']['export_menu_items'] === 0 ? 0 : 1,
      ];
    }

    return $options;
  }

  /**
   * Whether or not menu item references should be exported.
   *
   * @return bool
   */
  protected function exportReferencedMenuItems() {
    if (!isset($this->settings['handler_settings']['export_menu_items'])) {
      return TRUE;
    }

    return $this->settings['handler_settings']['export_menu_items'] !== 0;
  }

  /**
   * @inheritdoc
   */
  public function validateHandlerSettings(array &$form, FormStateInterface $form_state, $settings_key) {
    // No settings means no validation.
  }

  /**
   * Check if the import should be ignored.
   *
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @return bool
   *   Whether or not to ignore this import request.
   */
  protected function ignoreImport(ImportIntent $intent) {
    $reason = $intent->getReason();
    $action = $intent->getAction();

    if ($reason == ImportIntent::IMPORT_AUTOMATICALLY) {
      if ($this->settings['import'] == ImportIntent::IMPORT_MANUALLY) {
        // Once imported manually, updates will arrive automatically.
        if (($reason != ImportIntent::IMPORT_AUTOMATICALLY || $this->settings['import'] != ImportIntent::IMPORT_MANUALLY) || $action == SyncIntent::ACTION_CREATE) {
          return TRUE;
        }
      }
    }

    if ($action == SyncIntent::ACTION_UPDATE) {
      $behavior = $this->settings['import_updates'];
      if ($behavior == ImportIntent::IMPORT_UPDATE_IGNORE) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Check whether the entity type supports having a label.
   *
   * @return bool
   */
  protected function hasLabelProperty() {
    return TRUE;
  }

  /**
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @return \Drupal\Core\Entity\EntityInterface
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function createNew(ImportIntent $intent) {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($intent->getEntityType());

    $base_data = [];

    if (EntityHandlerPluginManager::isEntityTypeConfiguration($intent->getEntityType())) {
      $base_data['id'] = $intent->getId();
    }

    if ($this->hasLabelProperty()) {
      $base_data[$entity_type->getKey('label')] = $intent->getField('title');
    }

    // Required as field collections share the same property for label and bundle.
    $base_data[$entity_type->getKey('bundle')] = $intent->getBundle();

    $base_data[$entity_type->getKey('uuid')] = $intent->getUuid();
    if ($entity_type->getKey('langcode')) {
      $base_data[$entity_type->getKey('langcode')] = $intent->getField($entity_type->getKey('langcode'));
    }

    $storage = \Drupal::entityTypeManager()->getStorage($intent->getEntityType());
    $entity = $storage->create($base_data);

    return $entity;
  }

  /**
   * Import the remote entity.
   *
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $action = $intent->getAction();

    if ($this->ignoreImport($intent)) {
      return FALSE;
    }

    /**
     * @var \Drupal\Core\Entity\EntityInterface $entity
     */
    $entity = $intent->getEntity();

    if ($action == SyncIntent::ACTION_DELETE) {
      if ($entity) {
        return $this->deleteEntity($entity);
      }
      // Already done means success.
      if ($intent->getEntityStatus()->isDeleted()) {
        return TRUE;
      }
      return FALSE;
    }

    if ($entity) {
      if ($bundle_entity_type = $entity->getEntityType()->getBundleEntityType()) {
        $bundle_entity_type = \Drupal::entityTypeManager()->getStorage($bundle_entity_type)->load($entity->bundle());
        if (($bundle_entity_type instanceof RevisionableEntityBundleInterface && $bundle_entity_type->shouldCreateNewRevision()) || $bundle_entity_type->getEntityTypeId() == "field_collection") {
          $entity->setNewRevision(TRUE);
        }
      }
    }
    else {
      $entity = $this->createNew($intent);

      if (!$entity) {
        throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE);
      }

      $intent->setEntity($entity);
    }

    if ($entity instanceof FieldableEntityInterface && !$this->setEntityValues($intent)) {
      return FALSE;
    }

    // Allow other modules to extend the EntityHandlerBase import.
    // Dispatch ExtendEntityImport.
    \Drupal::service('event_dispatcher')->dispatch(BeforeEntityImport::EVENT_NAME, new BeforeEntityImport($entity, $intent));

    return TRUE;
  }

  /**
   * Delete a entity.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to delete.
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   *
   * @return bool
   *   Returns TRUE or FALSE for the deletion process.
   */
  protected function deleteEntity(EntityInterface $entity) {
    try {
      $entity->delete();
    }
    catch (\Exception $e) {
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
    }
    return TRUE;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   * @param \Drupal\cms_content_sync\ImportIntent $intent
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function saveEntity($entity, $intent) {
    $entity->save();
  }

  /**
   * Set the values for the imported entity.
   *
   * @param \Drupal\cms_content_sync\SyncIntent $intent
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The translation of the entity.
   *
   * @see Flow::IMPORT_*
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @return bool
   *   Returns TRUE when the values are set.
   */
  protected function setEntityValues(ImportIntent $intent, FieldableEntityInterface $entity = NULL) {
    if (!$entity) {
      $entity = $intent->getEntity();
    }

    /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
    $entityFieldManager = \Drupal::service('entity_field.manager');
    $type = $entity->getEntityTypeId();
    $bundle = $entity->bundle();
    $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

    $entity_type = \Drupal::entityTypeManager()->getDefinition($intent->getEntityType());
    $label       = $entity_type->getKey('label');
    if ($label && !$intent->shouldMergeChanges() && $this->hasLabelProperty()) {
      $entity->set($label, $intent->getField('title'));
    }

    $static_fields = $this->getStaticFields();

    $is_translatable = $this->isEntityTypeTranslatable($entity);
    $is_translation = boolval($intent->getActiveLanguage());

    foreach ($field_definitions as $key => $field) {
      $handler = $this->flow->getFieldHandler($type, $bundle, $key);

      if (!$handler) {
        continue;
      }

      // This field cannot be updated.
      if (in_array($key, $static_fields) && $intent->getAction() != SyncIntent::ACTION_CREATE) {
        continue;
      }

      if ($is_translatable && $is_translation && !$field->isTranslatable()) {
        continue;
      }

      if ($field->getType() == 'image' || $field->getType() == 'file') {
        continue;
      }

      $handler->import($intent);
    }

    $user = \Drupal::currentUser();
    if (static::USER_PROPERTY && empty($entity->get(static::USER_PROPERTY)->value)) {
      $entity->set(static::USER_PROPERTY, ['target_id' => $user->id()]);
    }
    if (static::USER_REVISION_PROPERTY && $entity->hasField(static::USER_REVISION_PROPERTY) && empty($entity->get(static::USER_REVISION_PROPERTY)->value)) {
      $entity->set(static::USER_REVISION_PROPERTY, ['target_id' => $user->id()]);
    }

    try {
      $this->saveEntity($entity, $intent);
    }
    catch (\Exception $e) {
      throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
    }

    // We can't set file fields until the source entity has been saved.
    // Otherwise Drupal will throw Exceptions:
    // Error message is: InvalidArgumentException: Invalid translation language (und) specified.
    // Occurs when using translatable entities referencing files.
    $changed = FALSE;
    foreach ($field_definitions as $key => $field) {
      $handler = $this->flow->getFieldHandler($type, $bundle, $key);

      if (!$handler) {
        continue;
      }

      // This field cannot be updated.
      if (in_array($key, $static_fields) && $intent->getAction() != SyncIntent::ACTION_CREATE) {
        continue;
      }

      if ($is_translatable && $is_translation && !$field->isTranslatable()) {
        continue;
      }

      if ($field->getType() != 'image' && $field->getType() != 'file') {
        continue;
      }

      $handler->import($intent);
      $changed = TRUE;
    }

    if ($changed) {
      try {
        $this->saveEntity($entity, $intent);
      }
      catch (\Exception $e) {
        throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
      }
    }

    if ($is_translatable && !$intent->getActiveLanguage()) {
      $languages = $intent->getTranslationLanguages();
      foreach ($languages as $language) {
        /**
         * If the provided entity is fieldable, translations are as well.
         *
         * @var \Drupal\Core\Entity\FieldableEntityInterface $translation
         */
        if ($entity->hasTranslation($language)) {
          $translation = $entity->getTranslation($language);
        }
        else {
          $translation = $entity->addTranslation($language);
        }

        $intent->changeTranslationLanguage($language);
        if (!$this->ignoreImport($intent)) {
          $this->setEntityValues($intent, $translation);
        }
      }

      // Delete translations that were deleted on master site.
      if (boolval($this->settings['import_deletion_settings']['import_deletion'])) {
        $existing = $entity->getTranslationLanguages(FALSE);
        foreach ($existing as &$language) {
          $language = $language->getId();
        }
        $languages = array_diff($existing, $languages);
        if (count($languages)) {
          foreach ($languages as $language) {
            $entity->removeTranslation($language);
          }
          try {
            $this->saveEntity($entity, $intent);
          }
          catch (\Exception $e) {
            throw new SyncException(SyncException::CODE_ENTITY_API_FAILURE, $e);
          }
        }
      }

      $intent->changeTranslationLanguage();
    }

    return TRUE;
  }

  /**
   * @param \Drupal\cms_content_sync\ExportIntent $intent
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @throws \Drupal\cms_content_sync\Exception\SyncException
   */
  protected function setSourceUrl(ExportIntent $intent, EntityInterface $entity) {
    if ($entity->hasLinkTemplate('canonical')) {
      try {
        $url = $entity->toUrl('canonical', ['absolute' => TRUE])
          ->toString(TRUE)
          ->getGeneratedUrl();
        $intent->setField(
          'url',
          $url
        );
      }
      catch (\Exception $e) {
        throw new SyncException(SyncException::CODE_UNEXPECTED_EXCEPTION, $e);
      }
    }
  }

  /**
   * Check if the entity should not be ignored from the export.
   *
   * @param \Drupal\cms_content_sync\SyncIntent $intent
   *   The Sync Core Request.
   * @param \Drupal\Core\Entity\FieldableEntityInterface $entity
   *   The entity that could be ignored.
   * @param string $reason
   *   The reason why the entity should be ignored from the export.
   * @param string $action
   *   The action to apply.
   *
   * @return bool
   *   Whether or not to ignore this export request.
   *
   * @throws \Exception
   */
  protected function ignoreExport(ExportIntent $intent) {
    $reason = $intent->getReason();
    $action = $intent->getAction();

    if ($reason == ExportIntent::EXPORT_AUTOMATICALLY) {
      if ($this->settings['export'] == ExportIntent::EXPORT_MANUALLY) {
        return TRUE;
      }
    }

    if ($action == SyncIntent::ACTION_UPDATE) {
      foreach (EntityStatus::getInfosForEntity($intent->getEntityType(), $intent->getUuid()) as $info) {
        $flow = $info->getFlow();
        if (!$flow) {
          continue;
        }
        if (!$info->getLastImport()) {
          continue;
        }
        if (!$info->isSourceEntity()) {
          break;
        }
        $config = $flow->getEntityTypeConfig($intent->getEntityType(), $intent->getBundle());

        if ($config['import_updates'] == ImportIntent::IMPORT_UPDATE_FORCE_UNLESS_OVERRIDDEN) {
          return TRUE;
        }
      }
    }

    return FALSE;
  }

  /**
   * @inheritdoc
   */
  public function getForbiddenFields() {
    /**
     * @var \Drupal\Core\Entity\EntityTypeInterface $entity_type_entity
     */
    $entity_type_entity = \Drupal::service('entity_type.manager')
      ->getStorage($this->entityTypeName)
      ->getEntityType();
    return [
      // These basic fields are already taken care of, so we ignore them
      // here.
      $entity_type_entity->getKey('id'),
      $entity_type_entity->getKey('revision'),
      $entity_type_entity->getKey('bundle'),
      $entity_type_entity->getKey('uuid'),
      $entity_type_entity->getKey('label'),
      // These are not relevant or misleading when synchronized.
      'revision_default',
      'revision_translation_affected',
      'content_translation_outdated',
    ];
  }

  /**
   * Get a list of fields that can't be updated.
   *
   * @return string[]
   */
  protected function getStaticFields() {
    return [];
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    if ($this->ignoreExport($intent)) {
      return FALSE;
    }

    if (!$entity) {
      $entity = $intent->getEntity();
    }

    // Base info.
    $intent->setField('title', $entity->label());

    // Menu items.
    if ($this->exportReferencedMenuItems()) {
      $menu_link_manager = \Drupal::service('plugin.manager.menu.link');
      /**
       * @var \Drupal\Core\Menu\MenuLinkManager $menu_link_manager
       */
      $menu_items = $menu_link_manager->loadLinksByRoute('entity.' . $this->entityTypeName . '.canonical', [$this->entityTypeName => $entity->id()]);
      $values = [];

      $form_values = _cms_content_sync_submit_cache($entity->getEntityTypeId(), $entity->uuid());

      foreach ($menu_items as $menu_item) {
        if (!($menu_item instanceof MenuLinkContent)) {
          continue;
        }

        /**
         * @var \Drupal\menu_link_content\Entity\MenuLinkContent $item
         */
        $item = \Drupal::service('entity.repository')
          ->loadEntityByUuid('menu_link_content', $menu_item->getDerivativeId());
        if (!$item) {
          continue;
        }

        // Menu item has just been disabled => Ignore export in this case.
        if (isset($form_values['menu']) && $form_values['menu']['id'] == 'menu_link_content:' . $item->uuid()) {
          if (!$form_values['menu']['enabled']) {
            continue;
          }
        }

        $details = [];
        $details['enabled'] = $item->get('enabled')->value;

        $values[] = $intent->embedEntity($item, SyncIntent::ENTITY_REFERENCE_EXPORT_AS_DEPENDENCY, $details);
      }

      $intent->setField('menu_items', $values);
    }

    // Preview.
    $view_mode = $this->flow->getPreviewType($entity->getEntityTypeId(), $entity->bundle());
    if ($view_mode != Flow::PREVIEW_DISABLED) {
      $entityTypeManager = \Drupal::entityTypeManager();
      $view_builder = $entityTypeManager->getViewBuilder($this->entityTypeName);

      $preview = $view_builder->view($entity, $view_mode);
      $rendered = \Drupal::service('renderer');
      $html = $rendered->executeInRenderContext(
        new RenderContext(),
        function () use ($rendered, $preview) {
          return $rendered->render($preview);
        }
      );
      $intent->setField('preview', $html);
    }
    else {
      $intent->setField('preview', '<em>Previews are disabled for this entity.</em>');
    }

    // Source URL.
    $this->setSourceUrl($intent, $entity);

    // Fields.
    if ($entity instanceof FieldableEntityInterface) {
      /** @var \Drupal\Core\Entity\EntityFieldManagerInterface $entityFieldManager */
      $entityFieldManager = \Drupal::service('entity_field.manager');
      $type = $entity->getEntityTypeId();
      $bundle = $entity->bundle();
      $field_definitions = $entityFieldManager->getFieldDefinitions($type, $bundle);

      foreach ($field_definitions as $key => $field) {
        $handler = $this->flow->getFieldHandler($type, $bundle, $key);

        if (!$handler) {
          continue;
        }

        $handler->export($intent);
      }
    }

    // Translations.
    if (!$intent->getActiveLanguage() &&
      $this->isEntityTypeTranslatable($entity)) {
      $languages = array_keys($entity->getTranslationLanguages(FALSE));

      foreach ($languages as $language) {
        $intent->changeTranslationLanguage($language);
        /**
         * @var \Drupal\Core\Entity\FieldableEntityInterface $translation
         */
        $translation = $entity->getTranslation($language);
        $this->export($intent, $translation);
      }

      $intent->changeTranslationLanguage();
    }

    // Allow other modules to extend the EntityHandlerBase export.
    // Dispatch ExtendEntityExport.
    \Drupal::service('event_dispatcher')->dispatch(BeforeEntityExport::EVENT_NAME, new BeforeEntityExport($entity, $intent));

    return TRUE;
  }

  /**
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *
   * @return bool
   */
  protected function isEntityTypeTranslatable($entity) {
    return $entity instanceof TranslatableInterface && $entity->getEntityType()->getKey('langcode');
  }

}
