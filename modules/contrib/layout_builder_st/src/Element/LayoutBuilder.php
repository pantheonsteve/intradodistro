<?php

namespace Drupal\layout_builder_st\Element;

use Drupal\Component\Plugin\ConfigurableInterface;
use Drupal\Component\Plugin\DerivativeInspectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Render\Element;
use Drupal\layout_builder\Element\LayoutBuilder as CoreLayoutbuilder;
use Drupal\layout_builder\LayoutTempstoreRepositoryInterface;
use Drupal\layout_builder\SectionComponent;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_st\LayoutBuilderTranslatablePluginInterface;
use Drupal\layout_builder_st\TranslationsHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Extended LayoutBuilder element to remove actions for translations.
 */
final class LayoutBuilder extends CoreLayoutbuilder {

  use TranslationsHelperTrait;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a new LayoutBuilder.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\layout_builder\LayoutTempstoreRepositoryInterface $layout_tempstore_repository
   *   The layout tempstore repository.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   (optional) The entity type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LayoutTempstoreRepositoryInterface $layout_tempstore_repository, MessengerInterface $messenger, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $layout_tempstore_repository, $messenger);
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('layout_builder.tempstore_repository'),
      $container->get('messenger'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAddSectionLink(SectionStorageInterface $section_storage, $delta) {
    $link = parent::buildAddSectionLink($section_storage, $delta);
    $this->setTranslationAcess($link, $section_storage);
    return $link;
  }

  /**
   * {@inheritdoc}
   */
  protected function buildAdministrativeSection(SectionStorageInterface $section_storage, $delta) {
    $section_build = parent::buildAdministrativeSection($section_storage, $delta);
    $this->setTranslationAcess($section_build['remove'], $section_storage);
    $this->setTranslationAcess($section_build['configure'], $section_storage);
    if (static::isTranslation($section_storage)) {
      foreach (Element::children($section_build['layout-builder__section']) as $region) {
        $region_build = &$section_build['layout-builder__section'][$region];
        $this->setTranslationAcess($region_build['layout_builder_add_block'], $section_storage);
        foreach (Element::children($region_build) as $uuid) {
          if (substr_count($uuid, '-') !== 4) {
            continue;
          }
          // Remove class that enables drag and drop.
          // @todo Can we remove drag and drop in JS?
          if (($key = array_search('js-layout-builder-block', $region_build[$uuid]['#attributes']['class'])) !== FALSE) {
            unset($region_build[$uuid]['#attributes']['class'][$key]);
          }
          if ($contextual_link_element = $this->createContextualLinkElement($section_storage, $delta, $region, $uuid)) {
            $region_build[$uuid]['#contextual_links'] = $contextual_link_element;
          }
          else {
            unset($region_build[$uuid]['#contextual_links']);
          }
        }
      }
    }

    return $section_build;
  }

  /**
   * Set the #access property based section storage translation.
   */
  private function setTranslationAcess(array &$build, SectionStorageInterface $section_storage) {
    $build['#access'] = !static::isTranslation($section_storage);
  }

  /**
   * Creates contextual link element for a component.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param $delta
   *   The section delta.
   * @param $region
   *   The region.
   * @param $uuid
   *   The UUID of the component.
   * @param $is_translation
   *   Whether the section storage is handling a translation.
   *
   * @return array|null
   *   The contextual link render array or NULL if none.
   *
   */
  private function createContextualLinkElement(SectionStorageInterface $section_storage, $delta, $region, $uuid) {
    $section = $section_storage->getSection($delta);
    $contextual_link_settings = [
      'route_parameters' => [
        'section_storage_type' => $section_storage->getStorageType(),
        'section_storage' => $section_storage->getStorageId(),
        'delta' => $delta,
        'region' => $region,
        'uuid' => $uuid,
      ],
    ];
    if (static::isTranslation($section_storage)) {
      $contextual_group = 'layout_builder_block_translation';
      $component = $section->getComponent($uuid);
      /** @var \Drupal\Core\Language\LanguageInterface $language */
      $language = $section_storage->getTranslationLanguage();
      $contextual_link_settings['route_parameters']['langcode'] = $language->getId();

      /** @var \Drupal\layout_builder\Plugin\Block\InlineBlock $plugin */
      $plugin = $component->getPlugin();
      if ($plugin instanceof DerivativeInspectionInterface && $plugin->getBaseId() === 'inline_block') {
        $configuration = $plugin->getConfiguration();
        /** @var \Drupal\block_content\Entity\BlockContent $block */
        $block = $this->entityTypeManager->getStorage('block_content')
          ->loadRevision($configuration['block_revision_id']);
        if ($block->isTranslatable()) {
          $contextual_group = 'layout_builder_inline_block_translation';
        }
      }
    }
    else {
      $contextual_group = 'layout_builder_block';
      // Add metadata about the current operations available in
      // contextual links. This will invalidate the client-side cache of
      // links that were cached before the 'move' link was added.
      // @see layout_builder.links.contextual.yml
      $contextual_link_settings['metadata'] = [
        'operations' => 'move:update:remove',
      ];
    }
    return [
      $contextual_group => $contextual_link_settings,
    ];
  }

  /**
   * Determines whether the component has translatable configuration.
   *
   * This function is replacement for \Drupal\layout_builder\SectionComponent::hasTranslatableConfiguration()
   * in https://www.drupal.org/project/drupal/issues/2946333#comment-13129737
   * This avoids having to alter the class in the module.
   *
   * @param \Drupal\layout_builder\SectionComponent $component
   *
   * @return bool
   *   TRUE if the plugin has translatable configuration.
   */
  private static function hasTranslatableConfiguration(SectionComponent $component) {
    $plugin = $component->getPlugin();
    if ($plugin instanceof LayoutBuilderTranslatablePluginInterface) {
      return $plugin->hasTranslatableConfiguration();
    }
    elseif ($plugin instanceof ConfigurableInterface) {
      // For all plugins that do not implement
      // LayoutBuilderTranslatablePluginInterface only allow label translation.
      $configuration = $plugin->getConfiguration();
      return !empty($configuration['label_display']) && !empty($configuration['label']);
    }
    return FALSE;
  }

}
