<?php

namespace Drupal\webhooks;

use Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher;
use Drupal\Component\Uuid\Php as Uuid;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\webhooks\Entity\WebhookConfig;
use Drupal\webhooks\Event\WebhookEvents;
use Drupal\webhooks\Event\ReceiveEvent;
use Drupal\webhooks\Event\SendEvent;
use Drupal\webhooks\Exception\WebhookIncomingEndpointNotFoundException;
use GuzzleHttp\Client;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class WebhookService.
 *
 * @package Drupal\webhooks
 */
class WebhooksService implements WebhooksServiceInterface {

  /**
   * The Json format.
   */
  const CONTENT_TYPE_JSON = 'json';

  /**
   * The Xml format.
   */
  const CONTENT_TYPE_XML = 'xml';

  /**
   * The http client.
   *
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   * The logger channel factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerFactory;

  /**
   * The request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * The webhook container object.
   *
   * @var \Drupal\webhooks\Webhook
   */
  protected $webhook;

  /**
   * The event dispatcher.
   *
   * @var \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher
   */
  protected $eventDispatcher;

  /**
   * The query factory.
   *
   * @var \Drupal\Core\Entity\Query\QueryFactory
   */
  protected $queryFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * WebhookService constructor.
   *
   * @param \GuzzleHttp\Client $client
   *   A http client.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   A logger channel factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The current request stack.
   * @param \Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher $event_dispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\Query\QueryFactory $query_factory
   *   The query factory.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
      Client $client,
      LoggerChannelFactoryInterface $logger_factory,
      RequestStack $request_stack,
      ContainerAwareEventDispatcher $event_dispatcher,
      QueryFactory $query_factory,
      EntityTypeManagerInterface $entity_type_manager
  ) {
    $this->client = $client;
    $this->loggerFactory = $logger_factory;
    $this->requestStack = $request_stack;
    $this->eventDispatcher = $event_dispatcher;
    $this->queryFactory = $query_factory;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Load multiple WebhookConfigs by event.
   *
   * @param string $event
   *   An event string in the form of entity:entity_type:action,
   *   e.g. 'entity:user:create', 'entity:user:update' or 'entity:user:delete'.
   * @param string $type
   *   A type string, e.g. 'outgoing' or 'incoming'.
   *
   * @return \Drupal\webhooks\Entity\WebhookConfigInterface[]
   *   An array of WebhookConfig entities.
   */
  public function loadMultipleByEvent($event, $type = 'outgoing') {
    $query = $this->queryFactory->get('webhook_config')
      ->condition('status', 1)
      ->condition('events', $event, 'CONTAINS')
      ->condition('type', $type, '=');
    $ids = $query->execute();
    return $this->entityTypeManager->getStorage('webhook_config')
      ->loadMultiple($ids);
  }

  /**
   * Send a webhook.
   *
   * @param \Drupal\webhooks\Entity\WebhookConfig $webhook_config
   *   A webhook config entity.
   * @param \Drupal\webhooks\Webhook $webhook
   *   A webhook object.
   */
  public function send(WebhookConfig $webhook_config, Webhook $webhook) {
    $uuid = new Uuid();
    $webhook->setUuid($uuid->generate());
    if ($secret = $webhook_config->getSecret()) {
      $webhook->setSecret($secret);
      $webhook->setSignature();
    }

    $headers = $webhook->getHeaders();
    $body = self::encode(
      $webhook->getPayload(),
      $webhook_config->getContentType()
    );

    try {
      $this->client->post(
        $webhook_config->getPayloadUrl(),
        ['headers' => $headers, 'body' => $body]
      );
    }
    catch (\Exception $e) {
      $this->loggerFactory->get('webhooks')->error(
        'Could not send Webhook @webhook: @message',
        ['@webhook' => $webhook_config->id(), '@message' => $e->getMessage()]
      );
    }

    // Dispatch Webhook Send event.
    $this->eventDispatcher->dispatch(
      WebhookEvents::SEND,
      new SendEvent($webhook_config, $webhook)
    );

    // Log the sent webhook.
    $this->loggerFactory->get('webhooks')->info(
      'Sent a Webhook: <code><pre>@webhook</pre></code>',
      ['@webhook' => print_r($webhook, TRUE)]
    );
  }

  /**
   * Receive a webhook.
   *
   * @param string $name
   *   The machine name of a webhook.
   *
   * @return \Drupal\webhooks\Webhook
   *   A webhook object.
   *
   * @throws \Drupal\webhooks\Exception\WebhookIncomingEndpointNotFoundException
   *   Thrown when the webhook endpoint is not found.
   */
  public function receive($name) {
    // We only receive webhook requests when a webhook configuration exists
    // with a matching machine name.
    $query = $this->queryFactory->get('webhook_config')
      ->condition('status', 1);
    $ids = $query->execute();
    if (!array_key_exists($name, $ids)) {
      throw new WebhookIncomingEndpointNotFoundException($name);
    }

    $request = $this->requestStack->getCurrentRequest();
    $headers = $request->headers->all();
    $payload = WebhooksService::decode(
      $request->getContent(),
      $request->getContentType()
    );

    /** @var \Drupal\webhooks\Webhook $webhook */
    $webhook = new Webhook($payload, $headers);
    $signature = $webhook->getSignature();
    if (!empty($signature)) {
      $webhook->verify();
    }

    // Dispatch Webhook Receive event.
    $this->eventDispatcher->dispatch(
      WebhookEvents::RECEIVE,
      new ReceiveEvent($webhook)
    );

    return $webhook;
  }

  /**
   * Encode payload data.
   *
   * @param array $data
   *   The payload data array.
   * @param string $content_type
   *   The content type string, e.g. json, xml.
   *
   * @return string
   *   A string suitable for a http request.
   */
  public static function encode($data, $content_type) {
    try {
      /** @var \Drupal\serialization\Encoder\JsonEncoder $encoder */
      $encoder = \Drupal::service('serializer.encoder.' . $content_type);
      if (!empty($encoder) && $encoder->supportsEncoding($content_type)) {
        return $encoder->encode($data, $content_type);
      }
    }
    catch (\Exception $e) {
    }
    return '';
  }

  /**
   * Decode payload data.
   *
   * @param array $data
   *   The payload data array.
   * @param string $format
   *   The format string, e.g. json, xml.
   *
   * @return mixed
   *   A string suitable for php usage.
   */
  public static function decode($data, $format) {
    try {
      /** @var \Drupal\serialization\Encoder\JsonEncoder $encoder */
      $encoder = \Drupal::service('serializer.encoder.' . $format);
      if (!empty($encoder) && $encoder->supportsDecoding($format)) {
        return $encoder->decode($data, $format);
      }
    }
    catch (\Exception $e) {
    }
    return '';
  }

}
