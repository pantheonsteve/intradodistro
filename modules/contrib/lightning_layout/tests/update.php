<?php

/**
 * @file
 * Prepares a fixture to test the update path.
 *
 * Forcibly uninstalls Lightning Dev, switches the installation profile from
 * Standard to Minimal, and deletes defunct config objects.
 */

Drupal::configFactory()
  ->getEditable('core.extension')
  ->clear('module.lightning_dev')
  ->clear('module.standard')
  ->set('module.minimal', 1000)
  ->set('profile', 'minimal')
  ->save();

/* Drupal::keyValue('system.schema')->deleteMultiple(['lightning_dev']); */

Drupal::entityTypeManager()
  ->getStorage('node_type')
  ->load('landing_page')
  ->unsetThirdPartySetting('lightning_workflow', 'workflow')
  ->save();

/* Drupal::service('plugin.cache_clearer')->clearCachedDefinitions(); */

$node_type = Drupal::entityTypeManager()
  ->getStorage('node_type')
  ->load('page');

if ($node_type) {
  $node_type->delete();
}

/*
user_role_revoke_permissions('authenticated', ['use text format basic_html']);
 */
