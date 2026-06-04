<?php

namespace Drupal\my_custom_module\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\my_custom_module\BuyerStoreResolverInterface;

class StoreAccess {

  public function __construct(
    protected BuyerStoreResolverInterface $resolver,
  ) {}

  public function access(
    StoreInterface $commerce_store,
    AccountInterface $account
  ): AccessResult {

    // Admins bypass store restrictions.
    if (in_array('administrator', $account->getRoles())) {
      return AccessResult::allowed();
    }

    $allowed = $this->resolver->getAllowedStoreIds($account);

    return AccessResult::allowedIf(
      in_array($commerce_store->id(), $allowed)
    )
      ->cachePerUser();
  }

}