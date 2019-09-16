<?php

namespace Drupal\openapi\Plugin\openapi\OpenApiGenerator;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Authentication\AuthenticationCollectorInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\Entity\ConfigEntityTypeInterface;
use Drupal\Core\Entity\ContentEntityTypeInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Link;
use Drupal\Core\ParamConverter\ParamConverterManagerInterface;
use Drupal\Core\Routing\RouteProviderInterface;
use Drupal\Core\Url;
use Drupal\jsonapi\ResourceType\ResourceType;
use Drupal\jsonapi\ResourceType\ResourceTypeRepository;
use Drupal\jsonapi\Routing\Routes;
use Drupal\jsonapi\Routing\Routes as JsonApiRoutes;
use Drupal\openapi\Plugin\openapi\OpenApiGeneratorBase;
use Drupal\schemata\SchemaFactory;
use Symfony\Cmf\Component\Routing\RouteObjectInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Route;
use Symfony\Component\Serializer\SerializerInterface;

/**
 * Defines an OpenApi Schema Generator for the JsonApi module.
 *
 * @OpenApiGenerator(
 *   id = "jsonapi",
 *   label = @Translation("JsonApi"),
 * )
 */
class JsonApiGenerator extends OpenApiGeneratorBase {

  const JSON_API_UUID_CONVERTER = 'paramconverter.jsonapi.entity_uuid';

  /**
   * Separator for using in definition id strings.
   *
   * Override the default one to use '--' and match jsonapi.
   *
   * @var string
   */
  static $DEFINITION_SEPARATOR = '--';

  /**
   * List of parameters hat should be filtered out on JSON API Routes.
   *
   * @var string[]
   */
  static $PARAMETERS_FILTER_LIST = [
    JsonApiRoutes::RESOURCE_TYPE_KEY,
  ];

  /**
   * Module Handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  private $moduleHandler;

  /**
   * Parameter Converter Manager.
   *
   * @var \Drupal\Core\ParamConverter\ParamConverterManagerInterface
   */
  private $paramConverterManager;

  /**
   * JsonApiGenerator constructor.
   *
   * @param array $configuration
   *   Plugin configuration.
   * @param string $plugin_id
   *   Unique plugin id.
   * @param array|mixed $plugin_definition
   *   Plugin instance definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteProviderInterface $routing_provider
   *   The routing provider.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $field_manager
   *   The field manager.
   * @param \Drupal\schemata\SchemaFactory $schema_factory
   *   The schema factory.
   * @param \Symfony\Component\Serializer\SerializerInterface $serializer
   *   The serializer.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request stack.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The configuration object factory.
   * @param \Drupal\Core\Authentication\AuthenticationCollectorInterface $authentication_collector
   *   The authentication collector.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   * @param \Drupal\Core\ParamConverter\ParamConverterManagerInterface $param_converter_manager
   *   The parameter converter manager service.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   The resource type manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager, RouteProviderInterface $routing_provider, EntityFieldManagerInterface $field_manager, SchemaFactory $schema_factory, SerializerInterface $serializer, RequestStack $request_stack, ConfigFactoryInterface $config_factory, AuthenticationCollectorInterface $authentication_collector, ModuleHandlerInterface $module_handler, ParamConverterManagerInterface $param_converter_manager, ResourceTypeRepository $resource_type_repository) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $entity_type_manager, $routing_provider, $field_manager, $schema_factory, $serializer, $request_stack, $config_factory, $authentication_collector);
    $this->moduleHandler = $module_handler;
    $this->paramConverterManager = $param_converter_manager;

    // Remove the disabled resource types from the output.
    $this->options['exclude'] = static::findDisabledMethods(
      $entity_type_manager,
      $resource_type_repository
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('router.route_provider'),
      $container->get('entity_field.manager'),
      $container->get('schemata.schema_factory'),
      $container->get('serializer'),
      $container->get('request_stack'),
      $container->get('config.factory'),
      $container->get('authentication_collector'),
      $container->get('module_handler'),
      $container->get('paramconverter_manager'),
      $container->get('jsonapi.resource_type.repository')
    );
  }

  /**
   * Introspects all the JSON API resource types and outputs the disabled ones.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\jsonapi\ResourceType\ResourceTypeRepository $resource_type_repository
   *   The resource type manager.
   *
   * @return string[]
   *   A list of resource keys to disable.
   */
  protected static function findDisabledMethods(
    EntityTypeManagerInterface $entity_type_manager,
    ResourceTypeRepository $resource_type_repository
  ) {
    $extract_resource_type_id = function (ResourceType $resource_type) use ($entity_type_manager) {
      $entity_type = $entity_type_manager->getDefinition($resource_type->getEntityTypeId());
      if (empty($entity_type->getKey('bundle'))) {
        return $resource_type->getEntityTypeId();
      }
      return sprintf(
        '%s%s%s',
        $resource_type->getEntityTypeId(),
        static::$DEFINITION_SEPARATOR,
        $resource_type->getBundle()
      );
    };
    $filter_disabled = function (ResourceType $resourceType) {
      // If there is an isInternal method and the resource is marked as internal
      // then consider it disabled. If not, then it's enabled.
      return method_exists($resourceType, 'isInternal') && $resourceType->isInternal();
    };
    $all = $resource_type_repository->all();
    $disabled_resources = array_filter($all, $filter_disabled);
    $disabled = array_map($extract_resource_type_id, $disabled_resources);
    return $disabled;
  }

  /**
   * {@inheritdoc}
   */
  public function getBasePath() {
    return parent::getBasePath() . $this->getJsonApiBase();
  }

  /**
   * Determine the base for JsonApi's endpoint routes.
   *
   * @return string
   *   The url prefix used for all jsonapi resource endpoints.
   */
  public function getJsonApiBase() {
    return \Drupal::getContainer()->getParameter('jsonapi.base_path');
  }

  /**
   * {@inheritdoc}
   */
  public function getPaths() {
    $routes = $this->getJsonApiRoutes();
    $api_paths = [];
    foreach ($routes as $route_name => $route) {
      /** @var \Drupal\jsonapi\ResourceType\ResourceType $resource_type */
      $resource_type = $this->getResourceType($route_name, $route);
      $entity_type_id = $resource_type->getEntityTypeId();
      $bundle_name = $resource_type->getBundle();
      if (!$this->includeEntityTypeBundle($entity_type_id, $bundle_name)) {
        continue;
      }
      $api_path = [];
      $methods = $route->getMethods();
      foreach ($methods as $method) {
        $method = strtolower($method);
        $path_method = [
          'summary' => $this->getRouteMethodSummary($route, $route_name, $method),
          'description' => $this->getRouteMethodDescription($route, $route_name, $method, $resource_type->getTypeName()),
          'parameters' => $this->getMethodParameters($route, $route_name, $resource_type, $method),
          'tags' => [$this->getBundleTag($entity_type_id, $bundle_name)],
          'responses' => $this->getEntityResponsesJsonApi($entity_type_id, $method, $bundle_name, $route_name, $route),
        ];
        /*
         * @TODO: #2977109 - Calculate oauth scopes required.
         *
         * if (array_key_exists('oauth2', $path_method['security'])) {
         *   ...
         * }
         */

        $api_path[$method] = $path_method;
      }
      // Each path contains the "base path" from a OpenAPI perspective.
      $path = str_replace($this->getJsonApiBase(), '', $route->getPath());
      $api_paths[$path] = NestedArray::mergeDeep(empty($api_paths[$path]) ? [] : $api_paths[$path], $api_path);
    }
    return $api_paths;
  }

  /**
   * Gets the JSON API routes.
   *
   * @return \Symfony\Component\Routing\Route[]
   *   The routes.
   */
  protected function getJsonApiRoutes() {
    $all_routes = $this->routingProvider->getAllRoutes();
    $jsonapi_routes = [];
    $jsonapi_base_path = $this->getJsonApiBase();
    /** @var \Symfony\Component\Routing\Route $route */
    foreach ($all_routes as $route_name => $route) {
      $is_jsonapi = $route->getDefault(JsonApiRoutes::JSON_API_ROUTE_FLAG_KEY);
      $is_entry_point = $route->getPath() === $jsonapi_base_path;
      if (!$is_jsonapi || $is_entry_point) {
        continue;
      }
      $jsonapi_routes[$route_name] = $route;
    }
    return $jsonapi_routes;
  }

  /**
   * Gets description of a method on a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param string $route_name
   *   The route name.
   * @param string $method
   *   The method.
   *
   * @return string
   *   The method summary.
   */
  protected function getRouteMethodSummary(Route $route, $route_name, $method) {
    $resource_type = $this->getResourceType($route_name, $route);
    $entity_type_id = $resource_type->getEntityTypeId();
    $bundle = $resource_type->getBundle();
    $tag = $this->getBundleTag($entity_type_id, $bundle);
    $route_type = $this->getRoutTypeFromName($route_name);
    if (in_array($route_type, ['related', 'relationship'])) {
      $target_resource_type = $this->relatedResourceType($route_name, $route);
      $target_tag = $this->getBundleTag(
        $target_resource_type->getEntityTypeId(),
        $target_resource_type->getBundle()
      );
      return $this->t('@route_type: @fieldName (@targetType)', [
        '@route_type' => ucfirst($route_type),
        '@fieldName' => explode('.', $route_name)[2],
        '@targetType' => $target_tag,
        '@tag' => $tag,
      ]);
    }
    if ($route_type === 'collection') {
      if ($method === 'get') {
        return $this->t('List (@tag)', ['@tag' => $tag]);
      }
      if ($method === 'post') {
        return $this->t('Create (@tag)', ['@tag' => $tag]);
      }
    }
    if ($route_type === 'individual') {
      if ($method === 'get') {
        return $this->t('View (@tag)', ['@tag' => $tag]);
      }
      if ($method === 'patch') {
        return $this->t('Update (@tag)', ['@tag' => $tag]);
      }
      if ($method === 'delete') {
        return $this->t('Remove (@tag)', ['@tag' => $tag]);
      }
    }
    return $this->t('@route_type @method', [
      '@route_type' => ucfirst($route_type),
      '@method' => strtoupper($method),
    ]);
  }

  /**
   * Gets description of a method on a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param string $route_name
   *   The route name.
   * @param string $method
   *   The method.
   * @param string $resource_type_name
   *   The resource type name
   *
   * @return string
   *   The method description.
   */
  protected function getRouteMethodDescription($route, $route_name, $method, $resource_type_name) {
    $route_type = $this->getRoutTypeFromName($route_name);
    if (!$route_type || $method !== 'get') {
      return NULL;
    }
    if ($route_type === 'collection') {
      $message = '%link_co for the @name resource type. Collections are a list';
      $message .= ' of %link_ro for a particular resource type. In the JSON ';
      $message .= 'API module for Drupal all collections are homogeneous, ';
      $message .= 'which means that all the items in a collection are of the ';
      $message .= 'same type.';
      return $this->t($message, [
        '%link_co' => Link::fromTextAndUrl(
          $this->t('Collection endpoint'),
          Url::fromUri('http://jsonapi.org/format/#fetching')
        )->toString(),
        '@name' => $resource_type_name,
        '%link_ro' => Link::fromTextAndUrl(
          $this->t('resource objects'),
          Url::fromUri('http://jsonapi.org/format/#document-resource-objects')
        )->toString(),
      ]);
    }
    elseif ($route_type === 'individual') {
      $message = '%link_in for the @name resource type. The individual ';
      $message .= 'endpoint contains a %link_ro with the data for a particular';
      $message .= ' resource or entity.';
      return $this->t($message, [
        '%link_in' => Link::fromTextAndUrl(
          $this->t('Individual endpoint'),
          Url::fromUri('http://jsonapi.org/format/#fetching')
        )->toString(),
        '@name' => $resource_type_name,
        '%link_ro' => Link::fromTextAndUrl(
          $this->t('resource object'),
          Url::fromUri('http://jsonapi.org/format/#document-resource-objects')
        )->toString(),
      ]);
    }
    elseif ($route_type === 'related') {
      $message = '%link_related for the @target_name resource type through the';
      $message .= ' %field_name relationship. The related endpoint contains a ';
      $message .= '%link_ro with the data for a particular related resource or';
      $message .= ' entity.';
      $target_resource_type = $this->relatedResourceType($route_name, $route);
      return $this->t($message, [
        '%link_related' => Link::fromTextAndUrl(
          $this->t('Related endpoint'),
          Url::fromUri('http://jsonapi.org/format/#fetching')
        )->toString(),
        '@target_name' => $target_resource_type->getTypeName(),
        '%field_name' => explode('.', $route_name)[2],
        '%link_ro' => Link::fromTextAndUrl(
          $this->t('resource object'),
          Url::fromUri('http://jsonapi.org/format/#document-resource-objects')
        )->toString(),
      ]);
    }
    elseif ($route_type === 'relationship') {
      $message = '%link_rel for the @target_name resource type through the';
      $message .= ' %field_name relationship. The relationship endpoint ';
      $message .= 'contains a %link_ri with the data for a particular ';
      $message .= 'relationship.';
      $target_resource_type = $this->relatedResourceType($route_name, $route);
      return $this->t($message, [
        '%link_rel' => Link::fromTextAndUrl(
          $this->t('Relationship endpoint'),
          Url::fromUri('https://jsonapi.org/format/#fetching-relationships')
        )->toString(),
        '@target_name' => $target_resource_type->getTypeName(),
        '%field_name' => explode('.', $route_name)[2],
        '%link_ri' => Link::fromTextAndUrl(
          $this->t('resource identifier object'),
          Url::fromUri('https://jsonapi.org/format/#document-resource-identifier-objects')
        )->toString(),
      ]);
    }
    return NULL;
  }

  /**
   * Gets the route from the name if possible.
   *
   * @param string $route_name
   *   The route name.
   *
   * @return string
   *   The route type.
   */
  protected function getRoutTypeFromName($route_name) {
    if (strpos($route_name, '.related') !== FALSE) {
      return 'related';
    }
    if (strpos($route_name, '.relationship') !== FALSE) {
      return 'relationship';
    }
    $route_name_parts = explode('.', $route_name);
    return isset($route_name_parts[2]) ? $route_name_parts[2] : '';
  }

  /**
   * Gets the related Resource Type.
   *
   * @param string $route_name
   *   The JSON API route name for which the ResourceType is wanted.
   * @param \Symfony\Component\Routing\Route $route
   *   The JSON API route for which the ResourceType is wanted.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType|null
   *   Returns the ResourceType for the related JSON API resource.
   */
  protected function relatedResourceType($route_name, $route) {
    if (!in_array(
      $this->getRoutTypeFromName($route_name),
      ['related', 'relationship'])
    ) {
      return NULL;
    }
    $resource_type = $this->getResourceType($route_name, $route);
    $field_name = explode('.', $route_name)[2];
    $target_resource_type = current($resource_type->getRelatableResourceTypesByField($field_name));
    assert(is_a($target_resource_type, ResourceType::class));
    return $target_resource_type;
  }

  /**
   * Get the parameters array for a method on a route.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route.
   * @param string $route_name
   *   The route name.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The JSON API resource type.
   * @param string $method
   *   The HTTP method.
   *
   * @return array
   *   The parameters.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function getMethodParameters(Route $route, $route_name, ResourceType $resource_type, $method) {
    $parameters = [];
    if ($method === 'get' && $resource_type->isVersionable()) {
      $parameters[] = [
        'name' => 'resourceVersion',
        'in' => 'query',
        'type' => 'string',
        'required' => FALSE,
        'description' => $this->t(
          'The JSON:API module exposes entity revisions as resource versions. @link.',
          [
            '@link' => Link::fromTextAndUrl(
              'Learn more in the documentation',
              Url::fromUri('https://www.drupal.org/docs/8/modules/jsonapi/revisions')
            )->toString(),
          ]
        ),
      ];
    }
    $entity_type_id = $resource_type->getEntityTypeId();
    $bundle_name = $resource_type->getBundle();
    $option_parameters = $route->getOption('parameters');
    if (!empty($option_parameters) && $filtered_parameters = $this->filterParameters($option_parameters)) {
      foreach ($filtered_parameters as $parameter_name => $parameter_info) {
        $parameter = [
          'name' => $parameter_name,
          'required' => TRUE,
          'in' => 'path',
        ];
        if ($parameter_info['converter'] === static::JSON_API_UUID_CONVERTER) {
          $parameter['type'] = 'uuid';
          $parameter['description'] = $this->t('The uuid of the @entity @bundle',
            [
              '@entity' => $entity_type_id,
              '@bundle' => $bundle_name,
            ]
          );
        }
        $parameters[] = $parameter;
      }

      if ($this->jsonApiPathHasRelated($route->getPath())) {
        $parameters[] = [
          'name' => 'related',
          'required' => TRUE,
          'in' => 'path',
          'type' => 'string',
          'description' => $this->t('The relationship field name'),
        ];
      }
    }
    $route_type = $this->getRoutTypeFromName($route_name);
    if ($method == 'get' && $route_type === 'collection' && $resource_type->isLocatable()) {
      // If no route parameters and GET then this is collection route.
      // @todo Add descriptions or link to documentation.
      $parameters[] = [
        'name' => 'filter',
        'in' => 'query',
        'type' => 'array',
        'required' => FALSE,
        'description' => $this->t('The JSON:API module has some of the most robust and feature-rich filtering features around. All of that power comes with a bit of a learning curve though. @link.', [
            '@link' => Link::fromTextAndUrl(
              'Learn more in the documentation',
              Url::fromUri('https://www.drupal.org/docs/8/modules/jsonapi/filtering')
            )->toString(),
          ]
        ),
      ];
      $parameters[] = [
        'name' => 'sort',
        'in' => 'query',
        'type' => 'array',
        'required' => FALSE,
        'description' => $this->t('The JSON:API module allows you to sort collections based on properties in the resource or in nested resources. @link.', [
            '@link' => Link::fromTextAndUrl(
              'Learn more in the documentation',
              Url::fromUri('https://www.drupal.org/docs/8/modules/jsonapi/sorting')
            )->toString(),
          ]
        ),
      ];
      $parameters[] = [
        'name' => 'page',
        'in' => 'query',
        'type' => 'array',
        'required' => FALSE,
        'description' => $this->t('Pagination can be a deceptively complex topic. It\'s easy to fall into traps and not follow best-practices. @link.', [
            '@link' => Link::fromTextAndUrl(
              'Learn more in the documentation',
              Url::fromUri('https://www.drupal.org/docs/8/modules/jsonapi/pagination')
            )->toString(),
          ]
        ),
      ];
      $parameters[] = [
        'name' => 'include',
        'in' => 'query',
        'type' => 'string',
        'required' => FALSE,
        'description' => $this->t('Embed related entities in the response. For example: use a query string like <code>?include=comments.author</code> to include all the entities referenced by <code>comments</code> and all the entities referenced by <code>author</code> on those entities!. @link.', [
            '@link' => Link::fromTextAndUrl(
              'Learn more in the documentation',
              Url::fromUri('https://www.drupal.org/docs/8/modules/jsonapi/includes')
            )->toString(),
          ]
        ),
      ];
    }
    elseif ($method == 'post' || $method == 'patch') {
      // We need a parameter for the body.
      $body_entity_type_id = $entity_type_id;
      $body_bundle_name = $bundle_name;
      if (in_array($route_type, ['related', 'relationship'])) {
        $target_resource_type = $this->relatedResourceType($route_name, $route);
        $body_entity_type_id = $target_resource_type->getEntityTypeId();
        $body_bundle_name = $target_resource_type->getBundle();
      }
      // Determine if it is mutable.
      if ($resource_type->isMutable()) {
        if ($route_type === 'relationship') {
          $is_multiple = $this->isToManyRelationship($route_name, $resource_type);
          // Relationships are completely different.
          $parameters[] = [
            'name' => 'body',
            'in' => 'body',
            'description' => $this->t('The resource identifier object'),
            'required' => TRUE,
            'schema' => static::buildRelationshipSchema($is_multiple, $target_resource_type->getTypeName()),
          ];
        }
        else {
          $parameters[] = [
            'name' => 'body',
            'in' => 'body',
            'description' => $this->t('The %label object', ['%label' => $body_entity_type_id]),
            'required' => TRUE,
            'schema' => [
              '$ref' => $this->getDefinitionReference($body_entity_type_id, $body_bundle_name),
            ],
          ];
        }
      }
    }
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityResponsesJsonApi($entity_type_id, $method, $bundle_name, $route_name, Route $route = NULL) {
    $route_type = $this->getRoutTypeFromName($route_name);
    if ($route_type === 'collection') {
      if ($method === 'get') {
        $schema_response = [];
        if ($definition_ref = $this->getDefinitionReference($entity_type_id, $bundle_name)) {
          $definition_key = $this->getEntityDefinitionKey($entity_type_id, $bundle_name);
          $definition = $this->getDefinitions()[$definition_key];
          $ref = NestedArray::getValue($definition, ['definitions', 'data'])
            ? "$definition_ref/definitions/data"
            : "$definition_ref/properties/data";
          $schema = $definition;
          $schema['properties']['data'] = [
            'type' => 'array',
            'items' => ['$ref' => $ref],
          ];
          $schema_response = ['schema' => $schema];
        }
        $responses['200'] = [
            'description' => 'successful operation',
          ] + $schema_response;
        return $responses;
      }

    }
    elseif (in_array($route_type, ['relationship', 'related'])) {
      $resource_type = $this->getResourceType($route_name, $route);
      $target_resource_type = $this->relatedResourceType($route_name, $route);
      $is_multiple = $this->isToManyRelationship($route_name, $resource_type);
      if ($route_type === 'relationship') {
        $schema = static::buildRelationshipSchema($is_multiple, $target_resource_type->getTypeName());
        if ($method === 'get') {
          return [
            200 => [
              'description' => 'successful operation',
              'schema' => $schema
            ],
          ];
        }
        elseif ($method === 'post') {
          return [201 => ['description' => 'created', 'schema' => $schema]];
        }
        elseif ($method === 'patch') {
          return [
            200 => [
              'description' => 'successful operation',
              'schema' => $schema,
            ],
          ];
        }
        elseif ($method === 'delete') {
          return [204 => ['description' => 'no content']];
        }
      }
      else {
        // Fake a route name that will yield the expected results for the related
        // responses.
        $target_route_name = Routes::getRouteName($target_resource_type, $is_multiple ? 'collection' : 'individual');
        return $this->getEntityResponsesJsonApi(
          $target_resource_type->getEntityTypeId(),
          $method,
          $target_resource_type->getBundle(),
          $target_route_name
        );
      }
    }
    else {
      return parent::getEntityResponses($entity_type_id, $method, $bundle_name);
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getDefinitions() {
    static $definitions = [];
    if (!$definitions) {
      foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
        if ($entity_type instanceof ContentEntityTypeInterface) {
          if ($bundle_type = $entity_type->getBundleEntityType()) {
            $bundle_storage = $this->entityTypeManager->getStorage($bundle_type);
            $bundles = $bundle_storage->loadMultiple();
            foreach ($bundles as $bundle_name => $bundle) {
              if ($this->includeEntityTypeBundle($entity_type->id(), $bundle_name)) {
                $definition_key = $this->getEntityDefinitionKey($entity_type->id(), $bundle_name);
                $json_schema = $this->getJsonSchema('api_json', $entity_type->id(), $bundle_name);
                $json_schema = $this->fixReferences($json_schema, '#/definitions/' . $definition_key);
                $definitions[$definition_key] = $json_schema;
              }
            }
          }
          else {
            if ($this->includeEntityTypeBundle($entity_type->id())) {
              $definition_key = $this->getEntityDefinitionKey($entity_type->id());
              $json_schema = $this->getJsonSchema('api_json', $entity_type->id());
              $json_schema = $this->fixReferences($json_schema, '#/definitions/' . $definition_key);
              $definitions[$definition_key] = $json_schema;
            }
          }
        }
      }
    }
    return $definitions;
  }

  /**
   * When embedding JSON Schemas you need to make sure to fix any possible $ref
   *
   * @param array $schema
   *   The schema to fix.
   * @param $prefix
   *   The prefix where this schema is embedded.
   *
   * @return array
   */
  private function fixReferences(array $schema, $prefix) {
    foreach ($schema as $name => $item) {
      if (is_array($item)) {
        $schema[$name] = $this->fixReferences($item, $prefix);
      }
      if ($name === '$ref' && is_string($item) && strpos($item, '#/') !== FALSE) {
        $schema[$name] = preg_replace('/#\//', $prefix . '/', $item);
      }
    }
    return $schema;
  }

  /**
   * {@inheritdoc}
   */
  public function getTags() {
    $tags = [];
    foreach ($this->entityTypeManager->getDefinitions() as $entity_type) {
      if ($bundle_type_id = $entity_type->getBundleEntityType()) {
        $bundle_storage = $this->entityTypeManager->getStorage($bundle_type_id);
        $bundles = $bundle_storage->loadMultiple();
        foreach ($bundles as $bundle_name => $bundle) {
          if (!$this->includeEntityTypeBundle($entity_type->id(), $bundle_name)) {
            continue;
          }
          $description = $this->t("@bundle_label @bundle of type @entity_type.",
            [
              '@bundle_label' => $entity_type->getBundleLabel(),
              '@bundle' => $bundle->label(),
              '@entity_type' => $entity_type->getLabel(),
            ]
          );
          $tag = [
            'name' => $this->getBundleTag($entity_type->id(), $bundle->id()),
            'description' => $description,
            'x-entity-type' => $entity_type->id(),
            'x-definition' => [
              '$ref' => $this->getDefinitionReference($entity_type->id(), $bundle_name),
            ],
          ];
          if (method_exists($bundle, 'getDescription')) {
            $tag['description'] .= ' ' . $bundle->getDescription();
          }
          $tags[] = $tag;
        }
      }
      else {
        if (!$this->includeEntityTypeBundle($entity_type->id())) {
          continue;
        }
        $tag = [
          'name' => $this->getBundleTag($entity_type->id()),
        ];
        if ($entity_type instanceof ConfigEntityTypeInterface) {
          $tag['description'] = $this->t('Configuration entity @entity_type', ['@entity_type' => $entity_type->getLabel()]);
        }
        $tags[] = $tag;
      }
    }
    return $tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getConsumes() {
    return [
      'application/vnd.api+json',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getProduces() {
    return [
      'application/vnd.api+json',
    ];
  }

  /**
   * Get the tag to use for a bundle.
   *
   * @param string $entity_type_id
   *   The entity type.
   * @param string $bundle_name
   *   The entity type.
   *
   * @return string
   *   The bundle tag.
   */
  protected function getBundleTag($entity_type_id, $bundle_name = NULL) {
    static $tags = [];
    if (!isset($tags[$entity_type_id][$bundle_name])) {
      $entity_type = $this->entityTypeManager->getDefinition($entity_type_id);
      $tag = $entity_type->getLabel();
      if ($bundle_name && $bundle_type_id = $entity_type->getBundleEntityType()) {
        $bundle_entity = $this->entityTypeManager->getStorage($bundle_type_id)
          ->load($bundle_name);
        $tag .= ' - ' . $bundle_entity->label();
      }
      $tags[$entity_type_id][$bundle_name] = $tag;
    }
    return $tags[$entity_type_id][$bundle_name];
  }

  /**
   * {@inheritdoc}
   */
  public function getApiName() {
    return $this->t('JSON API');
  }

  /**
   * {@inheritdoc}
   */
  protected function getEntityDefinitionKey($entity_type_id, $bundle_name = NULL) {
    // Override the default definition key structure to use 'type--bundle'.
    if (!$bundle_name) {
      $bundle_name = $entity_type_id;
    }
    return parent::getEntityDefinitionKey($entity_type_id, $bundle_name);
  }

  /**
   * {@inheritdoc}
   */
  protected function getApiDescription() {
    return $this->t('This is a JSON API compliant implementation');
  }

  /**
   * Gets a Resource Type.
   *
   * @param string $route_name
   *   The JSON API route name for which the ResourceType is wanted.
   * @param \Symfony\Component\Routing\Route $route
   *   The JSON API route for which the ResourceType is wanted.
   *
   * @return \Drupal\jsonapi\ResourceType\ResourceType
   *   Returns the ResourceType for the given JSON API route.
   */
  protected function getResourceType($route_name, Route $route) {
    $parameters[RouteObjectInterface::ROUTE_NAME] = $route_name;
    $parameters[RouteObjectInterface::ROUTE_OBJECT] = $route;
    $upcasted_parameters = $this->paramConverterManager->convert($parameters + $route->getDefaults());
    return $upcasted_parameters[JsonApiRoutes::RESOURCE_TYPE_KEY];
  }

  /**
   * Filters an associative array by key on a set of parameter.
   *
   * @param array $parameters
   *   Associative array that is going to be filtered.
   *
   * @return array
   *   Returns the filtered associative array.
   */
  protected function filterParameters(array $parameters) {
    foreach (static::$PARAMETERS_FILTER_LIST as $filter) {
      if (array_key_exists($filter, $parameters)) {
        unset($parameters[$filter]);
      }
    }
    return $parameters;
  }

  /**
   * Checks if a JSON API Path has {related}.
   *
   * @todo remove once https://www.drupal.org/project/jsonapi/issues/2953346
   * is done on JSON API Project.
   *
   * @param string $path
   *   The path.
   *
   * @return bool
   *   TRUE if path contains {related}, FALSE otherwise
   */
  protected function jsonApiPathHasRelated($path) {
    return strpos($path, '{related}') !== FALSE;
  }

  /**
   * Builds the relationship schema.
   *
   * @param bool $is_multiple
   *   Indicates if the relationship is to-many.
   * @param string $resource_type_name
   *   The resource type for the relationship.
   *
   * @return array
   *   The schema definition.
   *
   * @todo: build this once and use '$ref' when necessary.
   */
  protected static function buildRelationshipSchema($is_multiple, $resource_type_name = NULL) {
    $linkage_schema = [
      'description' => 'The "type" and "id" to non-empty members.',
      'type' => 'object',
      'required' => ['type', 'id',],
      'properties' => [
        'type' => ['title' => t('Resource type name'), 'type' => 'string'],
        'id' => ['title' => t('Resource ID'), 'type' => 'string', 'format' => 'uuid'],
        'meta' => [
          'description' => 'Non-standard meta-information that can not be represented as an attribute or relationship.',
          'type' => 'object',
          'additionalProperties' => TRUE,
          'properties' => (object) [],
        ],
      ],
      'additionalProperties' => FALSE,
    ];
    if ($resource_type_name) {
      $linkage_schema['properties']['type']['enum'] = [$resource_type_name];
    }
    return [
      'type' => 'object',
      'properties' => [
        'data' => $is_multiple
          ? [
            'description' => 'An array of objects each containing \"type\" and \"id\" members for to-many relationships.',
            'type' => 'array',
            'items' => $linkage_schema,
            'uniqueItems' => TRUE,
          ]
          : $linkage_schema
      ],
    ];
  }

  /**
   * Checks if the relationship is to-many.
   *
   * @param string $route_name
   *   The route name.
   * @param \Drupal\jsonapi\ResourceType\ResourceType $resource_type
   *   The resource type.
   *
   * @return bool
   *   Indicates if the relationship is multiple cardinality.
   */
  protected function isToManyRelationship($route_name, ResourceType $resource_type) {
    $public_field_name = explode('.', $route_name)[2];
    $internal_field_name = $resource_type->getInternalName($public_field_name);
    $field_definitions = $this->fieldManager
      ->getFieldDefinitions($resource_type->getEntityTypeId(), $resource_type->getBundle());
    return $field_definitions[$internal_field_name]->getFieldStorageDefinition()->getCardinality() !== 1;
  }

}
