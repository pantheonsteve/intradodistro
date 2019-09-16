<?php

namespace Drupal\schemata;

use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\schemata\Schema\NodeSchema;
use Drupal\schemata\Schema\Schema;
use Psr\Log\LoggerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Drupal\Core\TypedData\TypedDataManager;

/**
 * Create an object of type Drupal\schemata\Schema\SchemaInterface.
 *
 * Identifying a specific classed object to use is currently handled by a
 * mapping function in the create() method. Swapping out different
 * SchemaInterface implementations is not currently effective.
 */
class SchemaFactory {

  /**
   * Logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * EntityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * EntityTypeBundleInfo.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * TypedDataManager.
   *
   * @var \Drupal\Core\TypedData\TypedDataManager
   */
  protected $typedDataManager;

  /**
   * Constructs a SchemaBuilder.
   *
   * @param \Psr\Log\LoggerInterface $logger
   *   Logger.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The EntityTypeManager to extract details of entity types.
   * @param \Drupal\Core\Entity\EntityTypeBundleInfo $entity_type_bundle_info
   *   The EntityTypeManager to extract details of entity types.
   * @param \Drupal\Core\TypedData\TypedDataManager $typed_data_manager
   *   The TypedDataManager to extract meaning of individual Entity properties.
   */
  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, EntityTypeBundleInfo $entity_type_bundle_info, TypedDataManager $typed_data_manager) {
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->typedDataManager = $typed_data_manager;
  }

  /**
   * Assemble a schema object based on the requested entity type.
   *
   * @param string $entity_type
   *   URL input specifying an entity type to be processed.
   * @param string $bundle
   *   URL input specifying an entity bundle to be processed. May be NULL
   *   for support of entities that do not have bundles.
   *
   * @return \Drupal\schemata\Schema\SchemaInterface
   *   A Schema object which can be processed as a Rest Resource response.
   */
  public function create($entity_type, $bundle = NULL) {
    try {
      $entity_type_plugin = $this->getSourceEntityPlugin($entity_type);
    }
    catch(\Exception $e) {
      $this->logger->error($e->getMessage());
      // @todo Handle these exceptions in https://www.drupal.org/node/2868562.
      return NULL;
    }

    if ($entity_type_plugin->getBundleEntityType()) {
      $bundles = $this->entityTypeBundleInfo->getBundleInfo($entity_type);
    }
    if (!empty($bundle) && !array_key_exists($bundle, $bundles)) {
      $this->logger->warning('Specified Entity Bundle "%bundle" does not exist.', [
        '%bundle' => $bundle,
      ]);
      return NULL;
    }

    $class = $entity_type === 'node' && !empty($bundle)
      ? NodeSchema::class
      : Schema::class;

    $data_definition = $this->dataDefinitionFactory($entity_type, $bundle);
    $schema = new $class(
      $data_definition,
      $bundle,
      $data_definition->getPropertyDefinitions()
    );

    $this->logger->notice('Schema generated for Entity Type (%entity_type) and Bundle (%bundle).', [
      '%entity_type' => $entity_type,
      '%bundle' => empty($bundle) ? 'N/A' : $bundle,
    ]);

    return $schema;
  }

  /**
   * Creates the data definition object.
   *
   * @param string $entity_type
   *   The entity type ID.
   * @param string $bundle
   *   The bundle name.
   *
   * @return \Drupal\Core\Entity\TypedData\EntityDataDefinition
   *   The data definition.
   */
  protected function dataDefinitionFactory($entity_type, $bundle) {
    $key = sprintf('entity:%s', $entity_type);
    if (!empty($bundle)) {
      $key .= ':' . $bundle;
    }
    return $this->typedDataManager->createDataDefinition($key);
  }

  /**
   * Load the Entity Type Plugin to drive schema content.
   *
   * This method should incorporate any "validation" of the entity type.
   *
   * It is broken out in part to facilitate test creation.
   *
   * @param string $entity_type_id
   *   Entity Type ID.
   *
   * @return \Drupal\Core\Entity\EntityTypeInterface
   *   The Entity Type plugin.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *   Thrown if $entity_type_id does not refer to a valid Entity Type plugin.
   *
   * @throws \InvalidArgumentException
   *   Thrown if $entity_type_id does not refer to a Content Entity Type.
   *
   * @see \Drupal\Tests\schemata\Functional\ValidateSchemaTest::validateSchemaAsJsonSchema()
   */
  public function getSourceEntityPlugin($entity_type_id) {
    $entity_type_plugin = $this->entityTypeManager->getDefinition($entity_type_id);
    if (!($entity_type_plugin->entityClassImplements(ContentEntityInterface::class))) {
      throw new \InvalidArgumentException(sprintf('Entity Type %s is not a content entity. Only content entities are supported at this time.', $entity_type_id));
    }

    return $entity_type_plugin;
  }



}
