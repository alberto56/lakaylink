<?php

/**
 * @file
 * Contains the BuyerVerificationResult value object.
 */

namespace Drupal\my_custom_module\Service;

use Drupal\Core\Url;

/**
 * Represents the result of a buyer verification operation.
 *
 * Encapsulates the verification status, a user-facing message, and an optional
 * redirect URL to be used after successful verification.
 */
final class BuyerVerificationResult {

  /**
   * Creates a new buyer verification result.
   *
   * @param bool $success
   *   Indicates whether the verification was successful.
   * @param string $message
   *   A user-facing message describing the verification result.
   * @param \Drupal\Core\Url|null $redirectUrl
   *   An optional redirect URL for the next destination after verification.
   */
  public function __construct(
    // Indicates whether the verification succeeded.
    public readonly bool $success,

    // Message describing the outcome of the verification.
    public readonly string $message,

    // Optional redirect destination after verification.
    public readonly ?Url $redirectUrl = NULL,
  ) {}

}
