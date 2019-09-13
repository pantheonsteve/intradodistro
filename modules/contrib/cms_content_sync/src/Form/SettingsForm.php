<?php

namespace Drupal\cms_content_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Component\Utility\Unicode;
use Drupal\user\Entity\Role;

/**
 * CMS Content Sync general settings form.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'cms_content_sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    global $base_url;
    $config = $this->config('cms_content_sync.settings');

    $form['cms_content_sync_base_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Base URL'),
      '#default_value' => $config->get('cms_content_sync_base_url'),
      '#description' => $this->t('By default the global base_url provided by Drupal is used for the communication between the CMS Content Sync backend and Drupal. However, this setting allows you to override the base_url that should be used for the communication.
      Once this is set, all Settings must be reepxorted. This can be done by either saving them, or using <i>drush cms_content_synce</i>. Do not include a trailing slash.<br>The cms content sync base url could also be set within a settings.php file by adding: <i>$config["cms_content_sync.settings"]["cms_content_sync_base_url"] = "http://example.com";</i> to it.'),
      '#attributes' => [
        'placeholder' => $base_url,
      ],
    ];

    $form['import_dashboard'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Import dashboard configuration'),
    ];

    $form['import_dashboard']['cms_content_sync_enable_preview'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable preview'),
      '#default_value' => $config->get('cms_content_sync_enable_preview'),
      '#description' => $this->t('If you want to import content from this site on other sites via the UI ("Manual" import action) and you\'re using custom Preview display modes, check this box to actually export them so they become available on remote sites.'),
    ];

    $roles = user_role_names(TRUE);
    // The administrator will always have access.
    unset($roles['administrator']);

    $roles_having_access = [];
    foreach ($roles as $role => $label) {
      // Load role.
      $role = Role::load($role);
      if ($role->hasPermission('access cms content sync content overview') && $role->hasPermission('restful get cms_content_sync_import_entity') && $role->hasPermission('restful post cms_content_sync_import_entity')) {
        $roles_having_access[$role->id()] = $role->id();
      }
    }

    $form['import_dashboard']['access'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Access'),
      '#default_value' => $roles_having_access,
      '#description' => $this->t('Please select which roles should be able to access the import dashboard. This setting will automatically grant the required permissions, do not forgot to export the configuration before the next configuration import.'),
      '#options' => $roles,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    $base_url = $form_state->getValue('cms_content_sync_base_url');

    if (!empty($base_url) && Unicode::substr($base_url, -1) === '/') {
      $form_state->setErrorByName('cms_content_sync_base_url', 'Do not include a trailing slash.');
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('cms_content_sync.settings')
      ->set('cms_content_sync_base_url', $form_state->getValue('cms_content_sync_base_url'))
      ->save();
    $this->config('cms_content_sync.settings')
      ->set('cms_content_sync_enable_preview', boolval($form_state->getValue('cms_content_sync_enable_preview')))
      ->save();

    // Set import dashboard access permissions.
    $import_dashboard_access = $form_state->getValue('access');
    foreach ($import_dashboard_access as $role => $access) {
      // Load role.
      $role_obj = Role::load($role);
      if ($role === $access) {
        $role_obj->grantPermission('access cms content sync content overview');
        $role_obj->grantPermission('restful get cms_content_sync_import_entity');
        $role_obj->grantPermission('restful post cms_content_sync_import_entity');
        $role_obj->save();
      }
      else {
        $role_obj->revokePermission('access cms content sync content overview');
        $role_obj->revokePermission('restful get cms_content_sync_import_entity');
        $role_obj->revokePermission('restful post cms_content_sync_import_entity');
        $role_obj->save();
      }
    }
  }

}
