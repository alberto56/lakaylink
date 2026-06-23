<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class SellerDashboardController extends ControllerBase {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    AccountProxyInterface $current_user,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
  }

  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_user'),
    );
  }

  public function dashboard(): array {

    $account = $this->currentUser;

    $store_storage = $this->entityTypeManager->getStorage('commerce_store');

    $store_ids = $store_storage->getQuery()
      ->condition('uid', $account->id()) // change if your ownership field differs
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