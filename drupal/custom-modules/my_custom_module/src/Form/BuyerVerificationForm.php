<?php

namespace Drupal\my_custom_module\Form;

use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\my_custom_module\Service\InvitationCodeGenerator;
use Drupal\my_custom_module\Service\BuyerVerificationManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a buyer verification form.
 *
 * This form allows users to submit a verification code,
 * validates it, and then marks the current user as verified
 * for a specific store context.
 */
class BuyerVerificationForm extends FormBase {

  /**
   * Constructs a new BuyerVerificationForm instance.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   Currently logged in user.
   * @param \Drupal\my_custom_module\Service\InvitationCodeGenerator $verificationService
   *   Service responsible for validating verification codes.
   * @param \Drupal\my_custom_module\Service\BuyerVerificationManager $buyerVerificationManager
   *   Service responsible for applying buyer verification logic.
   */
  public function __construct(
    private readonly AccountProxyInterface $currentUser,
    private readonly InvitationCodeGenerator $verificationService,
    private readonly BuyerVerificationManager $buyerVerificationManager,
  ) {}

  /**
   * Factory method for dependency injection via the service container.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of this form class.
   */
  public static function create(ContainerInterface $container): static {
    $instance = parent::create($container);

    $instance->currentUser = $container->get('current_user');
    $instance->verificationService = $container->get('my_custom_module.invitation_code');
    $instance->buyerVerificationManager = $container->get('my_custom_module.buyer_verification_manager');

    return $instance;
  }

  /**
   *
   * Returns the unique form ID used by Drupal Form API.
   *
   * @return string
   *   The form ID.
   */
  public function getFormId(): string {
    return 'buyer_verification_form';
  }

  /**
   *
   * Builds the buyer verification form.
   *
   * The form contains:
   * - A verification code text field
   * - A submit button
   *
   * @param array $form
   *   The form render array.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return array
   *   The rendered form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    // Attach custom Twig template for theming this form.
    $form['#theme'] = 'buyer_verification_form';

    // Verification code input field.
    $form['verification_code'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Verification code'),
      '#required' => TRUE,
      '#description' => $this->t('Paste the verification code exactly as provided.'),
    ];

    // Form action container.
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
   *
   * Handles form submission and verifies the user using the provided code.
   *
   * Steps:
   * 1. Retrieve submitted verification code
   * 2. Validate code via InvitationCodeGenerator service
   * 3. Verify current user via BuyerVerificationManager
   * 4. Display success or error messages
   * 5. Redirect user on success
   *
   * @param array $form
   *   The form structure.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get and normalize submitted verification code.
    $code = trim($form_state->getValue('verification_code'));

    // Validate verification code.
    $verification = $this->verificationService->validate($code);

    if (!$verification) {
      $this->messenger()->addError($this->t('Invalid verification code.'));
      return;
    }

    // Verifies the current user using an invitation code.
    $result = $this->buyerVerificationManager->verifyCurrentUser(
      $this->currentUser,
      $code,
    );

    if (!$result->success) {
      $this->messenger()->addError($result->message);
      return;
    }

    // Success message and redirect.
    $this->messenger()->addStatus($result->message);
    $form_state->setRedirectUrl($result->redirectUrl);
  }

}
