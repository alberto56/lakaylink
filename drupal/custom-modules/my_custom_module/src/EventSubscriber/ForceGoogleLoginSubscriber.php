<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Redirects anonymous users to the custom Google login page.
 *
 * This subscriber intercepts incoming requests before controller execution
 * and ensures that anonymous users are redirected to the custom login page.
 *
 * The following paths are excluded from the redirect:
 * - Custom login page.
 * - Google OAuth authentication routes.
 * - Logout route.
 * - Password reset login links.
 * - API endpoints.
 */
class ForceGoogleLoginSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ForceGoogleLoginSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The currently logged-in user account.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 300],
    ];
  }

  /**
   * Handles the kernel request event.
   *
   * Redirects anonymous users to the custom login page unless the request
   * targets an excluded path.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();

    // Skip processing for authenticated users.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $path = $request->getPathInfo();

    // Allow API endpoints to bypass authentication redirects.
    if (str_starts_with($path, '/api/')) {
      return;
    }

    // Determine the language from the URL path.
    // Example:
    // /fr/products  -> fr
    // /de/products  -> de
    // /products     -> default language.
    $language = $this->getLanguageFromPath($path);

    // Remove the language prefix before checking allowed paths.
    // /fr/custom-login -> /custom-login.
    // /de/user/login/google -> /user/login/google.
    $path_without_language = $this->removeLanguagePrefix($path, $language);

    // Routes that should remain accessible to anonymous users.
    $allowed_paths = [
      '/custom-login',
      '/user/login/google',
      '/oauth',
      '/user/logout',
      '/test-user-login',
    ];

    // Allow password reset login links.
    if (preg_match(
      '#^/user/reset/\d+/.+/login$#',
      $path_without_language
    )) {
      return;
    }

    // Allow requests matching any configured path prefix.
    if (in_array($path_without_language, $allowed_paths, TRUE)) {
      return;
    }

    /*
     * Routes that should remain accessible to anonymous users.
     *
     * Redirect to the custom login page using the language
     *  associated with the current URL.
     */
    $login_url = Url::fromRoute(
      'my_custom_module.custom_login',
      [],
      [
        'language' => $language,
        'absolute' => TRUE,
      ]
    )->toString();

    // Redirect all other anonymous requests to the custom login page.
    $event->setResponse(
      new RedirectResponse($login_url)
    );
  }

  /**
   * Gets the enabled language associated with the current path.
   *
   * Example:
   *
   * /fr/products -> French language object.
   * /de/products -> German language object.
   * /products    -> Default language object.
   */
  private function getLanguageFromPath(string $path) {
    $languages = $this->languageManager->getLanguages();

    // Check enabled languages against the URL prefix.
    foreach ($languages as $language) {
      $prefix = $language->getId();

      // Skip empty language IDs.
      if ($prefix === '') {
        continue;
      }

      $language_prefix = '/' . $prefix;

      // Exact language path: /fr.
      if ($path === $language_prefix) {
        return $language;
      }

      // Language-prefixed path: /fr/anything.
      if (str_starts_with($path, $language_prefix . '/')) {
        return $language;
      }
    }

    // No language prefix in URL.
    return $this->languageManager->getDefaultLanguage();
  }

  /**
   * Removes the language prefix from a path.
   *
   * Example:
   *
   * /fr/custom-login -> /custom-login
   * /de/custom-login -> /custom-login
   * /custom-login    -> /custom-login
   */
  private function removeLanguagePrefix(string $path, $language): string {
    $prefix = '/' . $language->getId();

    if (
      $language->getId() !== '' &&
      (
        $path === $prefix ||
        str_starts_with($path, $prefix . '/')
      )
    ) {
      $path = substr($path, strlen($prefix));
    }

    return $path ?: '/';
  }

}
