<?php

namespace Drupal\cms_content_sync\SyncCore;

use Drupal\Core\Url;

/**
 * Class Client
 * The client used by the Storage to connect to the Sync Core. You can imagine
 * this to be a remote database connection where the Storage talks to individual
 * tables / collections.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
class Client {
  /**
   * @var string METHOD_GET Perform an HTTP GET request.
   */
  const METHOD_GET = 'GET';
  /**
   * @var string METHOD_POST Perform an HTTP POST request.
   */
  const METHOD_POST = 'POST';
  /**
   * @var string METHOD_PUT Perform an HTTP PUT request.
   */
  const METHOD_PUT = 'PUT';
  /**
   * @var string METHOD_DEL Perform an HTTP DEL request.
   */
  const METHOD_DEL = 'DEL';

  /**
   * @var int MAX_ITEMS_PER_PAGE
   *    The maximum number of items that can be queried per page.
   */
  const MAX_ITEMS_PER_PAGE = 100;

  /**
   * @var \GuzzleHttp\Client
   *    The HTTP client used to execute requests.
   */
  protected $client;

  /**
   * @var string
   *    The base URL of the remote Sync Core. See Pool::$backend_url
   */
  protected $base_url;

  /**
   * @param string $base_url
   */
  public function __construct($base_url) {
    $this->base_url = $base_url;
    $this->client = \Drupal::httpClient();
  }

  /**
   * Execute a GET request.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Query $query
   *
   * @return mixed
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function request($query) {
    $url = $this->base_url . $query->getPath();

    $url = Url::fromUri($url, [
      'query' => $query->toArray(),
    ])->toUriString();

    $method = $query->getMethod();
    $body = $query->getBody();

    $response = $this->client->request(
      $method,
      $url,
      array_merge(
        ['http_errors' => FALSE],
        $body ? ['body' => json_encode($body)] : []
      )
    );

    $success = $response->getStatusCode() == 200;

    if ($query->returnBoolean()) {
      return $success;
    }

    if (!$success) {
      throw new \Exception("Failed to get response from Sync Core (Code " . $response->getStatusCode() . "): " . $response->getBody());
    }

    $data = json_decode($response->getBody(), TRUE);

    return $data;
  }

  /**
   * Execute a GET request.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Query|string $url
   *
   * @return mixed
   *
   * @throws \Exception
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function get($url) {
    if (substr($url, 0, 7) == 'http://' || substr($url, 0, 8) == 'https://') {
      $url = preg_replace('@^https?://[^/]+/rest@', $this->base_url, $url);
    }
    else {
      $url = $this->base_url . $url;
    }

    $method = self::METHOD_GET;

    $response = $this->client->request(
      $method,
      $url,
      ['http_errors' => FALSE]
    );

    $success = $response->getStatusCode() == 200;

    if (!$success) {
      throw new \Exception("Failed to get response from Sync Core (Code " . $response->getStatusCode() . "): " . $response->getBody());
    }

    $data = json_decode($response->getBody(), TRUE);

    return $data;
  }

}
