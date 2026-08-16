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
 */
class ForceGoogleLoginSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a new ForceGoogleLoginSubscriber.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
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
   */
  public function onRequest(RequestEvent $event): void {
    $request = $event->getRequest();

    // Allow authenticated users.
    if ($this->currentUser->isAuthenticated()) {
      return;
    }

    $path = $request->getPathInfo();

    // Allow API endpoints.
    if (str_starts_with($path, '/api/')) {
      return;
    }

    /**
     * Determine the language from the URL path.
     *
     * Example:
     * /fr/products  -> fr
     * /de/products  -> de
     * /products     -> default language.
     */
    $language = $this->getLanguageFromPath($path);

    /**
     * Remove the language prefix before checking allowed paths.
     *
     * /fr/custom-login -> /custom-login
     * /de/user/login/google -> /user/login/google
     */
    $path_without_language = $this->removeLanguagePrefix($path, $language);

    // Public routes.
    $allowed_paths = [
      '/custom-login',
      '/user/login/google',
      '/oauth',
      '/user/logout',
    ];

    // Allow password reset login links.
    if (preg_match(
      '#^/user/reset/\d+/.+/login$#',
      $path_without_language
    )) {
      return;
    }

    // Allow public authentication paths.
    foreach ($allowed_paths as $allowed_path) {
      if (
        $path_without_language === $allowed_path ||
        str_starts_with($path_without_language, $allowed_path . '/')
      ) {
        return;
      }
    }

    /**
     * Redirect to the custom login page using the language
     * associated with the current URL.
     */
    $login_url = Url::fromRoute(
      'my_custom_module.custom_login',
      [],
      [
        'language' => $language,
        'absolute' => TRUE,
      ]
    )->toString();

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

      // Exact language path: /fr
      if ($path === $language_prefix) {
        return $language;
      }

      // Language-prefixed path: /fr/anything
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
