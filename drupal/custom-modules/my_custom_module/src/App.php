<?php

namespace Drupal\my_custom_module;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
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
  public function hookFormAlter(&$form, FormStateInterface $form_state, $form_id) {

    // Match ALL add-to-cart forms (including dynamic ones like product_19)
    if (str_starts_with($form_id, 'commerce_order_item_add_to_cart_form_commerce_product')) {
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

  /**
   * Testable implementation of hook_entity_field_access().
   *
   * Controls access to the buyer store assignment field.
   *
   * Restricts editing of the field_allowed_stores field to users who have
   * the "manage buyer store assignments" permission. All other field access
   * operations are left unchanged.
   *
   * @param string $operation
   *   The operation being performed ('view' or 'edit').
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The field definition being accessed.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The user account requesting access.
   * @param \Drupal\Core\Field\FieldItemListInterface|null $items
   *   (optional) The field values.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The access result for the requested operation.
   *
   * @see hook_entity_field_access()
   */
  public function hookEntityFieldAccess(
    $operation,
    FieldDefinitionInterface $field_definition,
    AccountInterface $account,
    ?FieldItemListInterface $items = NULL,
  ) {
    if ($field_definition->getName() !== 'field_allowed_stores') {
      return AccessResult::neutral();
    }

    if ($operation === 'edit') {
      return $account->hasPermission('manage buyer store assignments')
        ? AccessResult::allowed()
        : AccessResult::forbidden();
    }

    return AccessResult::neutral();
  }

  /**
   * Testable implementation of hook_theme().
   *
   * Registers theme hooks provided by the module.
   *
   * Defines the custom login page theme implementation used to render
   * the Google authentication login screen.
   *
   * @return array
   *   An associative array containing theme hook definitions.
   *
   * @see hook_theme()
   */
  public function hookTheme() {
    return [
      'custom_login_page' => [
        'variables' => [
          'social_login_block' => NULL,
        ],
        'template' => 'custom-login-page',
      ],
    ];
  }

}
