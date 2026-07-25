<?php

namespace Drupal\my_custom_module\Service;

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides access to the currently active store context.
 *
 * The store context is stored in the user session by
 * StoreContextSubscriber and can be retrieved here for use
 * across services, controllers, and forms.
 */
class CurrentStore {

  /**
   * Constructs a CurrentStore service.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack used to access the current HTTP request.
   */
  public function __construct(
    private RequestStack $requestStack,
  ) {}

  /**
   * Gets the currently active store ID from session.
   *
   * @return int|null
   *   The active store ID, or NULL if not set or no request exists.
   */
  public function getStoreId(): ?int {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      return NULL;
    }

    $value = $request->getSession()->get('active_store_id');

    return $value !== NULL ? (int) $value : NULL;
  }

  /**
   * Gets the currently active store slug from session.
   *
   * @return string|null
   *   The active store slug, or NULL if not set or no request exists.
   */
  public function getStoreSlug(): ?string {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      return NULL;
    }

    $value = $request->getSession()->get('active_store_slug');

    return $value !== NULL ? (string) $value : NULL;
  }

}
