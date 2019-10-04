<?php

namespace Drupal\bootstrap_simple_carousel\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\image\Entity\ImageStyle;

/**
 * Class SettingsForm.
 *
 * Provides a settings form.
 *
 * @package Drupal\bootstrap_simple_carousel\Form
 */
class SettingsForm extends ConfigFormBase {

  const ORIGINAL_IMAGE_STYLE_ID = 'original';

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'bootstrap_simple_carousel_admin_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['bootstrap_simple_carousel.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('bootstrap_simple_carousel.settings');

    $form['interval'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Interval'),
      '#description' => $this->t('The amount of time to delay between automatically cycling an item. If false, carousel will not automatically cycle.'),
      '#default_value' => $config->get('interval'),
    ];

    $form['wrap'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Wrap'),
      '#description' => $this->t('Whether the carousel should cycle continuously or have hard stops.'),
      '#default_value' => $config->get('wrap'),
    ];

    $form['pause'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Pause on hover'),
      '#description' => $this->t("If is checked, pauses the cycling of the carousel on mouseenter and resumes the cycling of the carousel on mouseleave. If is unchecked, hovering over the carousel won't pause it."),
      '#default_value' => $config->get('pause'),
    ];

    $form['indicators'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Indicators'),
      '#description' => $this->t('Show carousel indicators'),
      '#default_value' => $config->get('indicators'),
    ];

    $form['controls'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Controls'),
      '#description' => $this->t('Show carousel arrows (next/prev).'),
      '#default_value' => $config->get('controls'),
    ];

    $form['assets'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Assets'),
      '#description' => $this->t("Includes bootstrap framework v4.0.0, don't check it, if you use the bootstrap theme, or the bootstrap framework are already included."),
      '#default_value' => $config->get('assets'),
    ];

    $form['image_fluid'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Image fluid'),
      '#description' => $this->t("Adds to image the bootstrap img-fluid class."),
      '#default_value' => $config->get('image_fluid'),
    ];

    $form['image_style'] = [
      '#type' => 'select',
      '#title' => $this->t('Image style'),
      '#description' => $this->t('Image style for carousel items. If you will be use the image styles for bootstrap items, you need to set up the same width for the "bootstrap carousel" container.'),
      '#options' => $this->getImagesStyles(),
      '#default_value' => $config->get('image_style'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Return images styles.
   *
   * @return array
   *   Image styles list
   */
  protected function getImagesStyles() {
    $styles = ImageStyle::loadMultiple();

    $options = [
      static::ORIGINAL_IMAGE_STYLE_ID => $this->t('Original image'),
    ];
    foreach ($styles as $key => $value) {
      $options[$key] = $value->get('label');
    }

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable('bootstrap_simple_carousel.settings')
      ->set('interval', $form_state->getValue('interval'))
      ->set('wrap', $form_state->getValue('wrap'))
      ->set('pause', $form_state->getValue('pause'))
      ->set('indicators', $form_state->getValue('indicators'))
      ->set('controls', $form_state->getValue('controls'))
      ->set('assets', $form_state->getValue('assets'))
      ->set('image_fluid', $form_state->getValue('image_fluid'))
      ->set('image_style', $form_state->getValue('image_style'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
