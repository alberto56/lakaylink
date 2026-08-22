<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Block\BlockManagerInterface;
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
   * Constructor.
   */
  public function __construct(BlockManagerInterface $block_manager) {
    $this->blockManager = $block_manager;
  }

  /**
   * Dependency injection.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.block')
    );
  }

  /**
   * Custom login page.
   */
  public function loginPage() {

    // Create Social Auth Login block.
    $plugin_block = $this->blockManager->createInstance('social_auth_login', []);

    // Build render array.
    $social_login_block = $plugin_block->build();

    return [
      '#theme' => 'custom_login_page',
      '#social_login_block' => $social_login_block,
    ];
  }

}
