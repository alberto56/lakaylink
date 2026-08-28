<?php

namespace Drupal\commerce_store\Entity;

use Drupal\Core\Url;

interface StoreInterface {

  /**
   * Gets the store ID.
   *
   * @return int
   *   The store ID.
   */
  public function id();

  /**
   * Generates a URL for the store.
   *
   * @param string $rel
   *   The link relationship type.
   * @param array $options
   *   URL options.
   *
   * @return \Drupal\Core\Url
   *   The URL object.
   */
  public function toUrl(string $rel = 'canonical', array $options = []): Url;

}

class Store {

  public static function load($id): ?self {
    return NULL;
  }

  public function id(): int {
    return 0;
  }

  public function toUrl(): Url {
    return new Url('entity.commerce_store.canonical', [
      'commerce_store' => $this->id(),
    ]);
  }

}
