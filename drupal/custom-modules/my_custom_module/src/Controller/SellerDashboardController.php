<?php

namespace Drupal\my_custom_module\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link; use Drupal\Core\Url;
use Drupal\commerce_store\Entity\Store;

class SellerDashboardController extends ControllerBase {

  public function dashboard(): array {
    $build = [];
    $account = $this->currentUser();
    $stores = Store::loadMultiple();
    $items = [];
    foreach ($stores as $store) {
      $url = Url::fromRoute(
        'my_custom_module.generate_code',
        [ 'commerce_store' => $store->id(), ] 
      );
      $items[] = [
        '#markup' => '<h3>' . $store->label() . '</h3>' . Link::fromTextAndUrl( 'Generate Invitation Code', $url )->toString(),
      ];
    }
    $build['stores'] = [ '#theme' => 'item_list', '#items' => $items, ];
    return $build;
  }

}