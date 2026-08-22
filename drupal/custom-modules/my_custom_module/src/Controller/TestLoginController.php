<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Returns user authentication Json response.
 */
final class TestLoginController extends ControllerBase {

  /**
   * The controller constructor.
   */
  public function __construct(
    private readonly UserAuthenticationInterface $userAuth,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('user.auth'),
    );
  }

  /**
   * Authenticates a user and establishes a Drupal session.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The incoming request containing username and password.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the authentication result.
   */
  public function login(Request $request): JsonResponse {
    $data = $request->toArray();

    $username = $data['username'] ?? '';
    $password = $data['password'] ?? '';

    if ($username === '' || $password === '') {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Username and password are required.',
      ], 400);
    }

    $uid = $this->userAuth->authenticate($username, $password);

    if (!$uid) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'Invalid username or password.',
      ], 401);
    }

    $account = $this->entityTypeManager()
      ->getStorage('user')
      ->load($uid);

    if (!$account || !$account->isActive()) {
      return new JsonResponse([
        'success' => FALSE,
        'message' => 'User is not active.',
      ], 403);
    }

    user_login_finalize($account);

    return new JsonResponse([
      'success' => TRUE,
      'uid' => $account->id(),
      'username' => $account->getAccountName(),
      'roles' => $account->getRoles(),
    ]);
  }

}
