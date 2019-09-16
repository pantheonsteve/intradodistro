<?php

namespace Drupal\Tests\layout_library\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests accessing the layout library.
 *
 * @group layout_library
 */
class LibraryAccessTest extends BrowserTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['layout_library'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->layoutAdmin = $this->drupalCreateUser(['configure any layout']);
  }

  /**
   * Tests accessing the library listing.
   */
  public function testLibraryListing() {
    $session = $this->assertSession();
    $this->drupalGet('admin/structure/layouts');
    $session->statusCodeEquals('403');

    $this->drupalLogin($this->layoutAdmin);
    $this->drupalGet('admin/structure/layouts');
    $session->statusCodeEquals('200');
  }

}
