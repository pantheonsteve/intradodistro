<?php

namespace Drupal\cms_content_sync_developer\EventSubscriber;

use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\Core\Config\ConfigFactory;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * A subscriber triggering a config when certain configuration changes.
 */
class VersionWarning implements EventSubscriberInterface {

  /**
   * The config Factory.
   *
   * @var config_factory\Drupal\Core\Config\ConfigFactory
   */
  protected $config_factory;

  /**
   * The current user.
   *
   * @var current_user\Drupal\Core\Session\AccountProxyInterface
   */
  protected $current_user;

  /**
   * The Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * MyModuleService constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactory $config_factory
   *   The config factory.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   */
  public function __construct(ConfigFactory $config_factory, AccountProxyInterface $current_user, MessengerInterface $messenger) {
    $this->config_factory = $config_factory;
    $this->current_user = $current_user;
    $this->messenger = $messenger;
  }

  /**
   * Show version warning.
   *
   * @param \Symfony\Component\HttpKernel\Event\GetResponseEvent $event
   *   The Event to process.
   */
  public function showVersionWarning(GetResponseEvent $event) {

    $current_user = $this->current_user;
    if ($current_user->hasPermission('administer cms content sync')) {
      $config = $this->config_factory;
      $messenger = $this->messenger;
      $developer_config = $config->getEditable('cms_content_sync.developer');
      $version_mismatch = $developer_config->get('version_mismatch');
      if (!empty($version_mismatch)) {
        $links = [];
        foreach ($version_mismatch as $flow_id => $flow) {
          $links[$flow_id] = Link::fromTextAndUrl($flow_id, Url::fromRoute('entity.cms_content_sync_flow.edit_form', ['cms_content_sync_flow' => $flow_id], ['absolute' => TRUE]))->toString();
        }

        $mismatching_flow_labels = implode(',', $links);
        $message = new TranslatableMarkup("You have to update the related flow(s) @flows to keep the content synchronization intact. Failing to update the config may break the synchronization and lead to damaged or missing content.", ['@flows' => new FormattableMarkup($mismatching_flow_labels, [])]);
        $messenger->addWarning($message);
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[KernelEvents::REQUEST][] = ['showVersionWarning'];
    return $events;
  }

}
