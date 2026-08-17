<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Block\BlockManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Render google social auth login page.
 */
final class LoginController extends ControllerBase {

  /**
   * Block manager.
   *
   * @var \Drupal\Core\Block\BlockManagerInterface
   */
  protected $blockManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructs the controller.
   *
   * @param \Drupal\Core\Block\BlockManagerInterface $block_manager
   *   The block manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(
    BlockManagerInterface $block_manager,
    LanguageManagerInterface $language_manager,
  ) {
    $this->blockManager = $block_manager;
    $this->languageManager = $language_manager;
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block'),
      $container->get('language_manager'),
    );
  }

  /**
   * Custom login page.
   */
  public function loginPage() {
    $language = $this->languageManager->getCurrentLanguage();

    // Google Social Auth login endpoint.
    $google_login_url = Url::fromUserInput(
      '/user/login/google',
      [
        'query' => [
          'language' => $language->getId(),
        ],
      ]
    )->toString();

    return [
      '#theme' => 'custom_login_page',
      '#google_login_url' => $google_login_url,
    ];
  }

}
