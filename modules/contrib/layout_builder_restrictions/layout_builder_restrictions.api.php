<?php

/**
 * @file
 * Api.php for layout_builder_restrictions.
 */

/**
 * Tell the module which block providers are available to Layout Builder.
 *
 * @return array
 *   An array of keys.
 */
function hook_layout_builder_restrictions_allowed_block_keys() {
  // Whitelist which block providers (e.g., System, Content, Menus)
  // are available to Layout Builder. In the example below, only
  // entity fields, Views blocks, and custom blocks will be available.
  // This hook will apply to all entities that use Layout Builder.
  return [
    (string) t('Content'),
    (string) t('Lists (Views)'),
    (string) t('Block'),
  ];
}

/**
 * Alter the allowed keys.
 *
 * @param array $keys
 *   The keys that modules have specified.
 */
function hook_layout_builder_restrictions_allowed_block_keys_alter(array &$keys) {
  // Unset some keys that another module has allowed.
  foreach ($keys as $delta => $key) {
    if ($key == (string) t('Custom')) {
      unset($keys[$delta]);
    }
  }
}

/**
 * Alter the controller result, after the layout builder has altered it.
 */
function hook_layout_builder_restrictions_chooser_result(array &$result) {
  $result[(string) t('Custom')]['#access'] = TRUE;
}

/**
 * Tell the module which layouts are allowed to use.
 */
function hook_layout_builder_restrictions_allowed_layouts() {
  // Only allow 'layout_onecol' to be used.
  return [
    'layout_onecol',
  ];
}

/**
 * Alter the keys allowed.
 *
 * @param array $keys
 *   The keys currently allowed.
 */
function hook_layout_builder_restrictions_allowed_layouts_alter(array &$keys) {
  // Unset some keys that another module has allowed.
  foreach ($keys as $delta => $key) {
    if ($key == 'layout_onecol') {
      unset($keys[$delta]);
    }
  }
}
