<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the home page controller for role selection.
 */
class HomeController extends ControllerBase {

  /**
   * Constructs a HomeController object.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $moduleLanguageManager
   *   The language manager.
   */
  public function __construct(
    private readonly LanguageManagerInterface $moduleLanguageManager,
  ) {}

  /**
   * Creates a HomeController instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A HomeController instance.
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('language_manager'),
    );
  }

  /**
   * Builds the home page with buyer and seller navigation options.
   *
   * Creates a list of links that allow users to continue as either a buyer
   * or a seller. The links are rendered using the
   * 'home_role_selector' theme hook.
   *
   * Here we are handling two cases.
   *
   * 1. when user registered or logged in first time, we are not sure
   *   whether user is a seller or buyer.
   * 2. If User has both the roles seller and buyer.
   *
   * We are listing links /home/seller and /buyer-login-redirect.
   * User can navigate to seller or buyer screens by clicking on those.
   *
   * @return array
   *   A render array for the role selection page.
   */
  public function home(): array {
    // Get the language associated with the current request.
    $language = $this->moduleLanguageManager->getCurrentLanguage();

    // Initialize the links array.
    $links = [];

    // Add the Buyer navigation link.
    $links[] = [
      'title' => $this->t('Continue as Buyer'),
      'url' => Url::fromRoute(
        'my_custom_module.buyer_login_redirect',
        [],
        [
          'language' => $language,
        ]
      ),
    ];

    // Add the Seller navigation link.
    $links[] = [
      'title' => $this->t('Continue as Seller'),
      'url' => Url::fromRoute(
        'my_custom_module.seller_dashboard',
        [],
        [
          'language' => $language,
        ]
      ),
    ];

    // Return the render array using the custom theme hook.
    return [
      '#theme' => 'home_role_selector',
      '#links' => $links,
    ];
  }

}
