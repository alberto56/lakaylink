<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class BuyerLoginRedirectController extends ControllerBase {

  public function __construct(
    private readonly BuyerStoreResolverInterface $buyerStoreResolver,
    private readonly UrlGeneratorInterface $urlGenerator,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_custom_module.buyer_store_resolver'),
      $container->get('url_generator')
    );
  }

  public function landing(): RedirectResponse {

    // Anonymous users.
    if ($this->currentUser()->isAnonymous()) {
      return new RedirectResponse(
        Url::fromRoute('my_custom_module.custom_login')->toString()
      );
    }

    // Buyers.
    if ($this->currentUser->hasRole('buyer')) {

      $stores = $this->buyerStoreResolver
        ->getAllowedStores($this->currentUser);

      $count = count($stores);

      if ($count === 0) {
        throw new AccessDeniedHttpException(
          'No stores have been assigned to your account.'
        );
      }

      if ($count === 1) {
        $store = reset($stores);

        return new RedirectResponse(
          $store->toUrl()->toString()
        );
      }

      return new RedirectResponse(
        $this->urlGenerator->generate('view.store_selector.page_1')
      );
    }

    // Other authenticated users.
    return new RedirectResponse('/admin');
  }

}