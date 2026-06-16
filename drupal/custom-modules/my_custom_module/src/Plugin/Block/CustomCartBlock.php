<?php

namespace Drupal\my_custom_module\Plugin\Block;

use Drupal\commerce_cart\Plugin\Block\CartBlock;

/**
 * Provides a custom commerce cart block.
 *
 * @Block(
 *   id = "custom_commerce_cart",
 *   admin_label = @Translation("Custom Commerce Cart"),
 *   category = @Translation("Commerce")
 * )
 */
class CustomCartBlock extends CartBlock {

  /**
   * The cart total.
   *
   * @var float
   */
  protected $cartTotal;

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = parent::build();
  
    foreach ($this->cartProvider->getCarts() as $cart) {
      if ($cart->hasItems() && $cart->cart->value) {
        $this->cartTotal = $cart->getTotalPrice();
        $build['#cart_total'] = $this->getCartTotalText();
        break;
      }
    }

    return $build;
  }

  /**
   * Gets the total price as a formatted string.
   *
   * @return mixed|null
   */
  protected function getCartTotalText() {
    $element = [];
    $element = [
      '#type' => 'inline_template',
      '#template' => '{{ price|commerce_price_format }}',
      '#context' => [
        'price' =>  $this->cartTotal,
      ],
    ];

    return $element;
  }

}
