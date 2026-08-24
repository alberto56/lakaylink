<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\social_auth\Event\BeforeRedirectEvent;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\SocialAuthDataHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Preserves the Drupal language during Social Auth login.
 */
final class SocialAuthLanguageSubscriber implements EventSubscriberInterface {

  /**
   * The language manager.
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * The Social Auth data handler.
   */
  protected SocialAuthDataHandler $dataHandler;

  /**
   * The logger.
   */
  protected $logger;

  /**
   * Constructs the subscriber.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    SocialAuthDataHandler $data_handler,
    LoggerChannelFactoryInterface $logger_factory,
  ) {
    $this->languageManager = $language_manager;
    $this->dataHandler = $data_handler;
    $this->logger = $logger_factory->get('my_custom_module');
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
   * Store the current language before redirecting to Google.
   */
  public function beforeRedirect(BeforeRedirectEvent $event): void {
    $language = $this->languageManager->getCurrentLanguage();

    $this->dataHandler->set(
      'social_auth_language',
      $language->getId()
    );

    $this->logger->notice(
      'Stored Social Auth language: @language',
      [
        '@language' => $language->getId(),
      ]
    );
  }

  /**
   * Runs after successful Social Auth login.
   */
  public function onLogin(LoginEvent $event): void {
    $language_code = $this->dataHandler->get('social_auth_language');

    if (!$language_code) {
      return;
    }

    // Debug for now.
    $this->logger->notice(
      'Social Auth login language: @language',
      ['@language' => $language_code]
    );
  }

}
