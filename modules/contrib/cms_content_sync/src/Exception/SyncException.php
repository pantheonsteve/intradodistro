<?php

namespace Drupal\cms_content_sync\Exception;

/**
 * Class SyncException, thrown if anything goes wrong during import / export
 * of entities for CMS Content Sync. Will be caught by the Flow
 * synchronization class, saved to the logs, presented to the user and returned
 * to Sync Core.
 */
class SyncException extends \Exception {

  /**
   * @var string
   *   The error code constant (see below).
   */
  public $errorCode;

  /**
   * @var \Exception
   *   The parent exception that caused this exception, if any.
   */
  public $parentException;

  /**
   * @var string CODE_ENTITY_API_FAILURE
   *   The entity API returned an unexpected error at some point, e.g. when
   *   saving an entity. More information is available at
   *   {@see SyncException::$parentException}.
   */
  const CODE_ENTITY_API_FAILURE = 'ENTITY_API_FAILURE';

  /**
   * @var string CODE_UNEXPECTED_EXCEPTION
   *   Any unexpected exception was thrown during synchronization. The exception
   *   is available via {@see SyncException::$parentException}.
   */
  const CODE_UNEXPECTED_EXCEPTION = 'UNEXPECTED_EXCEPTION';

  /**
   * @var string CODE_EXPORT_REQUEST_FAILED
   *   The export request to the Sync Core backend failed. The request failure
   *   exception is available via {@see SyncException::$parentException}.
   */
  const CODE_EXPORT_REQUEST_FAILED = 'EXPORT_REQUEST_FAILED';

  /**
   * @var string CODE_INVALID_IMPORT_REQUEST
   *   The import request from Sync Core was invalid.
   */
  const CODE_INVALID_IMPORT_REQUEST = 'INVALID_REQUEST';

  /**
   * @var string CODE_INTERNAL_ERROR
   *   An internal error occurred while processing the request.
   */
  const CODE_INTERNAL_ERROR = 'INTERNAL_ERROR';

  /**
   * SyncException constructor.
   *
   * @param string $errorCode
   *   See SyncException::CODE_*.
   * @param \Exception $parentException
   *   {@see SyncException::$parentException}.
   * @param string $message
   *   Optional message to describe the error in more detail.
   */
  public function __construct($errorCode, \Exception $parentException = NULL, $message = NULL) {
    parent::__construct($message ? $message : $errorCode);

    $this->errorCode       = $errorCode;
    $this->parentException = $parentException;
  }

}
