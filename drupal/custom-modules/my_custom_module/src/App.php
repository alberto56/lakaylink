<?php

namespace Drupal\my_custom_module;

use Drupal\Core\Form\FormStateInterface;
use Drupal\my_custom_module\traits\Environment;
use Drupal\my_custom_module\traits\Singleton;

/**
 * Module-wide functionality.
 */
class App {

  use Singleton;
  use Environment;

  /**
   * Testable implementation of hook_cron().
   */
  public function hookCron() {
    // Just an example of where you'd implement testable hooks.
    $storage = $this->getEntityTypeManager('commerce_store');

    $store_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    if (empty($store_ids)) {
      return;
    }

    $queue = $this->getQueue('my_custom_module_import_queue');

    foreach ($store_ids as $store_id) {
      $queue->createItem($store_id);
    }

    $this->getLogger('my_custom_module')->notice('Queued store imports: @count', [
      '@count' => count($store_ids),
    ]);
  }

  /**
   * Testable implementation of hook_form_alter().
   */
  public function hookFormAlter($form, FormStateInterface $form_state, $form_id) {
    // Match ALL add-to-cart forms (including dynamic ones like product_19)
    if (str_starts_with($form_id, 'commerce_order_item_add_to_cart_form')) {
      $account = $this->getCurrentUser();

      if ($account->isAnonymous()) {
        // Hide entire form.
        $form["actions"] = [];
        // Build destination (redirect back after login).
        $current_path = $this->getService('path.current')->getPath();
        $destination = ['query' => ['destination' => $current_path]];

        // Google login URL (from Social Auth).
        $url = Url::fromUri('internal:/user/login/google', $destination);

        // Create link.
        $link = Link::fromTextAndUrl('Login to buy', $url)->toRenderable();

        // Add button styling.
        $link['#attributes']['class'][] = 'button';
        $link['#attributes']['class'][] = 'button--primary';

        // Inject login link into form.
        $form['login_link'] = [
          '#type' => 'container',
          'link' => $link,
          '#weight' => 100,
        ];
      }
      elseif (!$account->hasRole('buyer')) {
        $form["#access"] = FALSE;
      }
    }
  }

}
