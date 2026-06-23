<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Site\Settings;
use Drupal\commerce_store\Entity\StoreInterface;

class GenerateCodeController extends ControllerBase {

  public function generate(StoreInterface $commerce_store): array {

    $expiry = time() + (14 * 24 * 60 * 60);

    $signature = hash(
      'sha256',
      json_encode([
        $expiry,
        $commerce_store->id(),
        Settings::get('hash_salt'),
      ])
    );

    $code = implode('/', [
      $expiry,
      $commerce_store->id(),
      $signature,
    ]);

    return [
      'title' => [
        '#markup' => '<h2>' . $commerce_store->label() . '</h2>',
      ],
      'instructions' => [
        '#markup' => '<p>Send this code to the buyer.</p>',
      ],
      'code' => [
        '#type' => 'textfield',
        '#value' => $code,
        '#attributes' => [
          'readonly' => 'readonly',
        ],
      ],
    ];
  }

}