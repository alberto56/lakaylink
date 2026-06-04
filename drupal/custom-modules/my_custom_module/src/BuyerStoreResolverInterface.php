<?php

declare(strict_types=1);

namespace Drupal\my_custom_module;

use Drupal\Core\Session\AccountInterface;

/**
 * @todo Add interface description.
 */
interface BuyerStoreResolverInterface {

  public function getAllowedStoreIds(AccountInterface $account): array;

  public function getAllowedStores(AccountInterface $account): array;

}
