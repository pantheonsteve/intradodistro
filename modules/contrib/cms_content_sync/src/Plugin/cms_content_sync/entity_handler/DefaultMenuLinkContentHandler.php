<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\cms_content_sync\ExportIntent;
use Drupal\cms_content_sync\ImportIntent;
use Drupal\cms_content_sync\SyncIntent;
use Drupal\cms_content_sync\Plugin\EntityHandlerBase;

/**
 * Class DefaultMenuLinkContentHandler, providing a minimalistic implementation
 * for menu items, making sure they're referenced correctly by UUID.
 *
 * @EntityHandler(
 *   id = "cms_content_sync_default_menu_link_content_handler",
 *   label = @Translation("Default Menu Link Content"),
 *   weight = 100
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\entity_handler
 */
class DefaultMenuLinkContentHandler extends EntityHandlerBase {

  const USER_REVISION_PROPERTY = 'revision_user';

  /**
   * @inheritdoc
   */
  public static function supports($entity_type, $bundle) {
    return $entity_type == 'menu_link_content';
  }

  /**
   * @inheritdoc
   */
  public function getAllowedPreviewOptions() {
    return [
      'table' => 'Table',
    ];
  }

  /**
   * @inheritdoc
   */
  public function updateEntityTypeDefinition(&$definition) {
    parent::updateEntityTypeDefinition($definition);

    $module_handler = \Drupal::service('module_handler');
    if ($module_handler->moduleExists('menu_token')) {
      $definition['new_properties']['menu_token_options'] = [
        'type' => 'object',
        'default_value' => NULL,
        'multiple' => FALSE,
      ];
      $definition['new_property_lists']['details']['menu_token_options'] = 'value';
      $definition['new_property_lists']['database']['menu_token_options'] = 'value';
      $definition['new_property_lists']['modifiable']['menu_token_options'] = 'value';
    }
  }

  /**
   * @inheritdoc
   */
  public function getHandlerSettings() {
    $menus = menu_ui_get_menus();
    return [
      'ignore_unpublished' => [
        '#type' => 'checkbox',
        '#title' => 'Ignore disabled',
        '#default_value' => isset($this->settings['handler_settings']['ignore_unpublished']) && $this->settings['handler_settings']['ignore_unpublished'] === 0 ? 0 : 1,
      ],
      'restrict_menus' => [
        '#type' => 'checkboxes',
        '#title' => 'Restrict to menus',
        '#default_value' => isset($this->settings['handler_settings']['restrict_menus']) ? $this->settings['handler_settings']['restrict_menus'] : [],
        '#options' => $menus,
        '#description' => t('When no checkbox is set, menu items from all menus will be exported/imported.'),
      ],
    ];
  }

  /**
   * @inheritdoc
   */
  public function export(ExportIntent $intent, EntityInterface $entity = NULL) {
    $result = parent::export($intent, $entity);

    if ($result && $intent->getAction() != SyncIntent::ACTION_DELETE) {
      $module_handler = \Drupal::service('module_handler');

      if ($module_handler->moduleExists('menu_token')) {
        $uuid = $intent->getUuid();
        $config_menu = \Drupal::entityTypeManager()
          ->getStorage('link_configuration_storage')
          ->load($uuid);

        if (!empty($config_menu)) {
          $config_array = unserialize($config_menu->get('configurationSerialized'));
          $intent->setField('menu_token_options', $config_array);
        }
      }
    }

    return $result;
  }

  /**
   * @inheritdoc
   */
  protected function setEntityValues(ImportIntent $intent, FieldableEntityInterface $entity = NULL) {
    $result = parent::setEntityValues($intent, $entity);

    if ($intent->getAction() != SyncIntent::ACTION_DELETE) {
      $module_handler = \Drupal::service('module_handler');

      if ($module_handler->moduleExists('menu_token')) {
        $config_array = $intent->getField('menu_token_options');
        if (!empty($config_array)) {
          $uuid = $intent->getUuid();
          $config_menu = \Drupal::entityTypeManager()
            ->getStorage('link_configuration_storage')
            ->load($uuid);
          if (empty($config_menu)) {
            $config_menu = \Drupal::entityTypeManager()
              ->getStorage('link_configuration_storage')
              ->create([
                'id' => $uuid,
                'label' => 'Menu token link configuration',
                'linkid' => (string) $intent->getField('link')[0]['uri'],
                'configurationSerialized' => serialize($config_array),
              ]);
          }
          else {
            $config_menu->set("linkid", (string) $intent->getField('link')[0]['uri']);
            $config_menu->set("configurationSerialized", serialize($config_array));
          }
          $config_menu->save();
        }
      }
    }

    return $result;
  }

  /**
   * @inheritdoc
   */
  public function ignoreImport(ImportIntent $intent) {
    $action = $intent->getAction();
    if ($action == SyncIntent::ACTION_DELETE) {
      return parent::ignoreImport($intent);
    }

    if (empty($this->resolveDependent)) {
      if (empty($intent->getField('enabled'))) {
        $enabled = TRUE;
      }
      else {
        $enabled = $intent->getField('enabled')[0]['value'];
      }
    }
    else {
      $enabled = $this->resolveDependent['data']['enabled'];
    }

    // Not published? Ignore this revision then.
    if (!$enabled && $this->settings['handler_settings']['ignore_unpublished']) {
      // Unless it's a delete, then it won't have a status and is independent.
      /**
 *of published state, so we don't ignore the import.
 */
      return TRUE;
    }

    if ($this->shouldRestrictMenuUsage()) {
      $menu = $intent->getField('menu_name')[0]['value'];
      if (empty($this->settings['handler_settings']['restrict_menus'][$menu])) {
        return TRUE;
      }
    }

    return parent::ignoreImport($intent);
  }

  protected $resolveDependent = NULL;

  /**
   * @inheritdoc
   */
  public function import(ImportIntent $intent) {
    $link = $intent->getField('link');

    if (isset($link[0]['uri'])) {
      $uri = $link[0]['uri'];
      preg_match('@^internal:/([a-z_0-9]+)\/([a-z0-9-]+)$@', $uri, $found);

      if (!empty($found)) {
        $referenced = \Drupal::service('entity.repository')
          ->loadEntityByUuid($found[1], $found[2]);

        if (!$referenced) {
          $this->resolveDependent = [
            SyncIntent::ENTITY_TYPE_KEY => $found[1],
            SyncIntent::UUID_KEY => $found[2],
            'data' => [
              'enabled' => !!$intent->getField('enabled')[0]['value'],
            ],
          ];

          $intent->setField('enabled', [['value' => 0]]);
        }
      }
    }
    elseif (!empty($link[0][SyncIntent::ENTITY_TYPE_KEY]) && !empty($link[0][SyncIntent::UUID_KEY])) {
      $referenced = $intent->loadEmbeddedEntity($link[0]);

      if (!$referenced) {
        $this->resolveDependent = array_merge($link[0], [
          'data' => [
            'enabled' => !!$intent->getField('enabled')[0]['value'],
          ],
        ]);

        $intent->setField('enabled', [['value' => 0]]);
      }
    }

    if (!parent::import($intent)) {
      return FALSE;
    }

    if ($this->resolveDependent) {
      $intent->saveUnresolvedDependency($this->resolveDependent, 'link', $this->resolveDependent['data']);
    }

    return TRUE;
  }

  /**
   *
   */
  protected function shouldRestrictMenuUsage() {
    return !empty(array_diff(array_values($this->settings['handler_settings']['restrict_menus']), [0]));
  }

  /**
   * @inheritdoc
   */
  public function ignoreExport(ExportIntent $intent) {
    /**
     * @var \Drupal\menu_link_content\Entity\MenuLinkContent $entity
     */
    $entity = $intent->getEntity();

    if (!$entity->isEnabled() && $this->settings['handler_settings']['ignore_unpublished']) {
      return TRUE;
    }

    if ($this->shouldRestrictMenuUsage()) {
      $menu = $entity->getMenuName();
      if (empty($this->settings['handler_settings']['restrict_menus'][$menu])) {
        return TRUE;
      }
    }

    $uri = $entity->get('link')->getValue()[0]['uri'];
    if (substr($uri, 0, 7) == 'entity:') {
      preg_match('/^entity:(.*)\/(\d*)$/', $uri, $found);
      // This means we're already dealing with a UUID that has not been resolved
      // locally yet. So there's no sense in exporting this back to the pool.
      if (empty($found)) {
        return TRUE;
      }
      else {
        $link_entity_type = $found[1];
        $link_entity_id   = $found[2];
        $entity_manager   = \Drupal::entityTypeManager();
        $reference        = $entity_manager->getStorage($link_entity_type)
          ->load($link_entity_id);
        // Dead reference > ignore.
        if (empty($reference)) {
          return TRUE;
        }
      }
    }

    return parent::ignoreExport($intent);
  }

}
