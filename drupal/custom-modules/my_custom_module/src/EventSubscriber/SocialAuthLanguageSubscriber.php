<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\social_auth\Event\BeforeRedirectEvent;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpFoundation\RequestStack;


/**
 * @todo Add description for this subscriber.
 */
final class SocialAuthLanguageSubscriber implements EventSubscriberInterface {

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The request stack.
   */
  protected RequestStack $requestStack;

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    RequestStack $request_stack,
  ) {
    $this->languageManager = $language_manager;
    $this->requestStack = $request_stack;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SocialAuthEvents::BEFORE_REDIRECT => 'beforeRedirect',
      SocialAuthEvents::USER_LOGIN => 'onLogin',
    ];
  }

  /**
   * Runs immediately before redirecting the user to Google.
   */
  public function beforeRedirect(BeforeRedirectEvent $event): void {
    $language = $this->languageManager->getCurrentLanguage();

    $event->getDataHandler()->set(
      'social_auth_language',
      $language->getId()
    );
  }

  /**
   * Runs after successful Social Auth login.
   */
  public function onLogin(LoginEvent $event): void {
    $language_code = $event->getDataHandler()->get('social_auth_language');

    if (!$language_code) {
      return;
    }

    // Store it for the next request.
    $this->requestStack->getCurrentRequest()->getSession()->set(
      'social_auth_language',
      $language_code
    );
  }

}
