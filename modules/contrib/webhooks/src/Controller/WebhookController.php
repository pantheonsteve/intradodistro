<?php

namespace Drupal\webhooks\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\webhooks\Exception\WebhookIncomingEndpointNotFoundException;
use Drupal\webhooks\Exception\WebhookMismatchSignatureException;
use Drupal\webhooks\WebhooksService;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class Webhook.
 *
 * @package Drupal\webhooks\Controller
 */
class WebhookController extends ControllerBase {

  /**
   * The webhooks service.
   *
   * @var \Drupal\webhooks\WebhooksService
   */
  protected $webhooksService;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * WebhookController constructor.
   *
   * @param \Drupal\webhooks\WebhooksService $webhooks_service
   *   The Webhooks service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   A logger channel factory.
   */
  public function __construct(
      WebhooksService $webhooks_service,
      EntityTypeManagerInterface $entity_type_manager,
      LoggerChannelFactoryInterface $logger_factory
  ) {
    $this->webhooksService = $webhooks_service;
    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('webhooks.service'),
      $container->get('entity_type.manager'),
      $container->get('logger.factory')
    );
  }

  /**
   * Webhooks receiver.
   *
   * @param string $name
   *   The machine name of a webhook.
   *
   * @return \GuzzleHttp\Psr7\Response
   *   Return a response with code 200 for OK or code 500 in case of error.
   */
  public function receive($name) {
    try {
      $this->webhooksService->receive($name);
    }
    catch (WebhookIncomingEndpointNotFoundException $e) {
      $this->loggerFactory->get('webhooks')->error(
        $e->getMessage()
      );
      return new Response(404, [], $e->getMessage());
    }
    catch (WebhookMismatchSignatureException $e) {
      $this->loggerFactory->get('webhooks')->error(
        'Signature not matching for received Webhook @webhook: @message',
        ['@webhook' => $webhook_config->id(), '@message' => $e->getMessage()]
      );
      return new Response(500, [], $e->getMessage());
    }
    $this->loggerFactory->get('webhooks')->info(
      'Received a Webhook: <code><pre>@webhook</pre></code>',
      ['@webhook' => print_r($webhook, TRUE)]
    );
    return new Response(200, [], 'OK');
  }

  /**
   * Access check callback.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current account.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   A successful access result.
   */
  public function access(AccountInterface $account) {
    return AccessResult::allowed();
  }

  /**
   * Toggle the active state.
   *
   * @param mixed $id
   *    The id of the entity given by route url.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object that may be returned by the controller.
   */
  public function toggleActive($id) {
    $webhooks_storage = $this->entityTypeManager->getStorage('webhook_config');
    /** @var \Drupal\webhooks\Entity\WebhookConfig $webhook_config */
    $webhook_config = $webhooks_storage->load($id);
    $webhook_config->setStatus(!$webhook_config->status());
    $webhook_config->save();
    return $this->redirect("entity.webhook_config.collection");
  }

}
