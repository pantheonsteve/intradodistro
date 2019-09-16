<?php

use Drupal\node\Entity\NodeType;

// Forcibly uninstall Lightning Dev, switch the installation profile from
// Standard to Minimal, and delete defunct config objects.

$config_factory = Drupal::configFactory();

$config_factory
  ->getEditable('core.extension')
  ->clear('module.lightning_dev')
  // openapi_redoc was renamed to openapi_ui_redoc, so we need to delete all
  // mention of it from the database.
  ->clear('module.openapi_redoc')
  ->clear('module.standard')
  ->set('module.minimal', 1000)
  ->set('profile', 'minimal')
  ->save();

Drupal::keyValue('system.schema')->deleteMultiple([
  'lightning_dev',
  'openapi_redoc',
]);

$config_factory
  ->getEditable('entity_browser.browser.media_browser')
  ->delete();

$config_factory->getEditable('media.type.tweet')->delete();

Drupal::service('plugin.cache_clearer')->clearCachedDefinitions();

// Delete all configuration associated with the Page content type, since certain
// Behat fixture contexts reinstall Lightning Page.
$node_type = NodeType::load('page');
if ($node_type) {
  $node_type->delete();
}

user_role_revoke_permissions('authenticated', ['use text format basic_html']);
