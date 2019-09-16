<?php

namespace Drupal\simple_oauth_extras\Controller;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\simple_oauth\Entities\UserEntity;
use Drupal\simple_oauth\KnownClientsRepositoryInterface;
use Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface;
use GuzzleHttp\Psr7\Response;
use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\Entities\ScopeEntityInterface;
use League\OAuth2\Server\Exception\OAuthServerException;
use League\OAuth2\Server\RequestTypes\AuthorizationRequest;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Oauth2AuthorizeController.
 */
class Oauth2AuthorizeController extends ControllerBase {

  /**
   * @var \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface
   */
  protected $messageFactory;

  /**
   * @var \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface
   */
  protected $grantManager;

  /**
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  protected $formBuilder;

  /**
   * The messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The known client repository service.
   *
   * @var \Drupal\simple_oauth\KnownClientsRepositoryInterface
   */
  protected $knownClientRepository;

  /**
   * Oauth2AuthorizeController construct.
   *
   * @param \Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface $message_factory
   *   The PSR-7 converter.
   * @param \Drupal\simple_oauth\Plugin\Oauth2GrantManagerInterface $grant_manager
   *   The plugin.manager.oauth2_grant.processor service.
   * @param \Drupal\Core\Form\FormBuilderInterface $form_builder
   *   The form builder.
   * @param \Drupal\Core\Messenger\MessengerInterface $messenger
   *   The messenger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config factory.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface $known_clients_repository
   *   The known client repository service.
   */
  public function __construct(HttpMessageFactoryInterface $message_factory, Oauth2GrantManagerInterface $grant_manager, FormBuilderInterface $form_builder, MessengerInterface $messenger, ConfigFactoryInterface $config_factory, KnownClientsRepositoryInterface $known_clients_repository) {
    $this->messageFactory = $message_factory;
    $this->grantManager = $grant_manager;
    $this->formBuilder = $form_builder;
    $this->messenger = $messenger;
    $this->configFactory = $config_factory;
    $this->knownClientRepository = $known_clients_repository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('psr7.http_message_factory'),
      $container->get('plugin.manager.oauth2_grant.processor'),
      $container->get('form_builder'),
      $container->get('messenger'),
      $container->get('config.factory'),
      $container->get('simple_oauth.known_clients')
    );
  }

  /**
   * Authorizes the code generation or prints the confirmation form.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request.
   *
   * @return mixed
   *   The response.
   */
  public function authorize(Request $request) {
    $client_uuid = $request->get('client_id');
    if (empty($client_uuid)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }
    try {
      $consumer_storage = $this->entityTypeManager()->getStorage('consumer');
    }
    catch (InvalidPluginDefinitionException $exception) {
      watchdog_exception('simple_oauth_extras', $exception);
      return RedirectResponse::create(Url::fromRoute('<front>')->toString());
    }
    $client_drupal_entities = $consumer_storage
      ->loadByProperties([
        'uuid' => $client_uuid,
      ]);
    if (empty($client_drupal_entities)) {
      return OAuthServerException::invalidClient()
        ->generateHttpResponse(new Response());
    }

    $client_drupal_entity = reset($client_drupal_entities);
    $is_third_party = $client_drupal_entity->get('third_party')->value;

    $scopes = [];
    if ($request->query->get('scope')) {
      $scopes = explode(' ', $request->query->get('scope'));
    }

    if ($this->currentUser()->isAnonymous()) {
      $this->messenger->addStatus($this->t('An external client application is requesting access to your data in this site. Please log in first to authorize the operation.'));
      // If the user is not logged in.
      $destination = Url::fromRoute('oauth2_token_extras.authorize', [], [
        'query' => UrlHelper::parse('/?' . $request->getQueryString())['query'],
      ]);
      $url = Url::fromRoute('user.login', [], [
        'query' => ['destination' => $destination->toString()],
      ]);
      // Client ID and secret may be passed as Basic Auth. Copy the headers.
      return RedirectResponse::create($url->toString(), 302, $request->headers->all());
    }
    elseif (!$is_third_party || $this->isKnownClient($client_uuid, $scopes)) {
      // Login user may skip the grant step if the client is not third party or
      // known.
      if ($request->get('response_type') == 'code') {
        $grant_type = 'code';
      }
      elseif ($request->get('response_type') == 'token') {
        $grant_type = 'implicit';
      }
      else {
        $grant_type = NULL;
      }
      try {
        $server = $this->grantManager->getAuthorizationServer($grant_type);
        $ps7_request = $this->messageFactory->createRequest($request);
        $auth_request = $server->validateAuthorizationRequest($ps7_request);
      }
      catch (OAuthServerException $exception) {
        $this->messenger->addMessage($this->t('Fatal error. Unable to get the authorization server.'));
        watchdog_exception('simple_oauth_extras', $exception);
        return RedirectResponse::create(Url::fromRoute('<front>')->toString());
      }
      if ($auth_request) {
        $can_grant_codes = $this->currentUser()
          ->hasPermission('grant simple_oauth codes');
        return static::redirectToCallback(
          $auth_request,
          $server,
          $this->currentUser,
          $can_grant_codes
        );
      }
    }
    return $this->formBuilder->getForm('Drupal\simple_oauth_extras\Controller\Oauth2AuthorizeForm');
  }

  /**
   * Generates a redirection response to the consumer callback.
   *
   * @param \League\OAuth2\Server\RequestTypes\AuthorizationRequest $auth_request
   *   The auth request.
   * @param \League\OAuth2\Server\AuthorizationServer $server
   *   The authorization server.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The user to be logged in.
   * @param bool $can_grant_codes
   *   Weather or not the user can grant codes.
   * @param bool $remembers_clients
   *   Weather or not the sites remembers consumers that were previously
   *   granted access.
   * @param \Drupal\simple_oauth\KnownClientsRepositoryInterface|null $known_clients_repository
   *   The known clients repository.
   *
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   The response.
   */
  public static function redirectToCallback(
    AuthorizationRequest $auth_request,
    AuthorizationServer $server,
    AccountInterface $current_user,
    $can_grant_codes,
    $remembers_clients = FALSE,
    KnownClientsRepositoryInterface $known_clients_repository = NULL
  ) {
    // Once the user has logged in set the user on the AuthorizationRequest.
    $user_entity = new UserEntity();
    $user_entity->setIdentifier($current_user->id());
    $auth_request->setUser($user_entity);
    // Once the user has approved or denied the client update the status
    // (true = approved, false = denied).
    $auth_request->setAuthorizationApproved($can_grant_codes);
    // Return the HTTP redirect response.
    $response = $server->completeAuthorizationRequest($auth_request, new Response());

    // Remembers the choice for the current user.
    if ($remembers_clients) {
      $scopes = array_map(function (ScopeEntityInterface $scope) {
        return $scope->getIdentifier();
      }, $auth_request->getScopes());
      $known_clients_repository = $known_clients_repository instanceof KnownClientsRepositoryInterface
        ? $known_clients_repository
        : \Drupal::service('simple_oauth.known_clients');

      $known_clients_repository->rememberClient(
        $current_user->id(),
        $auth_request->getClient()->getIdentifier(),
        $scopes
      );
    }

    // Get the location and return a secure redirect response.
    return TrustedRedirectResponse::create(
      $response->getHeaderLine('location'),
      $response->getStatusCode(),
      $response->getHeaders()
    );
  }

  /**
   * Whether the client with the given scopes is known and already authorized.
   *
   * @param string $client_uuid
   *   The client UUID.
   * @param string[] $scopes
   *   The list of scopes.
   *
   * @return bool
   *   TRUE if the client is authorized, FALSE otherwise.
   */
  protected function isKnownClient($client_uuid, array $scopes) {
    if (!$this->configFactory->get('simple_oauth.settings')->get('remember_clients')) {
      return FALSE;
    }
    return $this->knownClientRepository->isAuthorized($this->currentUser()->id(), $client_uuid, $scopes);
  }

}
