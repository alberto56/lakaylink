<?php

namespace Drupal\my_custom_module\Form;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Provides the buyer verification form.
 *
 * Allows users to verify their account using a verification code
 * generated for a specific store assignment.
 */
class BuyerVerificationForm extends FormBase {

  /**
   * Constructs a BuyerVerificationForm object.
   *
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyerStoreResolver
   *   Resolves stores available to a buyer.
   * @param \Drupal\Core\Entity\EntityStorageInterface $userStorage
   *   The user entity storage.
   */
  public function __construct(
    private readonly BuyerStoreResolverInterface $buyerStoreResolver,
    private readonly EntityStorageInterface $userStorage,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('my_custom_module.buyer_store_resolver'),
      $container->get('entity_type.manager')->getStorage('user'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'buyer_verification_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    // Use a custom Twig template for rendering the form.
    $form['#theme'] = 'buyer_verification_form';

    // Verification code field.
    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification code'),
      '#required' => TRUE,
      '#description' => $this->t('Paste the verification code exactly as provided.'),
    ];

    // Form actions container.
    $form['actions'] = [
      '#type' => 'actions',
    ];

    // Submit button.
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify Account'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    // Get and normalize the submitted verification code.
    $code = trim($form_state->getValue('verification_code'));

    // Expected format:
    // expiry/store_id/signature.
    $parts = explode('/', $code);

    // Ensure the code contains exactly three segments.
    if (count($parts) !== 3) {
      $this->messenger()->addError(
        $this->t('Invalid verification code.')
      );
      return;
    }

    // Extract the code components.
    [$expiry, $store_id, $signature] = $parts;

    // Validate numeric values.
    if (!ctype_digit($expiry) || !ctype_digit($store_id)) {
      $this->messenger()->addError(
        $this->t('Invalid verification code.')
      );
      return;
    }

    // Ensure the verification code has not expired.
    if ((int) $expiry < time()) {
      $this->messenger()->addError(
        $this->t('Verification code has expired.')
      );
      return;
    }

    // Generate the expected signature.
    $expected = hash(
      'sha256',
      Json::encode([
        (int) $expiry,
        $store_id,
        Settings::get('hash_salt'),
      ])
    );

    // Verify the submitted signature.
    if (!hash_equals($expected, $signature)) {
      $this->messenger()->addError(
        $this->t('Invalid verification code.')
      );
      return;
    }

    // Load the current user account.
    $user = $this->userStorage->load(
      $this->currentUser()->id()
    );

    if (!$user) {
      $this->messenger()->addError(
        $this->t('Unable to load account.')
      );
      return;
    }

    // Associate the verified store with the user account.
    if ($user->hasField('field_allowed_stores')) {
      $user->set('field_allowed_stores', $store_id);
    }

    // Update user roles after successful verification.
    $user->removeRole('unverified');
    $user->addRole('buyer');

    // Save account changes.
    $user->save();

    // Display success message.
    $this->messenger()->addStatus(
      $this->t('Your account has been verified.')
    );

    // Determine which stores the buyer can access.
    $stores = $this->buyerStoreResolver
      ->getAllowedStores($this->currentUser());

    $count = count($stores);

    // Buyers must have at least one assigned store.
    if ($count === 0) {
      throw new AccessDeniedHttpException(
        'No stores have been assigned to your account.'
      );
    }

    // Redirect directly when only one store is available.
    if ($count === 1) {
      $store = reset($stores);

      $form_state->setRedirectUrl(
        $store->toUrl()
      );
      return;
    }

    // Redirect to the store selector when multiple stores exist.
    $form_state->setRedirect(
      'view.store_selector.page_1'
    );
  }

}