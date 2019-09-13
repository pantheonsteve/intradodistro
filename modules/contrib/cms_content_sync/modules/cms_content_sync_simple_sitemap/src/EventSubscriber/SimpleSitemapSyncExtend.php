<?php

namespace Drupal\cms_content_sync_simple_sitemap\EventSubscriber;

use Drupal\cms_content_sync\Event\BeforeEntityExport;
use Drupal\cms_content_sync\Event\BeforeEntityTypeExport;
use Drupal\cms_content_sync\Event\BeforeEntityImport;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriptions for events dispatched by SimpleFbConnect.
 */
class SimpleSitemapSyncExtend implements EventSubscriberInterface {

  const SITEMAP_FIELD_NAME = 'simple_sitemap';

  /**
   * Returns an array of event names this subscriber wants to listen to.
   *
   * @return array
   *   The event names to listen to
   */
  public static function getSubscribedEvents() {
    $events[BeforeEntityExport::EVENT_NAME][] = ['extendExport'];
    $events[BeforeEntityImport::EVENT_NAME][] = ['extendImport'];
    $events[BeforeEntityTypeExport::EVENT_NAME][] = ['extendEntityType'];
    return $events;
  }

  /**
   * Basically copied from different parts of the simple sitemap module.
   * Must be updated when the logic of simple sitemap changes.
   *
   * @param string $entity_type_name
   * @param string $bundle_name
   *
   * @return bool Whether or not the simple sitemap module supports configuring
   *   sitemap settings for the given entity type + bundle.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function sitemapSupportsEntityType($entity_type_name, $bundle_name) {
    /**
     * @var \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
     */
    $entity_type_manager = \Drupal::service('entity_type.manager');
    $entity_type = $entity_type_manager->getDefinition($entity_type_name);

    if (!$entity_type instanceof ContentEntityTypeInterface
      || !method_exists($entity_type, 'getBundleEntityType')
      || !$entity_type->hasLinkTemplate('canonical')) {
      return FALSE;
    }

    /**
     * @var \Drupal\Core\Config\ConfigFactory $config_factory
     */
    $config_factory = \Drupal::service('config.factory');

    $setting = $config_factory
      ->get('simple_sitemap.settings')
      ->get('enabled_entity_types');
    if (empty($setting) || !in_array($entity_type_name, $setting)) {
      return FALSE;
    }

    $bundle_settings = $config_factory
      ->get("simple_sitemap.bundle_settings.$entity_type_name.$bundle_name")
      ->get();

    // @ToDo: Add support for multiple sitemap variants which have been
    // added by Simple Sitemap Version 3.0.
    if (empty($bundle_settings)) {
      $bundle_settings = $config_factory
        ->get("simple_sitemap.bundle_settings.default.$entity_type_name.$bundle_name")
        ->get();
    }
    if (empty($bundle_settings) || empty($bundle_settings['index'])) {
      return FALSE;
    }

    return TRUE;
  }

  /**
   * Add the field to the entity type for the simple sitemap configuration.
   *
   * @param \Drupal\cms_content_sync\Event\BeforeEntityTypeExport $event
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function extendEntityType(BeforeEntityTypeExport $event) {
    if (!$this->sitemapSupportsEntityType($event->getEntityTypeName(), $event->getBundleName())) {
      return;
    }

    $event->addField(self::SITEMAP_FIELD_NAME, 'object', FALSE);
  }

  /**
   * Alter the export to include the sitemap settings, if enabled for the entity
   * type and cached by the form values.
   * Will not support programmatically added sitemap settings, so that's not
   * supported out of the box.
   *
   * @param \Drupal\cms_content_sync\Event\BeforeEntityExport $event
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function extendExport(BeforeEntityExport $event) {
    $intent = $event->intent;
    $entity = $event->entity;

    if (!$this->sitemapSupportsEntityType($entity->getEntityTypeId(), $entity->bundle())) {
      return;
    }

    $values = _cms_content_sync_submit_cache($entity->getEntityTypeId(), $entity->uuid());

    // Fix for values appearing in a sub array on a commerce product entity.
    $values = isset($values['simple_sitemap']) ? $values['simple_sitemap'] : $values;

    if (empty($values)) {
      return;
    }

    $values = [
      'index' => $values['simple_sitemap_index_content'],
      'priority' => $values['simple_sitemap_priority'],
      'changefreq' => $values['simple_sitemap_changefreq'],
      'include_images' => $values['simple_sitemap_include_images'],
    ];

    $intent->setField(self::SITEMAP_FIELD_NAME, $values);
  }

  /**
   * @param \Drupal\cms_content_sync\Event\BeforeEntityImport $event
   *
   * @internal param $entity
   * @internal param $intent
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function extendImport(BeforeEntityImport $event) {
    $intent = $event->intent;

    $entity = $intent->getEntity();
    if (!$this->sitemapSupportsEntityType($entity->getEntityTypeId(), $entity->bundle())) {
      return;
    }

    $values = $intent->getField(self::SITEMAP_FIELD_NAME);
    if (empty($values)) {
      return;
    }

    /**
     * @var \Drupal\simple_sitemap\Simplesitemap $generator
     */
    $generator = \Drupal::service('simple_sitemap.generator');

    $generator->setEntityInstanceSettings(
      $entity->getEntityTypeId(),
      $entity->id(),
      $values
    );
  }

}
