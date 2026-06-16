<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides access control for Commerce store entities.
 *
 * Restricts access to stores based on the store assignments configured
 * for buyer users. Administrators are granted unrestricted access.
 */
final class StoreAccess implements ContainerInjectionInterface {

  /**
   * Constructs a StoreAccess object.
   *
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $resolver
   *   The buyer store resolver service.
   */
  public function __construct(
    protected BuyerStoreResolverInterface $resolver,
  ) {}

  /**
   * Creates an instance from the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new StoreAccess instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('my_custom_module.buyer_store_resolver')
    );
  }

  /**
   * Checks whether a user may access a Commerce store.
   *
   * Administrators are always granted access. Other users may access
   * only stores that have been assigned to them through the
   * field_allowed_stores user field.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $commerce_store
   *   The Commerce store being accessed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account requesting access.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result.
   */
  public function access(
    StoreInterface $commerce_store,
    AccountInterface $account,
  ): AccessResult {

    // Administrators bypass store assignment restrictions.
    if (in_array('administrator', $account->getRoles(), TRUE)) {
      return AccessResult::allowed();
    }

    // Get the list of stores assigned to the current user.
    $allowed = $this->resolver->getAllowedStoreIds($account);

    // Grant access only when the store is assigned to the user.
    return AccessResult::allowedIf(
      in_array($commerce_store->id(), $allowed, TRUE)
    )->cachePerUser();
  }

}
