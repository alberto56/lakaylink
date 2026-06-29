<?php

namespace Drupal\my_custom_module\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

class StoreContextSubscriber implements EventSubscriberInterface {

  public function __construct(private $requestStack) {}

  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();
    $session = $request->getSession();

    $path = $request->getPathInfo();

    // Match: /shop/{slug}/{id}
    if (preg_match('#^/shop/([a-z0-9\-]+)/(\d+)$#', $path, $matches)) {
      $slug = $matches[1];
      $id = $matches[2];

      $session->set('active_store_slug', $slug);
      $session->set('active_store_id', (int) $id);
    }
  }

  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }
}