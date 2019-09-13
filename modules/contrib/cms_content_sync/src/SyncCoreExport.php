<?php

namespace Drupal\cms_content_sync;

use Drupal\Core\Url;

/**
 *
 */
abstract class SyncCoreExport {
  /**
   * @var \GuzzleHttp\Client
   */
  protected $client;

  /**
   *
   */
  public function __construct() {
    $this->client = \Drupal::httpClient();
  }

  /**
   * Prepare the Sync Core export as a batch operation. Return a batch array
   * with single steps to be executed.
   *
   * @return array Steps
   */
  abstract public function prepareBatch();

  /**
   * Execute a single batch step (as returned as an item from
   * {@see self::prepareBatch()}.
   */
  public function executeBatch($operation) {
    return $this->sendEntityRequest($operation[0], $operation[1]);
  }

  /**
   *
   */
  abstract public function remove($removedOnly = TRUE);

  /**
   * Send a request to the Sync Core backend.
   * Requests will be passed to $this->client.
   *
   * @param string $url
   * @param array $arguments
   *
   * @return bool
   */
  protected function sendEntityRequest($url, $arguments) {
    $entityId = $arguments['json']['id'];
    $method   = $this->checkEntityExists($url, $entityId) ? 'patch' : 'post';

    if ('patch' == $method) {
      $url .= '/' . $entityId;
    }

    // $url .= (strpos($url, '?') === FALSE ? '?' : '&') . 'async=yes';.
    $this->client->{$method}($url, $arguments);

    return TRUE;
  }

  /**
   * @var array
   *   A list of existing entities, cached for better performance.
   */
  protected static $syncCoreData = [];

  /**
   * Check whether or not the given entity already exists.
   *
   * @param string $url
   * @param string $entityId
   *
   * @return bool
   */
  protected function checkEntityExists($url, $entityId) {
    if (empty(self::$syncCoreData[$url])) {
      self::$syncCoreData[$url] = $this->getEntitiesByUrl($url);
    }

    $entityIndex = array_search($entityId, self::$syncCoreData[$url]);
    $entityExists = (FALSE !== $entityIndex);

    return $entityExists;
  }

  /**
   * Get all entities for the given URL from the Sync Core backend.
   *
   * @param string $baseUrl
   * @param array $parameters
   *
   * @return array
   */
  protected function getEntitiesByUrl($baseUrl, $parameters = []) {
    $result = [];
    $url    = $this->generateUrl($baseUrl, $parameters + ['items_per_page' => 999999]);

    $response = $this->client->get($url);
    $body     = $response->getBody()->getContents();
    $body     = json_decode($body);

    foreach ($body->items as $value) {
      if (!empty($value->id)) {
        $result[] = $value->id;
      }
    }

    return $result;
  }

  /**
   * Get a URL string from the given url with additional query parameters.
   *
   * @param $url
   * @param array $parameters
   *
   * @return string
   */
  protected function generateUrl($url, $parameters = []) {
    $resultUrl = Url::fromUri($url, [
      'query' => $parameters,
    ]);

    return $resultUrl->toUriString();
  }

  /**
   * Get the current module version to send to the Sync Core.
   *
   * @return string
   */
  public static function getVersion() {
    return system_get_info('module', 'cms_content_sync')['version'];
  }

  /**
   * Check if exporting previews for each entity should be enabled.
   *
   * @return bool
   */
  public static function isPreviewEnabled() {
    // Check if the base_url is overwritten within the settings.
    $cms_content_sync_settings = \Drupal::config('cms_content_sync.settings');
    return boolval($cms_content_sync_settings->get('cms_content_sync_enable_preview'));
  }

}
