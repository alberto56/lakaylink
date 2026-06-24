<?php

namespace Drupal\my_custom_module\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class BuyerVerificationForm extends FormBase {

  /**
   * Constructor
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

    $form['#theme'] = 'buyer_verification_form';

    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification code'),
      '#required' => TRUE,
      '#description' => $this->t('Paste the verification code exactly as provided.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

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

    $code = trim($form_state->getValue('verification_code'));

    $parts = explode('/', $code);

    if (count($parts) !== 3) {
      $this->messenger()->addError($this->t('Invalid verification code.'));
      return;
    }

    [$expiry, $store_id, $signature] = $parts;

    if (!ctype_digit($expiry) || !ctype_digit($store_id)) {
      $this->messenger()->addError($this->t('Invalid verification code.'));
      return;
    }

    if ((int) $expiry < time()) {
      $this->messenger()->addError($this->t('Verification code has expired.'));
      return;
    }

    $expected = hash(
      'sha256',
      json_encode([
        (int) $expiry,
        $store_id,
        Settings::get('hash_salt'),
      ])
    );

    if (!hash_equals($expected, $signature)) {
      $this->messenger()->addError($this->t('Invalid verification code.'));
      return;
    }

    $user = $this->userStorage->load($this->currentUser()->id());

    if (!$user) {
      $this->messenger()->addError($this->t('Unable to load account.'));
      return;
    }

    // Optional field if you need store association.
    if ($user->hasField('field_allowed_stores')) {
      $user->set('field_allowed_stores', $store_id);
    }

    $user->removeRole('unverified');
    $user->addRole('buyer');

    $user->save();

    $this->messenger()->addStatus(
      $this->t('Your account has been verified.')
    );

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

      $form_state->setRedirectResponse(
        $store->toUrl()->toString()
      );
    }
    else {

      // Redirect to the store selection page when multiple stores exist.
      // return new RedirectResponse(
      //   Url::fromRoute('view.store_selector.page_1')->toString()
      // );
      $form_state->setRedirectResponse(
        Url::fromRoute('view.store_selector.page_1')->toString()
      );
    }
  }

}