<?php

declare(strict_types=1);

namespace Drupal\social_auth\Event;

use Symfony\Contracts\EventDispatcher\Event;

if (class_exists(LoginEvent::class, false) === FALSE) {
  /**
   * PHPStan stub for the Social Auth login event.
   */
  class LoginEvent extends Event {
  }
}

if (class_exists(SocialAuthEvents::class, false) === FALSE) {
  /**
   * PHPStan stub for Social Auth events.
   */
  final class SocialAuthEvents {

    /**
     * The user login event.
     */
    public const USER_LOGIN = 'social_auth.user_login';

  }
}
