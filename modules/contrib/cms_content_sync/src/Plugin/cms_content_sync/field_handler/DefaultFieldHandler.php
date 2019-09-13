<?php

namespace Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler;

use Drupal\cms_content_sync\Plugin\FieldHandlerBase;
use Drupal\Core\Field\FieldDefinitionInterface;

/**
 * Providing a minimalistic implementation for any field type.
 *
 * @FieldHandler(
 *   id = "cms_content_sync_default_field_handler",
 *   label = @Translation("Default"),
 *   weight = 100
 * )
 *
 * @package Drupal\cms_content_sync\Plugin\cms_content_sync\field_handler
 */
class DefaultFieldHandler extends FieldHandlerBase {

  /**
   * {@inheritdoc}
   */
  public static function supports($entity_type, $bundle, $field_name, FieldDefinitionInterface $field) {
    $core_field_types = [
      "boolean",
      "changed",
      "comment",
      "created",
      "daterange",
      "datetime",
      "decimal",
      "email",
      "float",
      "iframe",
      "integer",
      "language",
      "list_float",
      "list_integer",
      "list_string",
      "map",
      "range_decimal",
      "range_float",
      "range_integer",
      "string_long",
      "string",
      "telephone",
      "text_long",
      "text_with_summary",
      "text",
      "timestamp",
      "uri",
      "uuid",
    ];
    $contrib_field_types = [
      "address_country",
      "address_zone",
      "address",
      "color_field_type",
      "metatag",
      "soundcloud",
      "video_embed_field",
      "viewfield",
      "yearonly",
      "social_media",
    ];
    $allowed = array_merge($core_field_types, $contrib_field_types);
    return in_array($field->getType(), $allowed) !== FALSE &&
      ($entity_type != 'menu_link_content' || $field_name != 'parent');
  }

}
