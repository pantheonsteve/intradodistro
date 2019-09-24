<?php

namespace Drupal\image_effects\Component;

/**
 * Image handling methods for image_effects.
 */
abstract class ImageUtility {

  /**
   * Computes a length based on a length specification and an actual length.
   *
   * Examples:
   *  (50, 400) returns 50; (50%, 400) returns 200;
   *  (50, null) returns 50; (50%, null) returns null;
   *  (null, null) returns null; (null, 100) returns null.
   *
   * @param string|null $length_specification
   *   The length specification. An integer value or a % specification.
   * @param int|null $current_length
   *   The current length. May be null.
   *
   * @return int|null
   *   The computed length.
   */
  public static function percentFilter($length_specification, $current_length) {
    if (strpos($length_specification, '%') !== FALSE) {
      $length_specification = $current_length !== NULL ? str_replace('%', '', $length_specification) * 0.01 * $current_length : NULL;
    }
    return is_null($length_specification) ? NULL : (int) $length_specification;
  }

  /**
   * Determines the dimensions of a resized image.
   *
   * Based on the current size and resize specification.
   *
   * @param int|null $source_width
   *   Source image width.
   * @param int|null $source_height
   *   Source image height.
   * @param string|null $width_specification
   *   The width specification. An integer value or a % specification.
   * @param string|null $height_specification
   *   The height specification. An integer value or a % specification.
   * @param bool $square
   *   (Optional) when TRUE and one of the specifications is NULL, will return
   *   the same value for width and height.
   *
   * @return array
   *   Associative array.
   *   - width: Integer with the resized image width.
   *   - height: Integer with the resized image height.
   */
  public static function resizeDimensions($source_width, $source_height, $width_specification, $height_specification, $square = FALSE) {
    $dimensions = [];
    $dimensions['width'] = static::percentFilter($width_specification, $source_width);
    $dimensions['height'] = static::percentFilter($height_specification, $source_height);

    if (is_null($dimensions['width']) && is_null($dimensions['height'])) {
      return $dimensions;
    }

    if (!$dimensions['width'] || !$dimensions['height']) {
      if (is_null($source_width) || is_null($source_height)) {
        $dimensions['width'] = NULL;
        $dimensions['height'] = NULL;
      }
      else {
        if ($square) {
          $aspect_ratio = 1;
        }
        else {
          $aspect_ratio = $source_height / $source_width;
        }
        if ($dimensions['width'] && !$dimensions['height']) {
          $dimensions['height'] = (int) round($dimensions['width'] * $aspect_ratio);
        }
        elseif (!$dimensions['width'] && $dimensions['height']) {
          $dimensions['width'] = (int) round($dimensions['height'] / $aspect_ratio);
        }
      }
    }

    return $dimensions;
  }

}
