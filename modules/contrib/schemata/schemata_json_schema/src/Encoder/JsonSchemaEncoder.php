<?php

namespace Drupal\schemata_json_schema\Encoder;

use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Drupal\serialization\Encoder\JsonEncoder as JsonEncoder;

/**
 * Encodes data into json_schema.
 *
 * Simply respond to application/vnd.api+json format requests using encoder.
 */
class JsonSchemaEncoder extends JsonEncoder {

  /**
   * The formats that this Encoder supports.
   *
   * @var string
   */
  protected $baseFormat = 'schema_json';

  /**
   * The decorated encoder.
   *
   * @var \Symfony\Component\Serializer\Encoder\EncoderInterface
   */
  protected $innerEncoder;

  /**
   * Create a JsonSchemaEncoder instance.
   *
   * @param \Symfony\Component\Serializer\Encoder\EncoderInterface $inner_encoder
   */
  public function __construct(EncoderInterface $inner_encoder) {
    parent::__construct();
    $this->innerEncoder = $inner_encoder;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding($format) {
    list ($base, $encoded) = explode(':', $format, 2);
    if (empty($encoded)) {
      // Require sub type.
      return FALSE;
    }
    // Verify the correct base and that the sub type is supported by inner.
    return ($base === $this->baseFormat) && $this->innerEncoder->supportsEncoding($encoded);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding($format) {
    // We don't decode have a decoding system for json_schema.
    // @TODO: Implement conversion of json_schema to typed data.
    return FALSE;
  }

}
