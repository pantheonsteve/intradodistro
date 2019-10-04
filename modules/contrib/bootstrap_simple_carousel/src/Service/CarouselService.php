<?php

namespace Drupal\bootstrap_simple_carousel\Service;

use Drupal\Core\Render\Renderer;
use Drupal\file\Entity\File;

/**
 * CarouselService Class.
 *
 * Provides functions for the module.
 *
 * @category Class
 * @package Drupal\bootstrap_simple_carousel\Service
 */
class CarouselService {

  protected static $service;

  /**
   * This will hold Renderer object.
   *
   * @var \Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * CarouselService constructor.
   */
  protected function __construct() {
    $this->setRenderer(\Drupal::service('renderer'));
  }

  /**
   * Disallow clone.
   */
  protected function __clone() {
  }

  /**
   * Return singleton object.
   *
   * @return static
   *   Singleton
   */
  public static function getInstance() {
    if (empty(static::$service)) {
      static::$service = new static();
    }

    return static::$service;
  }

  /**
   * Set renderer.
   *
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   */
  public function setRenderer(Renderer $renderer) {
    $this->renderer = $renderer;
  }

  /**
   * Return a rendered image.
   *
   * @param int $image_id
   *   The image id for the carousel.
   * @param string $image_style
   *   The image style for the carousel.
   * @param array $params
   *   An array of parameters.
   *
   * @throws \Exception
   *
   * @return string
   *   Rendered image
   */
  public function renderImageById($image_id, $image_style = 'thumbnail', array $params = []) {
    $image = '';
    $imageFile = File::load($image_id);

    if (!empty($imageFile)) {
      $imageTheme = [
        '#theme' => 'image_style',
        '#style_name' => $image_style,
        '#uri' => $imageFile->getFileUri(),
        '#alt' => $params['alt'] ?? '',
        '#title' => $params['title'] ?? '',
      ];

      $image = $this->renderer->render($imageTheme);
    }

    return $image;
  }

  /**
   * Return a Render Link.
   *
   * @param string $url
   *   The url for the render link.
   * @param string $title
   *   The title for the render link.
   * @param array $attributes
   *   The array of attributes.
   *
   * @throws \Exception
   *
   * @return string
   *   Rendered link
   */
  public function renderLink($url, $title, array $attributes = []) {
    $linkTheme = [
      '#type' => 'link',
      '#title' => $title,
      '#url' => $url,
      '#options' => [
        'attributes' => $attributes,
        'html' => FALSE,
      ],
    ];

    $link = $this->renderer->render($linkTheme);

    return $link;
  }

  /**
   * Return the statuses.
   *
   * @return array
   *   Of statuses
   */
  public function getStatuses() {
    return [
      0 => t('Inactive'),
      1 => t('Active'),
    ];
  }

}
