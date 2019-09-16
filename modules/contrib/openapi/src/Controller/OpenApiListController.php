<?php

namespace Drupal\openapi\Controller;

use Drupal\Component\Plugin\PluginManagerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Lists OpenAPI links.
 */
class OpenApiListController extends ControllerBase {

  /**
   * Current Generator plugin manager.
   *
   * @var \Drupal\Component\Plugin\PluginManagerInterface
   */
  public $openapiGeneratorManager;

  /**
   * Creates a new OpenApiListController.
   *
   * @param \Drupal\Component\Plugin\PluginManagerInterface $openapi_generator_manager
   *   The current openapi generator plugin manager instance.
   * @param \Drupal\Component\Plugin\PluginManagerInterface $openapi_ui_manager
   *   ui library plugin manager instance. NULL if the module is not enabled.
   */
  public function __construct(PluginManagerInterface $openapi_generator_manager, PluginManagerInterface $openapi_ui_manager) {
    $this->openapiGeneratorManager = $openapi_generator_manager;
    $this->openapiUiManager = $openapi_ui_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $ui_manager = NULL;
    $module_handler = $container->get('module_handler');
    if ($module_handler->moduleExists('openapi_ui')) {
      $ui_manager = $container->get('plugin.manager.openapi_ui.ui');
    }
    return new static(
      $container->get('plugin.manager.openapi.generator'),
      $ui_manager
    );
  }

  /**
   * List all doc plugins and link to the ui views if they are available.
   */
  public function downloadsList() {
    $links = [
      ':openapi_spec' => 'https://github.com/OAI/OpenAPI-Specification/tree/OpenAPI.next',
      ':swagger_editor' => 'http://editor.swagger.io/',
      ':swagger_codegen' => 'https://swagger.io/tools/swagger-codegen/',
    ];
    $message = '<p>' . $this->t("The specifications provide the documentation on some of Drupal's apis following <a href=':openapi_spec'>OpenAPI (aka Swagger)</a> standards.", $links) . ' ';
    $message .= $this->t('These json files can be used in tools such as the <a href=":swagger_editor">Swagger Editor</a> to provide a more detailed version of the API documentation or <a href=":swagger_codegen">Swagger Codegen</a> to create an api client.', $links) . '</p>';
    $build['direct_download'] = [
      '#type' => 'item',
      '#markup' => $message,
    ];

    // Build a table with links to spec files and uis.
    $plugins = $this->openapiGeneratorManager->getDefinitions();
    if (count($plugins)) {
      $build['documentation'] = [
        '#type' => 'table',
        '#header' => [
          'module' => $this->t('Module'),
          'specification' => $this->t('Specification'),
        ],
      ];

      // Construct a message giving user information on docs uis.
      $openapi_ui_context = [
        ':openapi_ui_link' => 'https://drupal.org/project/openapi_ui#libraries',
      ];
      $ui_message = $this->t("Please visit the <a href=':openapi_ui_link'>OpenAPI UI module</a> for information on these interfaces and to discover others.", $openapi_ui_context) . '</p>';

      $ui_plugins = [];
      if ($this->openapiUiManager !== NULL && ($ui_plugins = $this->openapiUiManager->getDefinitions())  && count($ui_plugins)) {
        // Add a column for links to the docs uis.
        $build['documentation']['#header']['explore'] = $this->t('Explore') . '*';
        $ui_message = '<strong>*</strong> ' . $ui_message;
      }
      else {
        // If we don't have openapi_ui plugins, give the user info on them.
        $build['ui']['#title'] = $this->t('No UI plugins available');
        $no_ui_message = $this->t('There are no plugins available for exploring the OpenAPI documentation.') . ' ';
        $no_ui_message = $this->t('You can install one of the below projects to view the API Specifications from with your site.') . ' ';
        $ui_message = $no_ui_message . $ui_message;
      }

      $build['ui'] = [
        '#type' => 'item',
        '#markup' => '<p>' . $ui_message . '</p>',
      ];

      $json_format = [
        'query' => [
          '_format' => 'json'
        ]
      ];
      $open_api_links = [];
      foreach ($plugins as $generator_id => $generator) {
        $link_args = [
          'openapi_generator' => $generator_id,
        ];
        $link_context = [
          '%generator' => $generator['label'],
        ];
        $row = [
          'module' => [
            '#type' => 'item',
            '#markup' => $generator['label'],
          ],
          'specification' => [
            '#type' => 'dropbutton',
            '#links' => [
              [
                'title' => $this->t('View/Download', $link_context),
                'url' => Url::fromRoute('openapi.download', $link_args, $json_format),
              ],
            ],
          ],
        ];

        // If there are UI plugins, add them to the table.
        if (count($ui_plugins)) {
          $row['explore'] = [
            '#type' => 'dropbutton',
            '#links' => [],
          ];
          // Foreach ui, add a link to view the docs.
          foreach ($ui_plugins as $ui_plugin_id => $ui_plugin) {
            $interface_args = [
              'openapi_generator' => $generator_id,
              'openapi_ui' => $ui_plugin_id
            ];
            $ui_context = [
              '%interface' => $ui_plugin['label'],
            ];
            $row['explore']['#links'][$ui_plugin_id] = [
              'url' => Url::fromRoute('openapi.documentation', $interface_args),
              'title' => $this->t('Explore with %interface', $ui_context),
            ];
          }
        }

        // Add row to table.
        $build['documentation'][] = $row;
      }
    }
    else {
      // If there are no doc plugins, give info on getting a plugin.
      $links = [
        ':rest_link' => 'https://www.drupal.org/docs/8/core/modules/rest',
        ':jsonapi_link' => 'https://www.drupal.org/project/jsonapi',
      ];
      $no_plugins_message = '<strong>' . $this->t('No OpenApi generator plugins are currently available.') . '</strong> ';
      $no_plugins_message .= $this->t('You must enable a REST or API module which supports OpenApi Downloads, such as the <a href=":rest_link">Core Rest</a> and <a href=":jsonapi_link">Json API</a> modules.', $links);
      drupal_set_message(['#markup' => $no_plugins_message], 'warning');
    }

    return $build;
  }

}
