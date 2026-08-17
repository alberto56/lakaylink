<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\my_custom_module\BuyerStoreResolverInterface;
use Drupal\user\UserStorage;

/**
 * Manages buyer account verification and post-verification actions.
 */
class BuyerVerificationManager {

  use StringTranslationTrait;

  /**
   * The invitation code verification service.
   *
   * @var \Drupal\my_custom_module\Service\InvitationCodeGenerator
   */
  protected InvitationCodeGenerator $verificationService;

  /**
   * Resolves stores available to a buyer.
   *
   * @var \Drupal\my_custom_module\BuyerStoreResolverInterface
   */
  protected BuyerStoreResolverInterface $buyerStoreResolver;

  /**
   * The user entity storage.
   *
   * @var \Drupal\user\UserStorage
   */
  protected UserStorage $userStorage;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a BuyerVerificationManager object.
   *
   * @param \Drupal\my_custom_module\Service\InvitationCodeGenerator $verificationService
   *   The invitation code verification service.
   * @param \Drupal\my_custom_module\BuyerStoreResolverInterface $buyerStoreResolver
   *   The buyer store resolver service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   */
  public function __construct(
    InvitationCodeGenerator $verificationService,
    BuyerStoreResolverInterface $buyerStoreResolver,
    EntityTypeManagerInterface $entityTypeManager,
    LanguageManagerInterface $languageManager,
  ) {
    $this->verificationService = $verificationService;
    $this->buyerStoreResolver = $buyerStoreResolver;
    $this->userStorage = $entityTypeManager->getStorage('user');
    $this->languageManager = $languageManager;
  }

  /**
   * Verifies the current user using an invitation code.
   *
   * Validates the supplied verification code, assigns the verified store to the
   * user, updates the user's roles, and determines the appropriate redirect
   * destination based on the number of assigned stores.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $account
   *   The currently authenticated user account.
   * @param string $code
   *   The verification code entered by the user.
   *
   * @return \Drupal\my_custom_module\Service\BuyerVerificationResult
   *   The verification result.
   */
  public function verifyCurrentUser(
    AccountProxyInterface $account,
    string $code,
  ): BuyerVerificationResult {

    // Validate the submitted verification code.
    $verification = $this->verificationService->validate($code);

    if (!$verification) {
      return new BuyerVerificationResult(
        success: FALSE,
        message: (string) $this->t('Invalid or expired verification code.'),
      );
    }

    // Load the current user entity.
    $user = $this->userStorage->load($account->id());

    if (!$user) {
      return new BuyerVerificationResult(
        success: FALSE,
        message: (string) $this->t('Unable to load account.'),
      );
    }

    // Get the store associated with the verification code.
    $store_id = $verification['store_id'];

    // Add the store to the user's allowed stores if it is not already assigned.
    if ($user->hasField('field_allowed_stores')) {
      $existing = array_column(
        $user->get('field_allowed_stores')->getValue(),
        'target_id'
      );

      if (!in_array($store_id, $existing, TRUE)) {
        $user->get('field_allowed_stores')->appendItem($store_id);
      }
    }

    // Remove the unverified role after successful verification.
    if ($user->hasRole('unverified')) {
      $user->removeRole('unverified');
    }

    // Ensure the user has the buyer role.
    if (!$user->hasRole('buyer')) {
      $user->addRole('buyer');
    }

    // Save the updated user entity.
    $user->save();

    // Retrieve all stores assigned to the buyer.
    $stores = $this->buyerStoreResolver->getAllowedStores($user);

    // Buyers must have at least one assigned store.
    if (count($stores) === 0) {
      return new BuyerVerificationResult(
        success: FALSE,
        message: (string) $this->t(
          'No stores have been assigned to your account.'
        ),
      );
    }

    // Get the language associated with the current request.
    $language = $this->languageManager->getCurrentLanguage();

    // Redirect directly when only one store is assigned.
    if (count($stores) === 1) {
      $store = reset($stores);

      return new BuyerVerificationResult(
        success: TRUE,
        message: (string) $this->t('Your account has been verified.'),
        redirectUrl: $store->toUrl(
          'canonical',
          [
            'language' => $language,
          ]
        ),
      );
    }

    // Redirect buyers to the localized store selector
    // when multiple stores exist.
    return new BuyerVerificationResult(
      success: TRUE,
      message: (string) $this->t('Your account has been verified.'),
      redirectUrl: Url::fromRoute(
        'view.store_selector.page_1',
        [],
        [
          'language' => $language,
        ]
      ),
    );
  }

}
