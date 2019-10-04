<?php

namespace Drupal\Tests\bootstrap_simple_carousel\Unit;

use Drupal\Tests\PhpunitCompatibilityTrait;
use Drupal\Tests\UnitTestCase;

/**
 * @coversDefaultClass \Drupal\bootstrap_simple_carousel\Service\CarouselService
 * @group bootstrap_simple_carousel
 */
class CarouselServiceTest extends UnitTestCase {
  use PhpunitCompatibilityTrait;

  /**
   * The mocked renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface|\PHPUnit_Framework_MockObject_MockObject
   */
  protected $renderer;

  /**
   * The tested CarouselService.
   *
   * @var \Drupal\bootstrap_simple_carousel\Service\CarouselService
   */
  protected $carouselService;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $this->renderer = $this->createMock('\Drupal\Core\Render\RendererInterface');

    $this->carouselService = $this
      ->getMockBuilder('\Drupal\bootstrap_simple_carousel\Service\CarouselService')
      ->setMethods(['__construct'])
      ->disableOriginalConstructor()
      ->getMock();
  }

  /**
   * Tests the renderLink() method.
   *
   * @dataProvider providerTestRenderLink
   */
  public function testRenderLink($expected) {
    $this->renderer->expects($this->once())
      ->method('render')
      ->will($this->returnValue('<a href="http://example.com">example</a>'));
    $this->carouselService->setRenderer($this->renderer);
    $this->assertEquals($expected, $this->carouselService->renderLink('http://example.com', 'example'));
  }

  /**
   * Provides test data for providerTestRenderLink.
   *
   * @return array
   *   The test data.
   */
  public function providerTestRenderLink() {
    return [['<a href="http://example.com">example</a>']];
  }

}
