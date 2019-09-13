<?php

namespace Drupal\cms_content_sync\SyncCore;

/**
 * Class Query
 * A query to execute against the Sync Core. Will return a Result object when
 * executed. This is just a simple helper class to simplify query creation in
 * an OOP fashion.
 *
 * @package Drupal\cms_content_sync\SyncCore
 */
abstract class Query {

  /**
   * @var \Drupal\cms_content_sync\SyncCore\Storage\Storage
   */
  protected $storage;

  /**
   * @var array
   */
  protected $arguments = [];

  /**
   * Query constructor.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   */
  public function __construct($storage) {
    $this->storage = $storage;
  }

  /**
   * Get the arguments stored.
   *
   * @return array
   */
  public function toArray() {
    return $this->arguments;
  }

  /**
   * Get the Storage the Query belongs to.
   *
   * @return \Drupal\cms_content_sync\SyncCore\Storage\Storage
   */
  public function getStorage() {
    return $this->storage;
  }

  /**
   * Get a RequestArguments instance.
   *
   * @param \Drupal\cms_content_sync\SyncCore\Storage\Storage $storage
   *
   * @return \Drupal\cms_content_sync\SyncCore\Query
   */
  abstract public static function create($storage);

  /**
   * Get the path to be appended after the Pool::backend_url for this Query.
   *
   * @return string
   */
  abstract public function getPath();

  /**
   * Get the HTTP method to use for the request.
   *
   * @return string
   */
  public function getMethod() {
    return Client::METHOD_GET;
  }

  /**
   * Get the request body to use.
   *
   * @return string|null
   */
  public function getBody() {
    return NULL;
  }

  /**
   * @return bool
   */
  public function returnBoolean() {
    return FALSE;
  }

  /**
   * Provide a Result object to get the actual entities from.
   *
   * @return \Drupal\cms_content_sync\SyncCore\Result
   */
  abstract public function execute();

}
