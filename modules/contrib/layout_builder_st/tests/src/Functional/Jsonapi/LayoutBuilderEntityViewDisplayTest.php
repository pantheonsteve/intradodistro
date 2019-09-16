<?php

namespace Drupal\Tests\layout_builder_st\Functional\Jsonapi;

use Drupal\layout_builder_st\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\Tests\layout_builder\Functional\Jsonapi\LayoutBuilderEntityViewDisplayTest as CoreTest;

/**
 * JSON:API integration test for the "EntityViewDisplay" config entity type.
 *
 * @group jsonapi
 * @group layout_builder
 */
class LayoutBuilderEntityViewDisplayTest extends CoreTest {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['layout_builder_st'];

  /**
   * {@inheritdoc}
   */
  protected function getExpectedDocument() {
    $document = parent::getExpectedDocument();
    $document['data']['attributes']['hidden'][OverridesSectionStorage::TRANSLATED_CONFIGURATION_FIELD_NAME] = TRUE;
    return $document;
  }

}
