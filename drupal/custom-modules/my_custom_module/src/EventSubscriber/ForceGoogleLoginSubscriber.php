<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces anonymous users through Google authentication.
 */
class ForceGoogleLoginSubscriber implements EventSubscriberInterface {

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 300],
    ];
  }

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();

    if (\Drupal::currentUser()->isAuthenticated()) {
      return;
    }

    $path = $request->getPathInfo();

    if (str_starts_with($path, '/api/')) {
      return;
    }

    $allowed = [
      '/custom-login',
      '/user/login/google',
      '/oauth',
      '/user/logout',
    ];

    foreach ($allowed as $prefix) {
      if (str_starts_with($path, $prefix)) {
        return;
      }
    }

    $event->setResponse(
      new RedirectResponse('/custom-login')
    );
  }
}
