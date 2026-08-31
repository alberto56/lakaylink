<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Preserves the Drupal language during Social Auth login.
 */
final class SocialAuthLanguageSubscriber implements EventSubscriberInterface {

  public function __construct(
    private readonly LanguageManagerInterface $languageManager,
    private readonly LoggerChannelFactoryInterface $loggerFactory,
    private readonly RequestStack $requestStack,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      SocialAuthEvents::USER_LOGIN => 'onLogin',
    ];
  }

  /**
   * Stores the current language after Social Auth login.
   */
  public function onLogin(LoginEvent $event): void {
    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      return;
    }

    $language = $this->languageManager->getCurrentLanguage();
    $language_code = $language->getId();

    $request->getSession()->set(
      'social_auth_language',
      $language_code
    );

    $this->loggerFactory
      ->get('my_custom_module')
      ->notice(
        'Social Auth login language: @language',
        [
          '@language' => $language_code,
        ]
      );
  }

}
