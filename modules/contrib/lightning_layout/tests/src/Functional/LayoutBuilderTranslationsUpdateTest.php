<?php

namespace Drupal\Tests\lightning_layout\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;

/**
 * @group lightning_layout
 */
class LayoutBuilderTranslationsUpdateTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles() {
    $this->databaseDumpFiles = [
      __DIR__ . '/../../fixtures/2.0.0.php.gz',
    ];
  }

  public function test() {
    // The landing page content type's third-party settings are not relevant to
    // the test, and will fail config schema checks if they are present (since
    // they contain settings for Lightning Workflow).
    $this->config('node.type.landing_page')
      ->set('third_party_settings', [])
      ->save();

    $this->runUpdates();
    $this->assertTrue($this->container->get('module_handler')->moduleExists('layout_builder_st'));
  }

}
