<?php

namespace Drupal\my_custom_module;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\commerce_store\Entity\Store;
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
   *
   * Queues a grocery import job for each Commerce store so that
   * the imports can be processed asynchronously by the queue worker.
   */
  public function hookCron() {

    // Get the Commerce Store entity storage handler.
    $storage = $this->getEntityTypeManager('commerce_store');

    // Retrieve the IDs of all Commerce stores.
    // Access checks are disabled because the cron process should
    // process imports for all stores regardless of the current user.
    $store_ids = $storage->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    // Stop processing if there are no stores to import.
    if (empty($store_ids)) {
      return;
    }

    // Get the queue responsible for processing grocery imports.
    $queue = $this->getQueue('my_custom_module_import_queue');

    // Add one queue item for each Commerce store.
    // The store ID is passed to the queue worker for processing.
    foreach ($store_ids as $store_id) {
      $queue->createItem($store_id);
    }

    // Log the number of store imports that were added to the queue.
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
    if ($operation !== 'edit') {
      return AccessResult::neutral();
    }

    $field_permissions = [
      'field_allowed_stores' => 'manage buyer store assignments',
      'field_verified' => 'update user verification',
    ];

    $field_name = $field_definition->getName();

    if (!isset($field_permissions[$field_name])) {
      return AccessResult::neutral();
    }

    return $account->hasPermission($field_permissions[$field_name])
      ? AccessResult::allowed()
      : AccessResult::forbidden();
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
          'google_login_url' => NULL,
        ],
        'template' => 'custom-login-page',
      ],
      'buyer_verification_form' => [
        'render element' => 'form',
        'template' => 'buyer-verification-form',
      ],
      'seller_dashboard' => [
        'variables' => [
          'stores' => [],
        ],
        'template' => 'seller-dashboard',
      ],
      'home_role_selector' => [
        'variables' => [
          'links' => [],
        ],
        'template' => 'home-role-selector',
      ],
    ];
  }

  /**
   * Preprocesses variables for the main menu.
   *
   * Updates the Home and Shop menu links to use the current store context.
   *
   * @param array $variables
   *   The variables passed to the menu template.
   */
  public function hookPreprocessMenuMain(array &$variables) {
    // Get the current store information.
    $store_id = $this->getService('my_custom_module.current_store')->getStoreId();
    $store_slug = $this->getService('my_custom_module.current_store')->getStoreSlug();

    // Stop processing if no active store is available.
    if (!$store_id || !$store_slug) {
      return;
    }

    // Update menu links with store-specific URLs.
    foreach ($variables['items'] as &$item) {

      // Update the Home menu link.
      if ($item['title'] === 'Home') {
        $item['url'] = Url::fromUserInput("/shop/$store_slug/$store_id");
      }

      // Update the Shop menu link.
      if ($item['title'] === 'Shop') {
        $item['url'] = Url::fromUserInput("/store/$store_id/shop");
      }
    }
  }

  /**
   * Preprocesses variables for the commerce store template.
   *
   * Loads nodes related to the current store and exposes them to the template.
   *
   * @param array $variables
   *   The template variables.
   */
  public function hookPreprocessCommerceStore(array &$variables) {
    // Get the current commerce store from the route.
    $store = $this->getRouteMatch()->getParameter('commerce_store');

    // Continue only if a valid store entity exists.
    if (!$store instanceof Store) {
      return;
    }

    // Get the node storage handler.
    $nodeStorage = $this->getEntityTypeManager('node');

    // Find nodes referencing the current store.
    $nids = $nodeStorage->getQuery()
      ->condition('field_store_reference.target_id', $store->id())
      ->accessCheck(FALSE)
      ->execute();

    $nodes = Node::loadMultiple($nids);

    $aliasManager = $this->getService('path_alias.manager');

    foreach ($nodes as $node) {
      $node->alias = $aliasManager->getAliasByPath('/node/' . $node->id());
    }

    $variables['related_nodes'] = $nodes;
  }

}
