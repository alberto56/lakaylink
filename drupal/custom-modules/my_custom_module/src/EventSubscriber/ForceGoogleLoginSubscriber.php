<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

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
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
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

    // Routes that should remain accessible to anonymous users.
    $allowed_paths = [
      '/custom-login',
      '/user/login/google',
      '/oauth',
      '/user/logout',
    ];

    // Allow password reset login links.
    if (preg_match('#^/user/reset/\d+/.+/login$#', $path)) {
      return;
    }

    // Allow requests matching any configured path prefix.
    foreach ($allowed_paths as $prefix) {
      if (str_starts_with($path, $prefix)) {
        return;
      }
    }

    // Redirect all other anonymous requests to the custom login page.
    $event->setResponse(
      new RedirectResponse('/custom-login')
    );
  }

}
