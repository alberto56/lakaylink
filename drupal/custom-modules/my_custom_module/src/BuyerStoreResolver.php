<?php

declare(strict_types=1);

namespace Drupal\my_custom_module;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Resolves store assignments for buyer accounts.
 *
 * Reads the values stored in the field_allowed_stores user field and
 * returns the corresponding store IDs or loaded Commerce store entities.
 */
final class BuyerStoreResolver implements BuyerStoreResolverInterface {

  /**
   * Constructs a BuyerStoreResolver object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager service.
   */
  public function __construct(
    private readonly EntityTypeManagerInterface $entityTypeManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getAllowedStoreIds(AccountInterface $account): array {

    $user = $this->entityTypeManager
      ->getStorage('user')
      ->load($account->id());

    // Return an empty array if the user cannot be loaded or does not
    // contain the allowed stores field.
    if (!$user || !$user->hasField('field_allowed_stores')) {
      return [];
    }

    return array_column(
      $user->get('field_allowed_stores')->getValue(),
      'target_id'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getAllowedStores(AccountInterface $account): array {

    $ids = $this->getAllowedStoreIds($account);

    // No assigned stores.
    if (!$ids) {
      return [];
    }

    return $this->entityTypeManager
      ->getStorage('commerce_store')
      ->loadMultiple($ids);
  }

}
