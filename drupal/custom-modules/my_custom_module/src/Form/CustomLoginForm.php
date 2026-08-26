<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\user\UserAuthInterface;
use Drupal\user\UserInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a My custom module login form for test user.
 */
final class CustomLoginForm extends FormBase {

  /**
   * The user authentication service.
   *
   * @var \Drupal\user\UserAuthInterface
   */
  protected UserAuthInterface $userAuth;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a CustomLoginForm object.
   *
   * @param \Drupal\user\UserAuthInterface $user_auth
   *   The user authentication service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   */
  public function __construct(
    UserAuthInterface $user_auth,
    EntityTypeManagerInterface $entity_type_manager,
  ) {
    $this->userAuth = $user_auth;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * Creates an instance of the form.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   The form instance.
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('user.auth'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'my_custom_module_custom_login';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $form['name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Username'),
      '#required' => TRUE,
    ];

    $form['pass'] = [
      '#type' => 'password',
      '#title' => $this->t('Password'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Log in'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $username = $form_state->getValue('name');
    $password = $form_state->getValue('pass');

    $uid = $this->userAuth->authenticate($username, $password);

    if (!$uid) {
      $form_state->setErrorByName(
        'name',
        'Unrecognized username or password.'
      );
      return;
    }

    $form_state->set('uid', $uid);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $uid = $form_state->get('uid');

    /** @var \Drupal\user\UserInterface $account */
    $account = $this->entityTypeManager
      ->getStorage('user')
      ->load($uid);

    if (!$account instanceof UserInterface) {
      $this->messenger()->addError($this->t('Unable to log in.'));
      return;
    }

    user_login_finalize($account);

    $form_state->setRedirectUrl(
      Url::fromUserInput('/user-login-redirect')
    );
  }

}
