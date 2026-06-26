<?php

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Restricts sellers user to seller-specific routes only.
 */
class SellerAccessSubscriber implements EventSubscriberInterface {

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected AccountProxyInterface $currentUser;

  /**
   * The route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected RouteMatchInterface $routeMatch;

  /**
   * Constructs a SellerAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   */
  public function __construct(
    AccountProxyInterface $currentUser,
    RouteMatchInterface $routeMatch,
  ) {
    $this->currentUser = $currentUser;
    $this->routeMatch = $routeMatch;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      'kernel.request' => ['onRequest', 30],
    ];
  }

  /**
   * Restricts admin users to allowed routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {

    // Apply restrictions only to admin users.
    if (!$this->currentUser->hasRole('admin')) {
      return;
    }

    // Get the current route name.
    $route_name = $this->routeMatch->getRouteName();

    // Routes that admin users are allowed to access.
    $allowed = [
      'my_custom_module.seller_dashboard',
      'my_custom_module.generate_code',
      'user.logout',
    ];

    // Redirect to the seller dashboard if the route is not allowed.
    if (!in_array($route_name, $allowed, TRUE)) {
      $event->setResponse(
        new RedirectResponse('/seller')
      );
    }
  }

}