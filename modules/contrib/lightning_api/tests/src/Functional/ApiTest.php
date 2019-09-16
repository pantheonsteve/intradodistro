<?php

namespace Drupal\Tests\lightning_api\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\consumers\Entity\Consumer;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\Tests\BrowserTestBase;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

/**
 * Tests that OAuth and JSON:API are working together to authenticate, and
 * authorize interaction with entities.
 *
 * @group lightning_api
 * @group headless
 * @group orca_public
 */
class ApiTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['lightning_api', 'taxonomy'];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Allow writing via JSON:API.
    $this->config('jsonapi.settings')->set('read_only', FALSE)->save();

    // Log in as an administrator so that we can generate security keys for
    // OAuth.
    $account = $this->drupalCreateUser([], NULL, TRUE);
    $this->drupalLogin($account);

    $url = Url::fromRoute('lightning_api.generate_keys');
    $values = [
      'dir' => \Drupal::service('file_system')->realpath('temporary://'),
      'private_key' => 'private.key',
      'public_key' => 'public.key',
    ];
    $conf = getenv('OPENSSL_CONF');
    if ($conf) {
      $values['conf'] = $conf;
    }
    $this->drupalPostForm($url, $values, 'Generate keys');
    $this->assertSession()->pageTextContains('A key pair was generated successfully.');
    $this->drupalLogout();
  }

  /**
   * {@inheritdoc}
   */
  protected function createContentType(array $values = []) {
    $node_type = $this->drupalCreateContentType($values);
    // The router needs to be rebuilt in order for the new content type to be
    // available to JSON:API.
    $this->container->get('router.builder')->rebuild();
    return $node_type;
  }

  /**
   * Creates an API user with all privileges for a single content type.
   *
   * @param string $node_type
   *   The content type ID.
   *
   * @return string
   *   The API access token.
   */
  private function getCreator($node_type) {
    return $this->createApiUser([
      "access content",
      "bypass node access",
      "create $node_type content",
      "create url aliases",
      "delete $node_type revisions",
      "edit any $node_type content",
      "edit own $node_type content",
      "revert $node_type revisions",
      "view all revisions",
      "view own unpublished content",
      "view $node_type revisions",
    ]);
  }

  /**
   * Creates a user account with privileged API access.
   *
   * @see ::createUser() for parameter documentation.
   *
   * @return string
   *   The user's access token.
   */
  private function createApiUser(array $permissions = [], $name = NULL, $admin = FALSE) {
    $account = $this->createUser($permissions, $name, $admin);
    $roles = $account->getRoles(TRUE);
    $secret = $this->randomString(32);

    $client = Consumer::create([
      'label' => 'API Test Client',
      'secret' => $secret,
      'confidential' => TRUE,
      'user_id' => $account->id(),
      'roles' => reset($roles),
    ]);
    $client->save();

    $url = $this->buildUrl('/oauth/token');

    $response = $this->container->get('http_client')->post($url, [
      'form_params' => [
        'grant_type' => 'password',
        'client_id' => $client->uuid(),
        'client_secret' => $secret,
        'username' => $account->getAccountName(),
        'password' => $account->passRaw,
      ],
    ]);
    $body = $this->decodeResponse($response);

    // The response should have an access token.
    $this->assertArrayHasKey('access_token', $body);

    return $body['access_token'];
  }

  /**
   * Tests create, read, and update of content entities via the API.
   */
  public function testEntities() {
    $access_token = $this->createApiUser([], NULL, TRUE);

    // Create a taxonomy vocabulary. This cannot currently be done over the API
    // because jsonapi doesn't really support it, and will not be able to
    // properly support it until config entities can be internally validated
    // and access controlled outside of the UI.
    $vocabulary = Vocabulary::create([
      'name' => "I'm a vocab",
      'vid' => 'im_a_vocab',
      'status' => TRUE,
    ]);
    $vocabulary->save();

    $endpoint = '/jsonapi/taxonomy_vocabulary/taxonomy_vocabulary/' . $vocabulary->uuid();

    // Read the newly created vocabulary.
    $response = $this->request($endpoint, 'get', $access_token);
    $body = $this->decodeResponse($response);
    $this->assertSame($vocabulary->label(), $body['data']['attributes']['name']);

    $vocabulary->set('name', 'Still a vocab, just a different title');
    $vocabulary->save();
    // The router needs to be rebuilt in order for the new vocabulary to be
    // available to JSON:API.
    $this->container->get('router.builder')->rebuild();

    // Read the updated vocabulary.
    $response = $this->request($endpoint, 'get', $access_token);
    $body = $this->decodeResponse($response);
    $this->assertSame($vocabulary->label(), $body['data']['attributes']['name']);

    // Assert that the newly created vocabulary's endpoint is reachable.
    $response = $this->request('/jsonapi/taxonomy_term/im_a_vocab');
    $this->assertSame(200, $response->getStatusCode());

    $name = 'zebra';
    $term_uuid = $this->container->get('uuid')->generate();
    $endpoint = '/jsonapi/taxonomy_term/im_a_vocab/' . $term_uuid;

    // Create a taxonomy term (content entity).
    $this->request('/jsonapi/taxonomy_term/im_a_vocab', 'post', $access_token, [
      'data' => [
        'type' => 'taxonomy_term--im_a_vocab',
        'id' => $term_uuid,
        'attributes' => [
          'name' => $name,
          'uuid' => $term_uuid,
        ],
        'relationships' => [
          'vid' => [
            'data' => [
              'type' => 'taxonomy_vocabulary--taxonomy_vocabulary',
              'id' => $vocabulary->uuid(),
            ],
          ],
        ],
      ],
    ]);

    // Read the taxonomy term.
    $response = $this->request($endpoint, 'get', $access_token);
    $body = $this->decodeResponse($response);
    $this->assertSame($name, $body['data']['attributes']['name']);

    $new_name = 'squid';

    // Update the taxonomy term.
    $this->request($endpoint, 'patch', $access_token, [
      'data' => [
        'type' => 'taxonomy_term--im_a_vocab',
        'id' => $term_uuid,
        'attributes' => [
          'name' => $new_name,
        ],
      ],
    ]);

    // Read the updated taxonomy term.
    $response = $this->request($endpoint, 'get', $access_token);
    $body = $this->decodeResponse($response);
    $this->assertSame($new_name, $body['data']['attributes']['name']);
  }

  /**
   * Tests Getting data as anon and authenticated user.
   */
  public function testAllowed() {
    $this->createContentType(['type' => 'page']);
    // Create some sample content for testing. One published and one unpublished
    // basic page.
    $published_node = $this->drupalCreateNode();
    $unpublished_node = $published_node->createDuplicate()->setUnpublished();
    $unpublished_node->save();

    // Get data that is available anonymously.
    $response = $this->request('/jsonapi/node/page/' . $published_node->uuid());
    $this->assertSame(200, $response->getStatusCode());
    $body = $this->decodeResponse($response);
    $this->assertSame($published_node->getTitle(), $body['data']['attributes']['title']);

    // Get data that requires authentication.
    $access_token = $this->getCreator('page');
    $response = $this->request('/jsonapi/node/page/' . $unpublished_node->uuid(), 'get', $access_token);
    $this->assertSame(200, $response->getStatusCode());
    $body = $this->decodeResponse($response);
    $this->assertSame($unpublished_node->getTitle(), $body['data']['attributes']['title']);

    // Post new content that requires authentication.
    $count = (int) \Drupal::entityQuery('node')->count()->execute();
    $this->request('/jsonapi/node/page', 'post', $access_token, [
      'data' => [
        'type' => 'node--page',
        'attributes' => [
          'title' => 'With my own two hands',
        ],
      ],
    ]);
    $this->assertSame(++$count, (int) \Drupal::entityQuery('node')->count()->execute());
  }

  /**
   * Tests that authenticated and anonymous requests cannot get unauthorized
   * data.
   */
  public function testForbidden() {
    $this->createContentType(['type' => 'page']);

    // Cannot get unauthorized data (not in role/scope) even when authenticated.
    $response = $this->request('/jsonapi/user_role/user_role', 'get', $this->getCreator('page'));
    $body = $this->decodeResponse($response);
    $this->assertInternalType('array', $body['meta']['omitted']['links']);
    $this->assertNotEmpty($body['meta']['omitted']['links']);
    unset($body['meta']['omitted']['links']['help']);

    foreach ($body['meta']['omitted']['links'] as $link) {
      // This user/client should not have access to any of the roles' data.
      $this->assertSame(
        "The current user is not allowed to GET the selected resource. The 'administer permissions' permission is required.",
        $link['meta']['detail']
      );
    }

    // Cannot get unauthorized data anonymously.
    $unpublished_node = $this->drupalCreateNode()->setUnpublished();
    $unpublished_node->save();
    $url = $this->buildUrl('/jsonapi/node/page/' . $unpublished_node->uuid());
    // Unlike the roles test which requests a list, JSON API sends a 403 status
    // code when requesting a specific unauthorized resource instead of list.
    $this->setExpectedException(ClientException::class, "Client error: `GET $url` resulted in a `403 Forbidden`");
    $this->container->get('http_client')->get($url);
  }

  /**
   * Makes a request to the API using an optional OAuth token.
   *
   * @param string $endpoint
   *   Path to the API endpoint.
   * @param string $method
   *   The RESTful verb.
   * @param string $token
   *   A valid OAuth token to send as an Authorization header with the request.
   * @param array $data
   *   Additional json data to send with the request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   The response from the request.
   */
  private function request($endpoint, $method = 'get', $token = NULL, $data = NULL) {
    $options = NULL;
    if ($token) {
      $options = [
        'headers' => [
          'Authorization' => 'Bearer ' . $token,
          'Content-Type' => 'application/vnd.api+json',
        ],
      ];
    }
    if ($data) {
      $options['json'] = $data;
    }

    $url = $this->buildUrl($endpoint);
    return $this->container->get('http_client')->$method($url, $options);
  }

  /**
   * Decodes a JSON response from the server.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   *
   * @return mixed
   *   The decoded response data. If the JSON parser raises an error, the test
   *   will fail, with the bad input as the failure message.
   */
  private function decodeResponse(ResponseInterface $response) {
    $body = (string) $response->getBody();

    $data = Json::decode($body);
    if (json_last_error() === JSON_ERROR_NONE) {
      return $data;
    }
    else {
      $this->fail($body);
    }
  }

}
