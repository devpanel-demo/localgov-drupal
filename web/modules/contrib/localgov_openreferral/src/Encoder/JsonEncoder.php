<?php

namespace Drupal\localgov_openreferral\Encoder;

use Symfony\Component\Serializer\Encoder\JsonDecode;
use Symfony\Component\Serializer\Encoder\JsonEncode;
use Symfony\Component\Serializer\Encoder\JsonEncoder as BaseJsonEncoder;

/**
 * Uses JSON Encoder for Open Referral.
 */
class JsonEncoder extends BaseJsonEncoder {

  /**
   * The formats that this Encoder supports.
   */
  public const FORMAT = 'openreferral_json';

  /**
   * {@inheritdoc}
   */
  public function __construct(?JsonEncode $encodingImpl = NULL, ?JsonDecode $decodingImpl = NULL, array $defaultContext = []) {
    parent::__construct($encodingImpl, $decodingImpl, $defaultContext);
    // Encode <, >, ', &, and " for RFC4627-compliant JSON, which may also be
    // embedded into HTML.
    // @see \Symfony\Component\HttpFoundation\JsonResponse
    $json_encoding_options = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
    $this->encodingImpl = $encodingImpl ?: new JsonEncode([JsonEncode::OPTIONS => $json_encoding_options]);
    $this->decodingImpl = $decodingImpl ?: new JsonDecode([JsonDecode::ASSOCIATIVE => TRUE]);
  }

  /**
   * {@inheritdoc}
   */
  public function supportsEncoding(string $format): bool {
    return self::FORMAT === $format;
  }

  /**
   * {@inheritdoc}
   */
  public function supportsDecoding(string $format): bool {
    return self::FORMAT === $format;
  }

}
