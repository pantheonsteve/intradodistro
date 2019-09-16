<?php

namespace Drupal\layout_builder_restrictions\Form;

use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Layout\LayoutPluginManagerInterface;
use Drupal\Core\Plugin\Context\ContextHandlerInterface;
use Drupal\layout_builder\Entity\LayoutEntityDisplayInterface;
use Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface;
use Drupal\layout_builder_restrictions\Traits\PluginHelperTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Supplement form UI to add setting for which blocks & layouts are available.
 */
class FormAlter implements ContainerInjectionInterface {

  use PluginHelperTrait;
  use DependencySerializationTrait;

  /**
   * The section storage manager.
   *
   * @var \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface
   */
  protected $sectionStorageManager;

  /**
   * The block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The layout manager.
   *
   * @var \Drupal\Core\Layout\LayoutPluginManagerInterface
   */
  protected $layoutManager;

  /**
   * The context handler.
   *
   * @var \Drupal\Core\Plugin\Context\ContextHandlerInterface
   */
  protected $contextHandler;

  /**
   * FormAlter constructor.
   *
   * @param \Drupal\layout_builder\SectionStorage\SectionStorageManagerInterface $section_storage_manager
   *   The section storage manager.
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Block\LayoutPluginManagerInterface $layout_manager
   *   The layout plugin manager.
   * @param \Drupal\Core\Plugin\Context\ContextHandlerInterface $context_handler
   *   The context handler.
   */
  public function __construct(SectionStorageManagerInterface $section_storage_manager, BlockManagerInterface $block_manager, LayoutPluginManagerInterface $layout_manager, ContextHandlerInterface $context_handler) {
    $this->sectionStorageManager = $section_storage_manager;
    $this->blockManager = $block_manager;
    $this->layoutManager = $layout_manager;
    $this->contextHandler = $context_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.layout_builder.section_storage'),
      $container->get('plugin.manager.block'),
      $container->get('plugin.manager.core.layout'),
      $container->get('context.handler')
    );
  }

  /**
   * The actual form elements.
   */
  public function alterEntityViewDisplayForm(&$form, FormStateInterface $form_state, $form_id) {
    $display = $form_state->getFormObject()->getEntity();
    $is_enabled = $display->isLayoutBuilderEnabled();
    if ($is_enabled) {
      $form['#entity_builders'][] = [$this, 'entityFormEntityBuild'];
      // Block settings.
      $form['layout']['layout_builder_restrictions']['allowed_blocks'] = [
        '#type' => 'details',
        '#title' => t('Blocks available for placement'),
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
      $allowed_blocks = (isset($third_party_settings['allowed_blocks'])) ? $third_party_settings['allowed_blocks'] : [];
      foreach ($this->getBlockDefinitions($display) as $category => $blocks) {
        $category_form = [
          '#type' => 'fieldset',
          '#title' => $category,
          '#parents' => ['layout_builder_restrictions', 'allowed_blocks'],
        ];
        $category_setting = in_array($category, array_keys($allowed_blocks)) ? "restricted" : "all";
        $category_form['restriction_behavior'] = [
          '#type' => 'radios',
          '#options' => [
            "all" => t('Allow all existing & new %category blocks.', ['%category' => $category]),
            "restricted" => t('Choose specific %category blocks:', ['%category' => $category]),
          ],
          '#default_value' => $category_setting,
          '#parents' => [
            'layout_builder_restrictions',
            'allowed_blocks',
            $category,
            'restriction',
          ],
        ];
        foreach ($blocks as $block_id => $block) {
          $enabled = FALSE;
          if ($category_setting == 'restricted' && in_array($block_id, $allowed_blocks[$category])) {
            $enabled = TRUE;
          }
          $category_form[$block_id] = [
            '#type' => 'checkbox',
            '#title' => $block['admin_label'],
            '#default_value' => $enabled,
            '#parents' => [
              'layout_builder_restrictions',
              'allowed_blocks',
              $category,
              $block_id,
            ],
            '#states' => [
              'invisible' => [
                ':input[name="layout_builder_restrictions[allowed_blocks][' . $category . '][restriction]"]' => ['value' => "all"],
              ],
            ],
          ];
        }
        if ($category == 'Custom blocks' || $category == 'Custom block types') {
          $category_form['description'] = [
            '#type' => 'container',
            '#children' => t('<p>In the event both <em>Custom Block Types</em> and <em>Custom Blocks</em> restrictions are enabled, <em>Custom Block Types</em> restrictions are disregarded.</p>'),
            '#states' => [
              'visible' => [
                ':input[name="layout_builder_restrictions[allowed_blocks][' . $category . '][restriction]"]' => ['value' => "restricted"],
              ],
            ],
          ];
        }
        $form['layout']['layout_builder_restrictions']['allowed_blocks'][$category] = $category_form;
      }
      // Layout settings.
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', []);
      $allowed_layouts = (isset($third_party_settings['allowed_layouts'])) ? $third_party_settings['allowed_layouts'] : [];
      $layout_form = [
        '#type' => 'details',
        '#title' => t('Layouts available for sections'),
        '#parents' => ['layout_builder_restrictions', 'allowed_layouts'],
        '#states' => [
          'disabled' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
          'invisible' => [
            ':input[name="layout[enabled]"]' => ['checked' => FALSE],
          ],
        ],
      ];
      $layout_form['layout_restriction'] = [
        '#type' => 'radios',
        '#options' => [
          "all" => t('Allow all existing & new layouts.'),
          "restricted" => t('Allow only specific layouts:'),
        ],
        '#default_value' => !empty($allowed_layouts) ? "restricted" : "all",
      ];
      $definitions = $this->getLayoutDefinitions();
      foreach ($definitions as $plugin_id => $definition) {
        $enabled = FALSE;
        if (!empty($allowed_layouts) && in_array($plugin_id, $allowed_layouts)) {
          $enabled = TRUE;
        }
        $layout_form['layouts'][$plugin_id] = [
          '#type' => 'checkbox',
          '#default_value' => $enabled,
          '#description' => [
            $definition->getIcon(60, 80, 1, 3),
            [
              '#type' => 'container',
              '#children' => $definition->getLabel(),
            ],
          ],
          '#states' => [
            'invisible' => [
              ':input[name="layout_builder_restrictions[allowed_layouts][layout_restriction]"]' => ['value' => "all"],
            ],
          ],
        ];
      }
      $form['layout']['layout_builder_restrictions']['allowed_layouts'] = $layout_form;
    }
  }

  /**
   * Save allowed blocks & layouts for the given entity view mode.
   */
  public function entityFormEntityBuild($entity_type_id, LayoutEntityDisplayInterface $display, &$form, FormStateInterface &$form_state) {
    // Set allowed blocks.
    $allowed_blocks = [];
    $categories = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_blocks',
    ]);
    if (!empty($categories)) {
      foreach ($categories as $category => $category_setting) {
        if ($category_setting['restriction'] === 'restricted') {
          $allowed_blocks[$category] = [];
          unset($category_setting['restriction']);
          foreach ($category_setting as $block_id => $block_setting) {
            if ($block_setting == '1') {
              // Include only checked blocks.
              $allowed_blocks[$category][] = $block_id;
            }
          }
        }
      }
      $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction');
      $third_party_settings['allowed_blocks'] = $allowed_blocks;
      $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $third_party_settings);
    }

    // Set allowed layouts.
    $layout_restriction = $form_state->getValue([
      'layout_builder_restrictions',
      'allowed_layouts',
      'layout_restriction',
    ]);
    $allowed_layouts = [];
    if ($layout_restriction == 'restricted') {
      $allowed_layouts = array_keys(array_filter($form_state->getValue([
        'layout_builder_restrictions',
        'allowed_layouts',
        'layouts',
      ])));
    }
    $third_party_settings = $display->getThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction');
    $third_party_settings['allowed_layouts'] = $allowed_layouts;
    $display->setThirdPartySetting('layout_builder_restrictions', 'entity_view_mode_restriction', $third_party_settings);
  }

}
