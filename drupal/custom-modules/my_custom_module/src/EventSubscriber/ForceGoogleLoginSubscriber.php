<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Forces anonymous users through Google authentication.
 */
final class ForceGoogleLoginSubscriber implements EventSubscriberInterface {

  /**
   * Constructor.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly array $allowedRoutes,
  ) {}

  /**
   * Handles request events.
   */
  public function onRequest(RequestEvent $event): void {

    if (!$event->isMainRequest()) {
      return;
    }

    if (!$this->currentUser->isAnonymous()) {
      return;
    }

    $request = $event->getRequest();

    $routeName = $request->attributes->get('_route');

    if ($routeName && in_array($routeName, $this->allowedRoutes, TRUE)) {
      return;
    }

    $event->setResponse(
      new RedirectResponse('/user/login/google')
    );
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

}