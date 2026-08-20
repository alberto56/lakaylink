<?php

declare(strict_types=1);

/**
 * @file
 * Contains the UserLoginRedirectController class.
 */

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles user login redirection based on assigned roles.
 */
class UserLoginRedirectController extends ControllerBase {

  /**
   * Constructs a UserLoginRedirectController object.
   *
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyerStoreResolver
   *   The buyer store resolver service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $moduleLanguageManager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The Request Stack.
   */
  public function __construct(
    private readonly BuyerStoreResolverInterface $buyerStoreResolver,
    private readonly LanguageManagerInterface $moduleLanguageManager,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * Creates an instance of the controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new controller instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_custom_module.buyer_store_resolver'),
      $container->get('language_manager'),
      $container->get('request_stack'),
    );
  }

  /**
   * Redirects users to the appropriate destination after login.
   *
   * Redirects users based on their authentication status and assigned roles.
   * Buyers are redirected according to their available stores, sellers are
   * redirected to the seller dashboard, and users with multiple roles or
   * unverified accounts are redirected to the role selection page.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   The redirect response.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when a buyer has no assigned stores.
   */
  public function landing(): RedirectResponse {
    // Get the currently logged-in user.
    $user = $this->currentUser();

    $request = $this->requestStack->getCurrentRequest();

    // Prefer the language passed through the OAuth flow.
    $language_code = $request?->query->get('language');

    if ($language_code) {
      $language = $this->moduleLanguageManager->getLanguage($language_code);

      // If an invalid language was supplied, fall back to Drupal's
      // current request language.
      if (!$language) {
        $language = $this->moduleLanguageManager->getCurrentLanguage();
      }
    }
    else {
      // Get the language associated with the current request.
      $language = $this->moduleLanguageManager->getCurrentLanguage();
    }

    // Redirect anonymous users to the localized custom login page.
    if ($user->isAnonymous()) {
      return new RedirectResponse(
        Url::fromRoute(
          'my_custom_module.custom_login',
          [],
          [
            'language' => $language,
          ]
        )->toString()
      );
    }

    // Exclude the default authenticated role when evaluating user roles.
    $roles = array_diff($user->getRoles(), ['authenticated']);

    // Redirect users who are only unverified or who have both buyer and
    // seller roles to the localized role selection page.
    if (
      ($user->hasRole('unverified') && count($roles) === 1) ||
      (
        $this->currentUser->hasRole('buyer') &&
        $this->currentUser->hasRole('seller')
      )
    ) {
      return new RedirectResponse(
        Url::fromRoute(
          'my_custom_module.home',
          [],
          [
            'language' => $language,
          ]
        )->toString()
      );
    }

    // Redirect sellers to the localized seller dashboard.
    if ($this->currentUser->hasRole('seller')) {
      return new RedirectResponse(
        Url::fromRoute(
          'my_custom_module.seller_dashboard',
          [],
          [
            'language' => $language,
          ]
        )->toString()
      );
    }

    // Handle buyer-specific redirection.
    if ($this->currentUser->hasRole('buyer')) {

      // Retrieve all stores assigned to the buyer.
      $stores = $this->buyerStoreResolver
        ->getAllowedStores($this->currentUser);

      $count = count($stores);

      // Buyers must have at least one assigned store.
      if ($count === 0) {
        throw new AccessDeniedHttpException(
          'No stores have been assigned to your account.'
        );
      }

      // Redirect directly when only one store is assigned.
      if ($count === 1) {
        $store = reset($stores);

        return new RedirectResponse(
          $store->toUrl(
            'canonical',
            [
              'language' => $language,
            ]
          )->toString()
        );
      }

      // Redirect buyers to the localized store selector when
      // multiple stores exist.
      return new RedirectResponse(
        Url::fromRoute(
          'view.store_selector.page_1',
          [],
          [
            'language' => $language,
          ]
        )->toString()
      );
    }

    // Redirect all remaining authenticated users to the localized
    // administration dashboard.
    return new RedirectResponse(
      Url::fromUri(
        'internal:/admin',
        [
          'language' => $language,
        ]
      )->toString()
    );
  }

}
