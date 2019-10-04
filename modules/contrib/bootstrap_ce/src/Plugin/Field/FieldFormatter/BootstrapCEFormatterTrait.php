<?php

namespace Drupal\bootstrap_ce\Plugin\Field\FieldFormatter;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Field\FieldItemListInterface;

/**
 * Provides formatter settings and theme rendering for all the Bootstrap Carousel entities and medias
 * formatter.
 * 
 */
trait BootstrapCEFormatterTrait {

  /**
   * @see Drupal\Core\Field\PluginSettingsInterface::defaultSettings()
   */
  public static function defaultSettings() {
    return [
      'interval' => 3,
        ] + parent::defaultSettings();
  }

  /**
   * @see Drupal\Core\Field\FormatterInterface::settingsForm($form, $form_state)
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    //Interval duration in seconds.
    $elements['interval'] = [
      '#type' => 'number',
      '#default_value' => $this->getSetting('interval'),
      '#min' => 0,
      '#max' => 100,
      '#size' => 3,
      '#title' => $this->t('Slide interval'),
      '#description' => $this->t('Indicates slide interval duration in seconds. Set 0 (zero) for no duration.')
    ];

    return $elements;
  }

  /**
   * @see Drupal\Core\Field\FormatterInterface::settingsSummary()
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();

    $interval = $this->getSetting('interval');
    $summary[] = !empty($interval) ? $this->formatPlural($interval, 'Slide interval duration time: 1 second.', 'Slide interval duration time: @count seconds.') : $this->t('No interval duration time.');

    return $summary;
  }

  /**
   * @see Drupal\Core\Field\FormatterInterface::viewElements($items, $langcode)
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {

    $elements = parent::viewElements($items, $langcode);
    $info = [
      '#theme' => 'bootstrap_ce_formatter',
      '#entities' => parent::viewElements($items, $langcode),
      '#interval' => $this->getSetting('interval') * 1000,
      '#carousel_id' => 'carousel-' . $items->getEntity()->id().'-'.$items->getName(),
      '#field_name' => $items->getName(),
      '#bundle' => $items->getEntity()->bundle(),
      '#entity_id' => $items->getEntity()->id()
    ];
    $elements = array_merge($info, $elements);

    return $elements;
  }

}
