<?php

namespace Drupal\my_custom_module\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class StoreAccess implements ContainerInjectionInterface {

  public function __construct(
    protected BuyerStoreResolverInterface $resolver,
  ) {}

  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_custom_module.buyer_store_resolver')
    );
  }

  public function access(
    StoreInterface $commerce_store,
    AccountInterface $account
  ): AccessResult {

    if (in_array('administrator', $account->getRoles(), TRUE)) {
      return AccessResult::allowed();
    }

    $allowed = $this->resolver->getAllowedStoreIds($account);

    return AccessResult::allowedIf(
      in_array($commerce_store->id(), $allowed)
    )->cachePerUser();
  }

}