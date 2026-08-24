<?php

declare(strict_types=1);

/**
 * @file
 * Contains the UserLoginRedirectController class.
 */

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Handles user login redirection based on assigned roles.
 */
class UserLoginRedirectController extends ControllerBase {

  /**
   * Constructs a UserLoginRedirectController object.
   *
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyerStoreResolver
   *   The buyer store resolver service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Symfony\Component\HttpFoundation\RequestStack $requestStack
   *   The request stack.
   */
  public function __construct(
    private readonly BuyerStoreResolverInterface $buyerStoreResolver,
    private readonly LanguageManagerInterface $languageManager,
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

    // Get the language that was stored before Social Auth redirected
    // the user to Google.
    $language = $this->getSocialAuthLanguage();

    // Anonymous users.
    if ($user->isAnonymous()) {
      return new RedirectResponse(
        $this->localizedUrl(
          'my_custom_module.custom_login',
          [],
          $language
        )
      );
    }

    // Exclude Drupal's default authenticated role.
    $roles = array_diff(
      $user->getRoles(),
      ['authenticated']
    );

    // Users who are only unverified, or users who have both buyer and
    // seller roles, go to the home/role selection page.
    if (
      ($user->hasRole('unverified') && count($roles) === 1)
      || (
        $user->hasRole('buyer')
        && $user->hasRole('seller')
      )
    ) {
      return new RedirectResponse(
        $this->localizedUrl(
          'my_custom_module.home',
          [],
          $language
        )
      );
    }

    // Sellers go to the seller dashboard.
    if ($user->hasRole('seller')) {
      return new RedirectResponse(
        $this->localizedUrl(
          'my_custom_module.seller_dashboard',
          [],
          $language
        )
      );
    }

    // Buyers.
    if ($user->hasRole('buyer')) {
      // Get all stores assigned to the buyer.
      $stores = $this->buyerStoreResolver->getAllowedStores($user);

      $count = count($stores);

      // Buyers must have at least one assigned store.
      if ($count === 0) {
        throw new AccessDeniedHttpException(
          'No stores have been assigned to your account.'
        );
      }

      // One store: go directly to it.
      if ($count === 1) {
        $store = reset($stores);

        return new RedirectResponse(
          $store->toUrl()->toString()
        );
      }

      // Multiple stores: show the store selector.
      return new RedirectResponse(
        $this->localizedUrl(
          'view.store_selector.page_1',
          [],
          $language
        )
      );
    }

    // All other authenticated users.
    return new RedirectResponse(
      $this->localizedUrl(
        'system.admin',
        [],
        $language
      )
    );
  }

  /**
   * Gets the language stored during Social Auth.
   *
   * @return \Drupal\Core\Language\LanguageInterface|null
   *   The language, or NULL if none was stored.
   */
  private function getSocialAuthLanguage(): ?LanguageInterface {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request->hasSession()) {
      return NULL;
    }

    $session = $request->getSession();

    $language_code = $session->get('social_auth_language');

    if (!$language_code) {
      return NULL;
    }

    return $this->languageManager->getLanguage($language_code);
  }

  /**
   * Builds a localized URL.
   *
   * @param string $routeName
   *   The route name.
   * @param array $parameters
   *   Route parameters.
   * @param \Drupal\Core\Language\LanguageInterface|null $language
   *   The language to use.
   *
   * @return string
   *   The generated URL.
   */
  private function localizedUrl(
    string $routeName,
    array $parameters = [],
    ?LanguageInterface $language = NULL,
  ): string {
    $options = [];

    if ($language !== NULL) {
      $options['language'] = $language;
    }

    return Url::fromRoute(
      $routeName,
      $parameters,
      $options
    )->toString();
  }

}
