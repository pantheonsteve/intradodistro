<?php

/**
 * @file
 * Prepares a installed code base to run database updates for testing.
 */

use Drupal\node\Entity\NodeType;

// Forcibly uninstall Lightning Dev.
Drupal::configFactory()
  ->getEditable('core.extension')
  ->clear('module.lightning_dev')
  ->save();

Drupal::keyValue('system.schema')->deleteMultiple(['lightning_dev']);

// Remove Workflow-related settings from the Page content type.
NodeType::load('page')
  ->unsetThirdPartySetting('lightning_workflow', 'workflow')
  ->save();
