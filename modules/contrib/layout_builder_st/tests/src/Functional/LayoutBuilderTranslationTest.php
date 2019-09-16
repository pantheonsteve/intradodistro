<?php

namespace Drupal\Tests\layout_builder_st\Functional;

use Drupal\field\Entity\FieldConfig;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\Tests\content_translation\Functional\ContentTranslationTestBase;
use Drupal\Core\Entity\Entity\EntityViewDisplay;
use Drupal\Core\Url;

/**
 * Tests that the Layout Builder UI works with translated content.
 *
 * @group layout_builder
 */
class LayoutBuilderTranslationTest extends ContentTranslationTestBase {

  use TranslationTestTrait;

  /**
   * {@inheritdoc}
   */
  public static $modules = [
    'content_translation',
    'contextual',
    'entity_test',
    'layout_builder_st',
    'block',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();
    $this->setUpViewDisplay();
    $this->setUpEntities();
  }

  /**
   * Tests that the Layout Builder UI works with translated content.
   */
  public function testLayoutPerTranslation() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $entity_url = $this->entity->toUrl('canonical')->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $layout_url = $entity_url . '/layout';
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    // If there is not a layout override the layout translation is not
    // accessible.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    // Ensure that the tempstore varies per-translation.
    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    // Adjust the layout of the original entity.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->pressButton('Add Block');

    $assert_session->pageTextContains('Powered by Drupal');

    // Confirm the tempstore for the translated layout is not affected.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    $this->drupalGet($layout_url);
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');
    $assert_session->pageTextContains('Powered by Drupal');

    // Ensure that the layout change propagates to the translated entity.
    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextContains('Powered by Drupal');

    // Confirm that layout translation page is accessible once the untranslated
    // entity has a override.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('Access denied');
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');

    $this->assertNonTranslationActionsRemoved();

  }

  /**
   * Tests that access is denied to a layout translation if there is override.
   */
  public function testLayoutTranslationNoOverride() {
    $assert_session = $this->assertSession();

    $entity_url = $this->entity->toUrl('canonical')->toString();
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    // If there is not a layout override the layout translation is not
    // accessible.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');
  }

  /**
   * Tests access to layout translation if the layout field is translatable.
   */
  public function testTranslatableLayoutField() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    $field_storage = FieldStorageConfig::loadByName('entity_test_mul', OverridesSectionStorage::FIELD_NAME);
    $this->assertNotEmpty($field_storage);
    $field_storage->setTranslatable(TRUE);
    $this->assertNotEmpty($field_storage->save());
    $field_config = FieldConfig::loadByName('entity_test_mul', 'entity_test_mul', OverridesSectionStorage::FIELD_NAME);
    $this->assertNotEmpty($field_config);
    $field_config->setTranslatable(TRUE);
    $this->assertNotEmpty($field_config->save());


    $entity_url = $this->entity->toUrl('canonical')->toString();
    $layout_url = $entity_url . '/layout';
    $language = \Drupal::languageManager()->getLanguage($this->langcodes[2]);
    $translated_entity_url = $this->entity->toUrl('canonical', ['language' => $language])->toString();
    $translated_layout_url = $translated_entity_url . '/layout';

    $this->drupalGet($entity_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    $this->drupalGet($translated_entity_url);
    $assert_session->pageTextNotContains('The untranslated field value');
    $assert_session->pageTextContains('The translated field value');

    // If there is not a layout override the layout translation is not
    // accessible.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    // Ensure that the tempstore varies per-translation.
    $this->drupalGet($layout_url);
    $assert_session->pageTextNotContains('The translated field value');
    $assert_session->pageTextContains('The untranslated field value');

    // Adjust the layout of the original entity.
    $assert_session->linkExists('Add Block');
    $this->clickLink('Add Block');
    $assert_session->linkExists('Powered by Drupal');
    $this->clickLink('Powered by Drupal');
    $page->pressButton('Add Block');

    $assert_session->pageTextContains('Powered by Drupal');

    // Confirm the tempstore for the translated layout is not affected.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextContains('Access denied');

    $this->drupalGet($layout_url);
    $assert_session->pageTextContains('Powered by Drupal');
    $assert_session->buttonExists('Save layout');
    $page->pressButton('Save layout');

    $this->drupalGet($entity_url);
    $assert_session->pageTextContains('Powered by Drupal');


    // Confirm the translation layout is still not allowed.
    $this->drupalGet($translated_layout_url);

    $assert_session->pageTextContains('Access denied');

    // Update the layout field to be not translatable.
    $field_config = FieldConfig::loadByName('entity_test_mul', 'entity_test_mul', OverridesSectionStorage::FIELD_NAME);
    $this->assertNotEmpty($field_config);
    $field_config->setTranslatable(FALSE);
    $this->assertNotEmpty($field_config->save());

    // Confirm the translation layout is still not allowed.
    $this->drupalGet($translated_layout_url);
    $assert_session->pageTextNotContains('Access denied');
    $assert_session->buttonExists('Save layout');
  }
  /**
   * The entity used for testing.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  protected function getAdministratorPermissions() {
    $permissions = parent::getAdministratorPermissions();
    $permissions[] = 'administer entity_test_mul display';
    return $permissions;
  }

  /**
   * {@inheritdoc}
   */
  protected function getTranslatorPermissions() {
    $permissions = parent::getTranslatorPermissions();
    $permissions[] = 'view test entity translations';
    $permissions[] = 'view test entity';
    $permissions[] = 'configure any layout';
    return $permissions;
  }

  /**
   * Setup translated entity with layouts.
   */
  protected function setUpEntities() {
    $this->drupalLogin($this->administrator);

    $field_ui_prefix = 'entity_test_mul/structure/entity_test_mul';
    // Allow overrides for the layout.
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[enabled]' => TRUE], 'Save');
    $this->drupalPostForm("$field_ui_prefix/display/default", ['layout[allow_custom]' => TRUE], 'Save');

    // @todo The Layout Builder UI relies on local tasks; fix in
    //   https://www.drupal.org/project/drupal/issues/2917777.
    $this->drupalPlaceBlock('local_tasks_block');

    // Create a test entity.
    $id = $this->createEntity([
      $this->fieldName => [['value' => 'The untranslated field value']],
    ], $this->langcodes[0]);
    $storage = $this->container->get('entity_type.manager')
      ->getStorage($this->entityTypeId);
    $storage->resetCache([$id]);
    $this->entity = $storage->load($id);

    // Create a translation.
    $this->drupalLogin($this->translator);
    $add_translation_url = Url::fromRoute("entity.$this->entityTypeId.content_translation_add", [
      $this->entityTypeId => $this->entity->id(),
      'source' => $this->langcodes[0],
      'target' => $this->langcodes[2],
    ]);
    $this->drupalPostForm($add_translation_url, [
      "{$this->fieldName}[0][value]" => 'The translated field value',
    ], 'Save');
  }

  /**
   * Set up the View Display.
   */
  protected function setUpViewDisplay() {
    EntityViewDisplay::create([
      'targetEntityType' => $this->entityTypeId,
      'bundle' => $this->bundle,
      'mode' => 'default',
      'status' => TRUE,
    ])->setComponent($this->fieldName, ['type' => 'string'])->save();
  }

}
