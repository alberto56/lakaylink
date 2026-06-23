<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SellerDashboardController extends ControllerBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AccountProxyInterface $currentUser
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function dashboard(): array {
    $account = $this->currentUser();

    $store_storage = $this->entityTypeManager->getStorage('commerce_store');

    $store_ids = $store_storage->getQuery()
      ->condition('uid', $account->id()) // Replace with your ownership field.
      ->accessCheck(TRUE)
      ->execute();

    $stores = $store_storage->loadMultiple($store_ids);

    return [
      '#theme' => 'seller_dashboard',
      '#stores' => $stores,
      '#cache' => [
        'contexts' => ['user'],
      ],
    ];
  }

}