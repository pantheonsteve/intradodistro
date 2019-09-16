<?php

namespace Drupal\layout_builder_restrictions\EventSubscriber;

use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Render\Element;
use Drupal\Core\Routing\CurrentRouteMatch;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\GetResponseForControllerResultEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Layout builder restrictions event subscriber.
 */
class LayoutBuilderRestrictionsSubscriber implements EventSubscriberInterface {

  /**
   * Route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * Module handler service.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * LayoutBuilderRestrictionsSubscriber constructor.
   */
  public function __construct(CurrentRouteMatch $route_match, ModuleHandlerInterface $handler) {
    $this->routeMatch = $route_match;
    $this->moduleHandler = $handler;
  }

  /**
   * Subscriber for kernel view.
   */
  public function onKernelView(GetResponseForControllerResultEvent $event) {
    $route_name = $this->routeMatch->getCurrentRouteMatch()->getRouteName();
    $result = $event->getControllerResult();
    switch ($route_name) {
      case 'layout_builder.choose_section':
        $this->alterLayoutChooser($result);
        break;

      case 'layout_builder.choose_block':
        $this->alterBlockChooser($result);
        break;

      default:
        return;
    }
    $event->setControllerResult($result);
  }

  /**
   * Alters the layouts available in Layout Builder's choose_section.
   */
  protected function alterLayoutChooser(&$result) {
    if (empty($result['layouts']['#items'])) {
      return;
    }
    if (interface_exists('Drupal\Core\Plugin\FilteredPluginManagerInterface')) {
      // This will now be altered through the appropriate new hooks.
      return;
    }
    $keys = $this->moduleHandler->invokeAll('layout_builder_restrictions_allowed_layouts');
    $this->moduleHandler->alter('layout_builder_restrictions_allowed_layouts', $keys);
    if (!empty($keys)) {
      foreach ($result['layouts']['#items'] as $delta => $item) {
        /** @var \Drupal\Core\Url $url */
        $url = $item['#url'];
        $params = $url->getRouteParameters();
        if (!in_array($params['plugin_id'], $keys)) {
          unset($result['layouts']['#items'][$delta]);
        }
      }
    }
  }

  /**
   * Alters the block providers available in Layout Builder's choose_block.
   *
   * @param array $result
   *   Controller result.
   */
  protected function alterBlockChooser(array &$result) {
    if (interface_exists('Drupal\Core\Plugin\FilteredPluginManagerInterface')) {
      // This will now be altered through the appropriate new hooks. We still
      // fire the chooser result hook, since this is still a place where one
      // could put other things like extra css or extra content.
      $this->moduleHandler->alter('layout_builder_restrictions_chooser_result', $result);
      return;
    }
    $keys = $this->moduleHandler->invokeAll('layout_builder_restrictions_allowed_block_keys');
    if (!empty($keys)) {
      $this->moduleHandler->alter('layout_builder_restrictions_allowed_block_keys', $keys);
      foreach (Element::children($result) as $delta) {
        if (!in_array($delta, $keys)) {
          $result[$delta]['#access'] = FALSE;
        }
      }
    }
    $this->moduleHandler->alter('layout_builder_restrictions_chooser_result', $result);
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    return [
      // Make sure we are before the main content view.
      KernelEvents::VIEW => ['onKernelView', 1],
    ];
  }

}
