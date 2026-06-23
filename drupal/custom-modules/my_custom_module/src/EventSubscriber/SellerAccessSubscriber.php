<?php

namespace Drupal\my_custom_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpFoundation\RedirectResponse;

class SellerAccessSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents(): array {
    return [
      'kernel.request' => ['onRequest', 30],
    ];
  }

  public function onRequest(RequestEvent $event): void {

    $user = \Drupal::currentUser();

    if (!$user->hasRole('seller')) {
      return;
    }

    $route_name = \Drupal::routeMatch()->getRouteName();

    $allowed = [
      'my_custom_module.seller_dashboard',
      'my_custom_module.generate_code',
      'user.logout',
    ];

    if (!in_array($route_name, $allowed, TRUE)) {

      $event->setResponse(
        new RedirectResponse('/seller')
      );
    }
  }

}