<?php

namespace Drupal\cms_content_sync\SyncCore;

use Drupal\cms_content_sync\Entity\Pool;

/**
 *
 */
class SyncCore {
  /**
   * @var SyncCore[]
   */
  protected static $all = NULL;

  /**
   * @return SyncCore[]
   */
  public static function getAll() {
    if (!empty(self::$all)) {
      return self::$all;
    }

    $cores = [];
    foreach (Pool::getAll() as $pool) {
      $url = parse_url($pool->getBackendUrl());
      $host = $url['host'];
      if (isset($cores[$host])) {
        continue;
      }

      $cores[$host] = new SyncCore($pool->getBackendUrl());
    }

    return self::$all = $cores;
  }

  /**
   * Remove any information about basic auth in any URLs contained in the given messages.
   *
   * @param $message
   *
   * @return array|string|string[]|null
   */
  public static function obfuscateCredentials($message) {
    if (is_array($message)) {
      if (isset($message['msg'])) {
        $message['msg'] = self::obfuscateCredentials($message['msg']);
      }
      elseif (isset($message['err']['message'])) {
        $message['err']['message'] = self::obfuscateCredentials($message['err']['message']);
      }
      /**
 *Ignore other associative arrays.
 */
      elseif (isset($message[0])) {
        for ($i = 0; $i < count($message); $i++) {
          $message[$i] = self::obfuscateCredentials($message[$i]);
        }
      }
      return $message;
    }

    $message = preg_replace('@https://([^:]+):([^\@]+)\@@i', 'https://$1:****@', $message);
    $message = preg_replace('@http://([^:]+):([^\@]+)\@@i', 'http://$1:****@', $message);
    return $message;
  }

  protected $url;
  protected $urlComponents;

  /**
   * SyncCore constructor.
   *
   * @param string $url
   */
  public function __construct($url) {
    $this->url = $url;
    $this->urlComponents = parse_url($url);
  }

  const LOG_LEVEL_ERROR   = 'error';
  const LOG_LEVEL_WARNING = 'warn';

  /**
   *
   */
  public function getUrlComponents() {
    return $this->urlComponents;
  }

  /**
   * @param null|array|string $level
   * @return null|array
   */
  public function getLog($level = NULL, $obfuscateCredentials = TRUE) {
    $client = \Drupal::httpClient();

    if ($level) {
      $level = '?level=' . (is_array($level) ? implode(',', $level) : $level);
    }
    else {
      $level = '';
    }

    try {
      $response = $client->get($this->url . '/log' . $level);
      if ($response->getStatusCode() == 200) {
        $items = json_decode($response->getBody(), TRUE)['items'];
        if ($obfuscateCredentials) {
          $items = self::obfuscateCredentials($items);
        }
        foreach ($items as &$item) {
          if (isset($item['err']['message'])) {
            $item['msg'] = $item['err']['message'];
          }
        }
        return $items;
      }
    }
    catch (\Exception $e) {
    }

    return NULL;
  }

  /**
   * @return mixed|null
   */
  public function getStatus() {
    $client = \Drupal::httpClient();

    try {
      $response = $client->get($this->url . '/status');
      if ($response->getStatusCode() == 200) {
        return json_decode($response->getBody(), TRUE);
      }
    }
    catch (\Exception $e) {
    }

    return NULL;
  }

}
