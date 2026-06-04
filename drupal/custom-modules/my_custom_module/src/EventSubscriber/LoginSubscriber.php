<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSubscriber implements EventSubscriberInterface {

  public function __construct(
    protected BuyerStoreResolverInterface $buyerStoreResolver,
    protected UrlGeneratorInterface $urlGenerator,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      LoginSuccessEvent::class => 'onLoginSuccess',
    ];
  }

  /**
   * Redirect users based on allowed stores.
   */
  public function onLoginSuccess(LoginSuccessEvent $event): void {

    $user = $event->getUser();

    // Ignore anonymous or non-Drupal users.
    if (!method_exists($user, 'id')) {
      return;
    }

    $stores = $this->buyerStoreResolver->getAllowedStores($user);

    $count = count($stores);

    // No stores assigned.
    if ($count === 0) {
      throw new AccessDeniedHttpException(
        'No stores have been assigned to your account.'
      );
    }

    // Single store -> redirect directly.
    if ($count === 1) {

      $store = reset($stores);

      $event->setResponse(
        new RedirectResponse(
          $store->toUrl()->toString()
        )
      );

      return;
    }

    // Multiple stores -> selector page.
    $event->setResponse(
      new RedirectResponse(
        $this->urlGenerator->generate('view.store_selector.page_1')
      )
    );

  }

}