<?php

declare(strict_types=1);

namespace Drupal\my_custom_module;

use Drupal\Core\Session\AccountInterface;

/**
 * Provides methods for resolving stores available to a buyer.
 *
 * Implementations determine which Commerce stores a user is allowed to
 * access based on the store references assigned to their account.
 */
interface BuyerStoreResolverInterface {

  /**
   * Gets the IDs of stores assigned to a buyer.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return int[]
   *   An array of Commerce store entity IDs.
   */
  public function getAllowedStoreIds(AccountInterface $account): array;

  /**
   * Gets the Commerce store entities assigned to a buyer.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface[]
   *   An array of Commerce store entities keyed by store ID.
   */
  public function getAllowedStores(AccountInterface $account): array;

}