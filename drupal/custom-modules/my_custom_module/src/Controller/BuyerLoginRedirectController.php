<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles post-login redirection for buyer users.
 *
 * After authentication, this controller determines the appropriate
 * destination based on the user's role and assigned stores:
 * - Anonymous users are redirected to the custom login page.
 * - Buyers with a single assigned store are redirected directly to it.
 * - Buyers with multiple assigned stores are redirected to the store
 *   selection page.
 * - Buyers without assigned stores are denied access.
 * - Other authenticated users are redirected to the administration area.
 */
class BuyerLoginRedirectController extends ControllerBase {

  /**
   * Constructs a BuyerLoginRedirectController object.
   *
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyerStoreResolver
   *   The buyer store resolver service.
   */
  public function __construct(
    private readonly BuyerStoreResolverInterface $buyerStoreResolver,
  ) {}

  /**
   * Creates a controller instance from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new controller instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_custom_module.buyer_store_resolver')
    );
  }

  /**
   * Redirects users to the appropriate destination after login.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response to the appropriate page.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when a buyer has not been assigned any stores.
   */
  public function landing(): RedirectResponse {

    // Redirect anonymous users to the custom login page.
    if ($this->currentUser()->isAnonymous()) {
      return new RedirectResponse(
        Url::fromRoute('my_custom_module.custom_login')->toString()
      );
    }

    // Handle buyer-specific redirection logic.
    if ($this->currentUser->hasRole('buyer')) {

      $stores = $this->buyerStoreResolver
        ->getAllowedStores($this->currentUser);

      $count = count($stores);

      // Buyers must have at least one assigned store.
      if ($count === 0) {
        throw new AccessDeniedHttpException(
          'No stores have been assigned to your account.'
        );
      }

      // Redirect directly when only one store is available.
      if ($count === 1) {
        $store = reset($stores);

        return new RedirectResponse(
          $store->toUrl()->toString()
        );
      }

      // Redirect to the store selection page when multiple stores exist.
      return new RedirectResponse(
        Url::fromRoute('view.store_selector.page_1')->toString()
      );
    }

    // Redirect all other authenticated users to the admin dashboard.
    return new RedirectResponse('/admin');
  }

}
