<?php

/**
 * @file
 * Contains the InvitationCodeGenerator service.
 */

namespace Drupal\my_custom_module\Service;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Site\Settings;
use Drupal\commerce_store\Entity\StoreInterface;

/**
 * Generates and validates signed buyer invitation codes.
 */
class InvitationCodeGenerator {

  /**
   * Constructs an InvitationCodeGenerator object.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   The time service.
   */
  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * Generates a signed invitation code for a store.
   *
   * The generated code contains:
   * - The expiration timestamp.
   * - The store ID.
   * - A SHA-256 signature generated using the site's hash salt.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $store
   *   The store for which the invitation code is generated.
   *
   * @return string
   *   A signed invitation code.
   */
  public function generate(StoreInterface $store): string {
    // Set the invitation code to expire one month from the current request.
    $expiry = (new \DateTimeImmutable('@' . $this->time->getRequestTime()))
      ->modify('+1 month')
      ->getTimestamp();

    // Generate a signature using the expiry time, store ID, and site hash salt.
    $signature = hash(
      'sha256',
      Json::encode([
        $expiry,
        $store->id(),
        Settings::get('hash_salt'),
      ])
    );

    // Return the invitation code in the format:
    // expiry/store_id/signature.
    return implode('/', [
      $expiry,
      $store->id(),
      $signature,
    ]);
  }

  /**
   * Validates a signed invitation code.
   *
   * The expected code format is:
   * expiry/store_id/signature
   *
   * @param string $code
   *   The invitation code to validate.
   *
   * @return array|null
   *   An associative array containing:
   *   - expiry: The expiration timestamp.
   *   - store_id: The associated store ID.
   *   Returns NULL if the code is invalid or expired.
   */
  public function validate(string $code): ?array {
    // Split the code into its three expected parts.
    $parts = explode('/', trim($code));

    if (count($parts) !== 3) {
      return NULL;
    }

    [$expiry, $store_id, $signature] = $parts;

    // Ensure the expiry and store ID are valid numeric values.
    if (!ctype_digit($expiry) || !ctype_digit($store_id)) {
      return NULL;
    }

    // Reject expired invitation codes.
    if ((int) $expiry < time()) {
      return NULL;
    }

    // Recreate the expected signature for comparison.
    $expected = hash(
      'sha256',
      Json::encode([
        (int) $expiry,
        $store_id,
        Settings::get('hash_salt'),
      ])
    );

    // Reject the code if the signature does not match.
    if (!hash_equals($expected, $signature)) {
      return NULL;
    }

    // Return the validated invitation data.
    return [
      'expiry' => (int) $expiry,
      'store_id' => (int) $store_id,
    ];
  }

}
