<?php

namespace Drupal\cms_content_sync\Annotation;

use Drupal\Component\Annotation\Plugin;

/**
 * Defines a FieldHandler annotation object.
 *
 * They handle how field synchronizations are configured and how they
 * eventually behave on import and export.
 *
 * Additional annotation keys for handlers can be defined in
 * hook_cms_content_sync_entity_handler_info_alter().
 *
 * @Annotation
 *
 * @see \Drupal\Core\Field\HandlerPluginManager
 * @see \Drupal\Core\Field\HandlerInterface
 *
 * @ingroup third_party
 */
class FieldHandler extends Plugin {

  /**
   * The plugin ID.
   *
   * @var string
   */
  public $id;

  /**
   * The human-readable name of the handler type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $label;

  /**
   * A short description of the handler type.
   *
   * @var \Drupal\Core\Annotation\Translation
   *
   * @ingroup plugin_translatable
   */
  public $description;

  /**
   * The name of the handler class.
   *
   * This is not provided manually, it will be added by the discovery mechanism.
   *
   * @var string
   */
  public $class;

  /**
   * The weight.
   *
   * An integer to determine the weight of this handler relative to other
   * handlersin the Field UI when selecting a handler for a given field.
   *
   * @var intoptional
   */
  public $weight = NULL;

}
