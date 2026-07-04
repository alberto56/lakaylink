<?php

/**
 * @file
 * Contains the HomeController class.
 *
 * Here we are handling two cases.
 *
 * 1. when user registered or logged in first time, we are not sure
 *   whether he is a seller or buyer.
 * 2. If User has both the roles seller and buyer.
 *
 * We are listing links /home/seller and /buyer-login-redirect.
 *
 * On clicking those he can navigate to seller or buyer screens.
 *
 */

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Provides the home page controller for role selection.
 */
class HomeController extends ControllerBase {

  /**
   * Builds the home page with buyer and seller navigation options.
   *
   * Creates a list of links that allow users to continue as either a buyer
   * or a seller. The links are rendered using the
   * 'home_role_selector' theme hook.
   *
   * @return array
   *   A render array for the role selection page.
   */
  public function home() {
    // Get the currently logged-in user.
    $user = $this->currentUser();

    // Initialize the links array.
    $links = [];

    // Add the Buyer navigation link.
    $links[] = [
      'title' => $this->t('Continue as Buyer'),
      'url' => Url::fromRoute('my_custom_module.buyer_login_redirect'),
    ];

    // Add the Seller navigation link.
    $links[] = [
      'title' => $this->t('Continue as Seller'),
      'url' => Url::fromRoute('my_custom_module.seller_dashboard'),
    ];

    // Return the render array using the custom theme hook.
    return [
      '#theme' => 'home_role_selector',
      '#links' => $links,
    ];
  }

}
