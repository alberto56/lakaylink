<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Restricts sellers user to seller specific routes only.
 */
class SellerAccessSubscriber implements EventSubscriberInterface {

  /**
   * Constructs a SellerAccessSubscriber object.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user service.
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The route match service.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    protected AccountProxyInterface $currentUser,
    protected RouteMatchInterface $routeMatch,
    protected LanguageManagerInterface $languageManager,
  ) {}

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
    // Apply restrictions only to seller users.
    if (!$this->currentUser->hasRole('seller')) {
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

    // Get the language associated with the current request.
    $language = $this->languageManager->getCurrentLanguage();

    // Generate the seller dashboard URL using the current language.
    $seller_dashboard_url = Url::fromRoute(
      'my_custom_module.seller_dashboard',
      [],
      [
        'language' => $language,
      ]
    )->toString();

    // Redirect to the seller dashboard if the route is not allowed.
    if (!in_array($route_name, $allowed, TRUE)) {
      // Redirect to the localized seller dashboard.
      $event->setResponse(
        new RedirectResponse($seller_dashboard_url)
      );
    }
  }

}
