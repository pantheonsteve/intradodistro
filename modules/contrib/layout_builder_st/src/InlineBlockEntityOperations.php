<?php

namespace Drupal\layout_builder_st;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_builder\InlineBlockEntityOperations as CoreInlineBlockEntityOperations;
use Drupal\layout_builder\InlineBlockUsageInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;

/**
 * Overrides cores InlineBlockEntityOperations to provide translation operations.
 */
final class InlineBlockEntityOperations extends CoreInlineBlockEntityOperations {

  use TranslationsHelperTrait;

  /**
   * The block plugin manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, InlineBlockUsageInterface $usage, Connection $database, SectionStorageManagerInterface $section_storage_manager = NULL) {
    parent::__construct($entityTypeManager, $usage, $database, $section_storage_manager);
    $this->blockManager = \Drupal::service('plugin.manager.block');
  }

  /**
   * Saves a translated inline block.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity with the layout.
   * @param string $component_uuid
   *   The component UUID.
   * @param array $translated_component_configuration
   *   The translated component configuration.
   * @param bool $new_revision
   *   Whether a new revision of the block should be created.
   */
  protected function saveTranslatedInlineBlock(EntityInterface $entity, $component_uuid, array $translated_component_configuration, $new_revision) {
    /** @var \Drupal\block_content\BlockContentInterface $block */
    $block = unserialize($translated_component_configuration['block_serialized']);
    // Create a InlineBlock plugin from the translated configuration in order to
    // save the block.
    /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
    $plugin = $this->blockManager->createInstance('inline_block:' . $block->bundle(), $translated_component_configuration);
    $plugin->saveBlockContent($new_revision);
    // Remove serialized block after the block has been saved.
    unset($translated_component_configuration['block_serialized']);

    // Update the block_revision_id in the translated configuration which may
    // have changed after saving the block.
    $configuration = $plugin->getConfiguration();
    $translated_component_configuration['block_revision_id'] = $configuration['block_revision_id'];

    /** @var \Drupal\layout_builder_st\TranslatableSectionStorageInterface $section_storage */
    $section_storage = $this->getSectionStorageForEntity($entity);
    $section_storage->setTranslatedComponentConfiguration($component_uuid, $translated_component_configuration);
  }

  /**
   * {@inheritdoc}
   *
   * Override to ::saveTranslatedInlineBlock() for translations.
   */
  protected function saveInlineBlockComponent(EntityInterface $entity, SectionComponent $component, $new_revision, $duplicate_blocks) {
    $section_storage = $this->getSectionStorageForEntity($entity);
    if (static::isTranslation($section_storage)) {
      /** @var  \Drupal\layout_builder_st\TranslatableSectionStorageInterface $section_storage */
      $translated_component_configuration = $section_storage->getTranslatedComponentConfiguration($component->getUuid());
      if (isset($translated_component_configuration['block_serialized'])) {
        $this->saveTranslatedInlineBlock($entity, $component->getUuid(), $translated_component_configuration, $new_revision);
      }

    }
    else {
      parent::saveInlineBlockComponent($entity, $component, $new_revision, $duplicate_blocks);
    }

  }


}
