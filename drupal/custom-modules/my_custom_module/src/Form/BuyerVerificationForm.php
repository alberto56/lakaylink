<?php

namespace Drupal\my_custom_module\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Site\Settings;
use Drupal\user\Entity\User;

class BuyerVerificationForm extends FormBase {

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

    $form['instructions'] = [
      '#markup' => '
        <p>Contact us by WhatsApp at <strong>555-555-5555</strong> to confirm that your family is within our service area.</p>
        <p>We will provide a verification code.</p>
      ',
    ];

    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification code'),
      '#required' => TRUE,
      '#size' => 80,
      '#maxlength' => 255,
      '#description' => $this->t('Paste the verification code exactly as provided.'),
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Verify Account'),
      '#button_type' => 'primary',
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
        (int) $store_id,
        Settings::get('hash_salt'),
      ])
    );

    if (!hash_equals($expected, $signature)) {
      $this->messenger()->addError($this->t('Invalid verification code.'));
      return;
    }

    /** @var \Drupal\user\Entity\User $user */
    $user = User::load($this->currentUser()->id());

    if (!$user) {
      $this->messenger()->addError($this->t('Unable to load account.'));
      return;
    }

    // Optional field if you need store association.
    if ($user->hasField('field_store')) {
      $user->set('field_store', $store_id);
    }

    $user->removeRole('unverified_buyer');
    $user->addRole('buyer');

    $user->save();

    $this->messenger()->addStatus(
      $this->t('Your account has been verified.')
    );

    $form_state->setRedirect('<front>');
  }

}