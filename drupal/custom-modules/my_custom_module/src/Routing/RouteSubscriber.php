<?php

namespace Drupal\my_custom_module\Routing;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

/**
 * Subscribes to route alterations for my_custom_module.
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
  }

}
