<?php

namespace Drupal\my_custom_module\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribes to route alterations for my_custom_module.
 *
 * Disable user login and user register route. User should login from
 * google signin.
 */
class RouteSubscriber extends RouteSubscriberBase {

  /**
   * Alters existing routes.
   *
   * @param \Symfony\Component\Routing\RouteCollection $collection
   *   The route collection.
   */
  protected function alterRoutes(RouteCollection $collection) {
    // Disable login route.
    if ($route = $collection->get('user.login')) {
      $route->setRequirement('_access', 'FALSE');
    }

    // Disable register route.
    if ($route = $collection->get('user.register')) {
      $route->setRequirement('_access', 'FALSE');
    }

    if ($route = $collection->get('entity.commerce_store.canonical')) {

      $route->setRequirement(
        '_custom_access',
        '\Drupal\my_custom_module\Access\StoreAccess::access'
      );

    }

  }

}
