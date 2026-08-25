<?php

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Restricts sellers user to seller specific routes only.
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
   * The entityTypeManager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * Constructs a SellerAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entityTypeManager.
   */
  public function __construct(
    AccountProxyInterface $currentUser,
    RouteMatchInterface $routeMatch,
    EntityTypeManagerInterface $entityTypeManager,
  ) {
    $this->currentUser = $currentUser;
    $this->routeMatch = $routeMatch;
    $this->entityTypeManager = $entityTypeManager;
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
   * Restricts seller users to allowed routes.
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event.
   */
  public function onRequest(RequestEvent $event): void {
    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($this->currentUser->id());

    // Apply restrictions only to seller users.
    if (!$user->hasRole('seller')) {
      return;
    }

    // Get the current route name.
    $route_name = $this->routeMatch->getRouteName();

    // Routes that seller users are allowed to access.
    $allowed = [
      'my_custom_module.seller_dashboard',
      'my_custom_module.generate_code',
      'user.logout',
      'user.logout.confirm',
    ];

    // Redirect to the seller dashboard if the route is not allowed.
    if (!in_array($route_name, $allowed, TRUE)) {
      $event->setResponse(
        new RedirectResponse(Url::fromRoute('my_custom_module.seller_dashboard')->toString())
      );
    }
  }

}
