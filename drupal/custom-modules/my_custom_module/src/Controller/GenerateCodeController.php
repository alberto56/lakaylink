<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\commerce_store\Entity\StoreInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller responsible for generating time-limited signed store codes.
 *
 * This controller creates a secure code containing:
 * - Expiry timestamp
 * - Commerce store ID
 * - Cryptographic signature (SHA-256)
 *
 * The code can be shared with buyers and validated later.
 */
class GenerateCodeController extends ControllerBase {

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Drupal time service for request-aware timestamps.
   */
  public function __construct(
    protected TimeInterface $time,
  ) {}

  /**
   * Dependency injection factory method.
   *
   * This is required for controllers in Drupal to access services.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of this controller.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      // Inject Drupal's time service.
      $container->get('datetime.time')
    );
  }

  /**
   * Generates a secure time-limited code for a commerce store.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $commerce_store
   *   The store entity for which the code is generated.
   *
   * @return array
   *   A render array containing the store label and generated code.
   */
  public function generate(StoreInterface $commerce_store): array {

    // Get current request time (Drupal-aware instead of PHP time()).
    $current_time = $this->time->getRequestTime();

    // Set expiry time to 2 minutes (120 seconds) from now.
    $expiry = $current_time + 120;

    // Build a secure signature using expiry, store ID, and site hash salt.
    $signature = hash(
      'sha256',

      // Encode data in a consistent format before hashing.
      Json::encode([
        $expiry,
        $commerce_store->id(),
        Settings::get('hash_salt'),
      ])
    );

    // Combine expiry, store ID, and signature into a single transferable code.
    $code = implode('/', [
      $expiry,
      $commerce_store->id(),
      $signature,
    ]);

    // Return render array for Drupal page output.
    return [

      // Store title heading.
      'title' => [
        '#markup' => '<h2>' . $commerce_store->label() . '</h2>',
      ],

      // Instruction text.
      'instructions' => [
        '#markup' => '<p>Send this code to the buyer.</p>',
      ],

      // Generated code field (read-only for copying).
      'code' => [
        '#type' => 'textfield',

        // Pre-filled generated secure code.
        '#value' => $code,

        // Prevent user editing.
        '#attributes' => [
          'readonly' => 'readonly',
        ],
      ],
    ];
  }

}