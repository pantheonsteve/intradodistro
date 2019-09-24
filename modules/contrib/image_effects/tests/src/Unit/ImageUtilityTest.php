<?php

namespace Drupal\Tests\image_effects\Unit;

use Drupal\image_effects\Component\ImageUtility;
use PHPUnit\Framework\TestCase;

/**
 * Tests the image utility helper methods.
 *
 * @coversDefaultClass \Drupal\image_effects\Component\ImageUtility
 *
 * @group Image Effects
 */
class ImageUtilityTest extends TestCase {

  /**
   * Data provider for testPercentFilter.
   */
  public function percentFilterProvider() {
    return [
      [50, 400, 50],
      ['50', 400, 50],
      ['50%', 400, 200],
      [50, NULL, 50],
      ['50', NULL, 50],
      ['50%', NULL, NULL],
      [NULL, 100, NULL],
      [NULL, NULL, NULL],
      ['10%', 400, 40],
      ['100%', 400, 400],
      ['150%', 400, 600],
    ];
  }

  /**
   * @covers ::percentFilter
   * @dataProvider percentFilterProvider
   */
  public function testPercentFilter($length_specification, $current_length, $expected_result) {
    $this->assertSame($expected_result, ImageUtility::percentFilter($length_specification, $current_length));
  }

  /**
   * Data provider for testResizeDimensions.
   */
  public function resizeDimensionsProvider() {
    return [
      // Square = FALSE.
      [NULL, 100, 50, 25, FALSE, 50, 25],
      [200, NULL, 50, 25, FALSE, 50, 25],
      [NULL, NULL, 50, 25, FALSE, 50, 25],
      [200, 100, 50, 25, FALSE, 50, 25],
      [NULL, 100, '50%', '25%', FALSE, NULL, NULL],
      [200, NULL, '50%', '25%', FALSE, NULL, NULL],
      [NULL, NULL, '50%', '25%', FALSE, NULL, NULL],
      [200, 100, '50%', '25%', FALSE, 100, 25],
      [200, 100, '50%', '150%', FALSE, 100, 150],
      [200, 100, '150%', '10%', FALSE, 300, 10],
      [NULL, 100, '50', '25%', FALSE, 50, 25],
      [200, NULL, '50%', '25', FALSE, 100, 25],
      [200, 100, '50%', NULL, FALSE, 100, 50],
      [200, 100, NULL, '50%', FALSE, 100, 50],
      [40, 20, '100%', 0, FALSE, 40, 20],
      [40, 20, 0, '100%', FALSE, 40, 20],
      // Square = TRUE.
      [200, 100, 30, NULL, TRUE, 30, 30],
      [200, 100, NULL, 35, TRUE, 35, 35],
      [200, 100, '50%', NULL, TRUE, 100, 100],
      [200, 100, NULL, '50%', TRUE, 50, 50],
      [40, 20, '100%', 0, TRUE, 40, 40],
      [40, 20, 0, '100%', TRUE, 20, 20],
    ];
  }

  /**
   * @covers ::resizeDimensions
   * @dataProvider resizeDimensionsProvider
   */
  public function testResizeDimensions($source_width, $source_height, $width_specification, $height_specification, $square, $expected_width, $expected_height) {
    $result = ImageUtility::resizeDimensions($source_width, $source_height, $width_specification, $height_specification, $square);
    $this->assertSame($expected_width, $result['width']);
    $this->assertSame($expected_height, $result['height']);
  }

}
