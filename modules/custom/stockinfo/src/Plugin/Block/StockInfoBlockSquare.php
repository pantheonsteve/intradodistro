<?php

namespace Drupal\stockinfo\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormStateInterface;

/**
 * Provides a 'Stock Info Block - Square' Block.
 *
 * @Block(
 *   id = "stockinfoblocksquare",
 *   admin_label = @Translation("Stock Info block - Square"),
 *   category = @Translation("Intrado IR"),
 * )
 */
class StockInfoBlockSquare extends BlockBase implements BlockPluginInterface {

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['stock_symbol'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Stock Symbol'),
      '#description' => $this->t('Enter a Stock Symbol Here'),
      '#default_value' => isset($config['stock_symbol']) ? $config['stock_symbol'] : '',
    ];

    $form['stock_price'] = [
      '#type' => 'number',
      '#title' => $this->t('Price'),
      '#step' => '.01',
      '#description' => $this->t('Current stock price'),
      '#default_value' => isset($config['stock_price']) ? $config['stock_price'] : '',
    ];

    $form['stock_change'] = [
      '#type' => 'number',
      '#title' => $this->t('Change'),
      '#step' => '.01',
      '#description' => $this->t('Change from start of day'),
      '#default_value' => isset($config['stock_change']) ? $config['stock_change'] : '',
    ];

    $form['stock_volume'] = [
      '#type' => 'number',
      '#title' => $this->t('Volume'),
      '#step' => '.01',
      '#description' => $this->t('Current stock trading volume'),
      '#default_value' => isset($config['stock_volume']) ? $config['stock_volume'] : '',
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    $values = $form_state->getValues();
    $this->configuration['stock_symbol'] = $values['stock_symbol'];
    $this->configuration['stock_price'] = $values['stock_price'];
    $this->configuration['stock_change'] = $values['stock_change'];
    $this->configuration['stock_volume'] = $values['stock_volume'];
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $stockinfo = NULL;
    $stockinfo = new \stdClass();
    $stockinfo->name = $this->configuration['stock_symbol'];
    $stockinfo->price = $this->configuration['stock_price'];
    $stockinfo->change = $this->configuration['stock_change'];
    $stockinfo->volume = $this->configuration['stock_volume'];

    return [
      '#theme' => 'stockinfo_block_square',
      '#symbol' => $stockinfo->name,
      '#price' => $stockinfo->price,
      '#change' => $stockinfo->change,
      '#volume' => $stockinfo->volume,
      '#attached' => [
          'library' => [
            'stockinfo/stockinfo-block-square',
          ],
      ],
    ];
  }

}
