<?php

namespace Drupal\schemata_json_schema\Normalizer\jsonapi;

use Drupal\Component\Utility\NestedArray;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\schemata\Schema\SchemaInterface;
use Drupal\schemata\SchemaUrl;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Primary normalizer for SchemaInterface objects.
 */
class SchemataSchemaNormalizer extends JsonApiNormalizerBase {

  /**
   * The interface or class that this Normalizer supports.
   *
   * @var string
   */
  protected $supportedInterfaceOrClass = 'Drupal\schemata\Schema\SchemaInterface';

  /**
   * {@inheritdoc}
   */
  public function normalize($entity, $format = NULL, array $context = []) {
    /* @var $entity \Drupal\schemata\Schema\SchemaInterface */
    $generated_url = SchemaUrl::fromSchema($this->format, $this->describedFormat, $entity)
      ->toString(TRUE);
    // Create the array of normalized fields, starting with the URI.
    /** @var \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository */
    $resource_type_repository = \Drupal::service('jsonapi.resource_type.repository');
    $resource_type = $resource_type_repository->get(
      $entity->getEntityTypeId(),
      $entity->getBundleId() ?: $entity->getEntityTypeId()
    );
    $normalized = [
      '$schema' => 'http://json-schema.org/draft-06/schema#',
      'title' => 'JSON:API Schema',
      'description' => 'This is a schema for responses in the JSON:API format. For more, see http://jsonapi.org',
      'id' => $generated_url->getGeneratedUrl(),
      'type' => 'object',
      'required' => [
        'data',
      ],
      'properties' => [
        'data' => [
          'description' => '\"Resource objects\" appear in a JSON:API document to represent resources.',
          'type' => 'object',
          'required' => [
            'type',
            'id',
          ],
          'properties' => [
            'type' => [
              'type' => 'string',
              'title' => 'type',
              'description' => t('Resource type'),
              'enum' => [$resource_type->getTypeName()],
            ],
            'id' => [
              'type' => 'string',
              'title' => t('Resource ID'),
              'format' => 'uuid',
              'maxLength' => 128,
            ],
            'attributes' => [
              'description' => 'Members of the attributes object (\'attributes\") represent information about the resource object in which it\'s defined . ',
              'type' => 'object',
              'additionalProperties' => FALSE,
            ],
            'relationships' => [
              'description' => 'Members of the relationships object(\'relationships\") represent references from the resource object in which it\'s defined to other resource objects . ',
              'type' => 'object',
              'additionalProperties' => FALSE,
            ],
            'links' => [
              'type' => 'object',
              'additionalProperties' => [
                'description' => 'A link **MUST** be represented as either: a string containing the link\'s URL or a link object . ',
                'type' => 'object',
                'required' => [
                  'href',
                ],
                'properties' => [
                  'href' => [
                    'description' => 'A string containing the link\'s URL . ',
                    'type' => 'string',
                    'format' => 'uri - reference',
                  ],
                  'meta' => [
                    'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
                    'type' => 'object',
                    'additionalProperties' => TRUE,
                  ],
                ],
              ],
            ],
            'meta' => [
              'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
              'type' => 'object',
              'additionalProperties' => TRUE,
            ],
          ],
          'additionalProperties' => FALSE,
        ],
        'meta' => [
          'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
          'type' => 'object',
          'additionalProperties' => TRUE,
        ],
        'links' => [
          'type' => 'object',
          'additionalProperties' => [
            'description' => 'A link **MUST** be represented as either: a string containing the link\'s URL or a link object . ',
            'type' => 'object',
            'required' => [
              'href',
            ],
            'properties' => [
              'href' => [
                'description' => 'A string containing the link\'s URL . ',
                'type' => 'string',
                'format' => 'uri - reference',
              ],
              'meta' => [
                'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
                'type' => 'object',
                'additionalProperties' => TRUE,
              ],
            ],
          ],
        ],
        'jsonapi' => [
          'description' => 'An object describing the server\'s implementation',
          'type' => 'object',
          'properties' => [
            'version' => [
              'type' => 'string',
            ],
            'meta' => [
              'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
              'type' => 'object',
              'additionalProperties' => TRUE,
            ],
          ],
          'additionalProperties' => FALSE,
        ],
      ],
      'additionalProperties' => TRUE,
    ];

    // Stash schema request parameters.
    $context['entityTypeId'] = $entity->getEntityTypeId();
    $context['bundleId'] = $entity->getBundleId();
    $context['resourceType'] = $resource_type;

    // Retrieve 'properties' and possibly 'required' nested arrays.
    $schema_overrides = [
      'properties' => [
        'data' => $this->normalizeJsonapiProperties(
          $this->getProperties($entity, $format, $context),
          $format,
          $context
        ),
      ],
    ];
    return NestedArray::mergeDeep($normalized, $entity->getMetadata(), $schema_overrides);
  }

  /**
   * Identify properties of the data definition to normalize.
   *
   * This allow subclasses of the normalizer to build white or blacklisting
   * functionality on what will be included in the serialized schema. The JSON
   * Schema serializer already has logic to drop any properties that are empty
   * values after processing, but this allows cleaner, centralized logic.
   *
   * @param \Drupal\schemata\Schema\SchemaInterface $entity
   *   The Schema object whose properties the serializer will present.
   * @param string $format
   *   The serializer format. Defaults to NULL.
   * @param array $context
   *   The current serializer context.
   *
   * @return \Drupal\Core\TypedData\DataDefinitionInterface[]
   *   The DataDefinitions to be processed.
   */
  protected static function getProperties(SchemaInterface $entity, $format = NULL, array $context = []) {
    return $entity->getProperties();
  }

  /**
   * Normalize an array of data definitions.
   *
   * This normalization process gets an array of properties and an array of
   * properties that are required by name. This is needed by the
   * SchemataSchemaNormalizer, otherwise it would have been placed in
   * DataDefinitionNormalizer.
   *
   * @param \Drupal\Core\TypedData\DataDefinitionInterface[] $items
   *   An array of data definition properties to be normalized.
   * @param string $format
   *   Format identifier of the current serialization process.
   * @param array $context
   *   Operating context of the serializer.
   *
   * @return array
   *   Array containing one or two nested arrays.
   *   - properties: The array of all normalized properties.
   *   - required: The array of required properties by name.
   */
  protected function normalizeJsonapiProperties(array $items, $format, array $context = []) {
    $normalized = [];
    $resource_type = $context['resourceType'];
    assert($resource_type instanceof ResourceType);
    assert($this->serializer instanceof NormalizerInterface);
    foreach ($items as $name => $property) {
      if (!$resource_type->isFieldEnabled($name)) {
        continue;
      }
      $context['name'] = $resource_type->getPublicName($name);
      $item = $this->serializer->normalize($property, $format, $context);
      if (!empty($item)) {
        $normalized = NestedArray::mergeDeep($normalized, $item);
      }
    }

    return $normalized;
  }

}
