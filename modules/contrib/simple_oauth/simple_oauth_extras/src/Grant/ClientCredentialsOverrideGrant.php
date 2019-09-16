<?php

namespace Drupal\simple_oauth_extras\Grant;

use League\OAuth2\Server\Entities\ClientEntityInterface;
use League\OAuth2\Server\Grant\ClientCredentialsGrant;
use League\OAuth2\Server\ResponseTypes\ResponseTypeInterface;
use Psr\Http\Message\ServerRequestInterface;

class ClientCredentialsOverrideGrant extends ClientCredentialsGrant {

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \League\OAuth2\Server\Exception\OAuthServerException
   * @throws \League\OAuth2\Server\Exception\UniqueTokenIdentifierConstraintViolationException
   */
  public function respondToAccessTokenRequest(
    ServerRequestInterface $request,
    ResponseTypeInterface $responseType,
    \DateInterval $accessTokenTTL
  ) {
    // Validate request.
    $client = $this->validateClient($request);
    $scopes = $this->validateScopes($this->getRequestParameter('scope', $request));

    // Finalize the requested scopes.
    $finalized_scopes = $this->scopeRepository->finalizeScopes($scopes, $this->getIdentifier(), $client);

    // Issue and persist access token.
    $access_token = $this->issueAccessToken(
      $accessTokenTTL,
      $client,
      $this->getDefaultUser($client),
      $finalized_scopes
    );

    // Inject access token into response type.
    $responseType->setAccessToken($access_token);

    return $responseType;
  }

  /**
   * Finds the default user for the client.
   *
   * @param \League\OAuth2\Server\Entities\ClientEntityInterface $client
   *   The League's client.
   *
   * @return \Drupal\user\Entity\User
   *   The default user.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   */
  protected function getDefaultUser(ClientEntityInterface $client) {
    $client_drupal_entities = \Drupal::entityTypeManager()
      ->getStorage('consumer')
      ->loadByProperties([
        'uuid' => $client->getIdentifier(),
      ]);
    $client_drupal_entity = reset($client_drupal_entities);

    return $client_drupal_entity
      ? $client_drupal_entity->get('user_id')->target_id
      : NULL;
  }

}
