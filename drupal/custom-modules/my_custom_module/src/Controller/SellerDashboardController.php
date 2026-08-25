<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Component\Render\FormattableMarkup;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the seller dashboard page and store listing.
 */
final class SellerDashboardController extends ControllerBase {

  /**
   * The buyer store resolver service.
   *
   * @var \Drupal\my_custom_module\BuyerStoreResolverInterface
   */
  protected BuyerStoreResolverInterface $buyerStoreResolver;

  /**
   * Constructs a SellerDashboardController object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The current user account proxy.
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyer_store_resolver
   *   Service that resolves stores assigned to a user.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
    BuyerStoreResolverInterface $buyer_store_resolver,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->buyerStoreResolver = $buyer_store_resolver;
  }

  /**
   * Creates an instance of the controller.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new controller instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('my_custom_module.buyer_store_resolver'),
    );
  }

  /**
   * Builds the seller dashboard page.
   *
   * Displays a list of stores accessible to the current seller or admin.
   * If the user has no assigned stores, a warning message is shown instead.
   *
   * @return array
   *   A render array for the seller dashboard or an access/error message.
   *
   * @throws \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException
   *   Thrown when the user does not have access to the dashboard.
   */
  public function dashboard(): array {

    // Get current user.
    $currentUser = $this->currentUser;
    $user = $this->entityTypeManager()
      ->getStorage('user')
      ->load($currentUser->id());

    if ($user === NULL) {
      throw new AccessDeniedHttpException();
    }

    // Allow only sellers and administrators.
    if ($user->hasRole('seller') || $user->hasRole('administrator')) {

      // Load commerce store storage.
      $store_storage = $this->entityTypeManager->getStorage('commerce_store');

      $storeQuery = $store_storage->getQuery()->accessCheck(TRUE);

      // Restrict sellers to only their assigned stores.
      if ($user->hasRole('seller')) {

        // Get stores assigned to the seller.
        $allowed_stores = $this->buyerStoreResolver
          ->getAllowedStores($currentUser);

        $store_ids = [];

        foreach ($allowed_stores as $store) {
          $store_ids[] = $store->id();
        }

        // Show warning if seller has no stores assigned.
        if (empty($store_ids)) {
          return [
            '#markup' => new FormattableMarkup(
              '<div class="alert alert-warning" role="alert">@message</div>',
              [
                '@message' => $this->t('Ask administrator to associate you with at least one store.'),
              ]
            ),
          ];
        }

        // Filter stores by allowed IDs.
        $storeQuery->condition('store_id', $store_ids, 'IN');
      }

      // Execute query and load store entities.
      $store_ids = $storeQuery->execute();
      $stores = $store_storage->loadMultiple($store_ids);

      // Render seller dashboard.
      return [
        '#theme' => 'seller_dashboard',
        '#stores' => $stores,
        '#attached' => [
          'library' => [
            'core/drupal.ajax',
            'my_custom_module/invitation_code',
          ],
        ],
        '#cache' => [
          'contexts' => ['user'],
        ],
      ];
    }

    // Handle unverified users.
    elseif ($user->hasRole('unverified')) {
      return [
        '#markup' => new FormattableMarkup(
          '<div class="alert alert-warning" role="alert">@message</div>',
          [
            '@message' => $this->t('Ask an administrator to provide seller access and associate you with at least one store.'),
          ]
        ),
      ];
    }

    // Deny access for all other users.
    throw new AccessDeniedHttpException();
  }

}
