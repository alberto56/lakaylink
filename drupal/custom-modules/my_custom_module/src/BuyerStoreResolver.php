<?php

declare(strict_types=1);

namespace Drupal\my_custom_module;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * @todo Add class description.
 */
final class BuyerStoreResolver implements BuyerStoreResolverInterface {

  /**
   * Constructs a BuyerStoreResolver object.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  public function getAllowedStoreIds(AccountInterface $account): array {

    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($account->id());

    if (!$user || !$user->hasField('field_allowed_stores')) {
      return [];
    }

    return array_column(
      $user->get('field_allowed_stores')->getValue(),
      'target_id'
    );
  }

  public function getAllowedStores(AccountInterface $account): array {

    $ids = $this->getAllowedStoreIds($account);

    if (!$ids) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('commerce_store')
      ->loadMultiple($ids);
  }

}
