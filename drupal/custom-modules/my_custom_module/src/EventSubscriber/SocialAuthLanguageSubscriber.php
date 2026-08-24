<?php

declare(strict_types=1);

/**
 * @file
 * Contains the SocialAuthLanguageSubscriber class.
 */

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\social_auth\Event\BeforeRedirectEvent;
use Drupal\social_auth\Event\LoginEvent;
use Drupal\social_auth\Event\SocialAuthEvents;
use Drupal\social_auth\SocialAuthDataHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Preserves the Drupal language during Social Auth authentication.
 */
class SocialAuthLanguageSubscriber implements EventSubscriberInterface {

  /**
   * The Drupal language manager.
   */
  private LanguageManagerInterface $languageManager;

  /**
   * The Social Auth data handler.
   */
  private SocialAuthDataHandler $dataHandler;

  /**
   * The logger.
   */
  private LoggerChannelInterface $logger;

  /**
   * The request stack.
   */
  private RequestStack $requestStack;

  /**
   * Constructs the Social Auth language subscriber.
   *
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   * @param \Drupal\social_auth\SocialAuthDataHandler $data_handler
   *   The Social Auth data handler.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_factory
   *   The logger factory.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack.
   */
  public function __construct(
    LanguageManagerInterface $language_manager,
    SocialAuthDataHandler $data_handler,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
  ) {
    $this->languageManager = $language_manager;
    $this->dataHandler = $data_handler;
    $this->logger = $logger_factory->get('my_custom_module');
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
   * Stores the current language before redirecting to Google.
   *
   * This happens before Social Auth sends the user to Google.
   *
   * @param \Drupal\social_auth\Event\BeforeRedirectEvent $event
   *   The Social Auth before-redirect event.
   */
  public function beforeRedirect(BeforeRedirectEvent $event): void {
    $language = $this->languageManager->getCurrentLanguage();

    $language_code = $language->getId();

    // Store the language in Social Auth's data handler so that it survives
    // the OAuth redirect to Google and back.
    $this->dataHandler->set(
      'social_auth_language',
      $language_code
    );

    $this->logger->notice(
      'Stored Social Auth language: @language',
      [
        '@language' => $language_code,
      ]
    );
  }

  /**
   * Handles successful Social Auth login.
   *
   * Copies the language from Social Auth's data handler into the Drupal
   * session so that the post-login controller can use it.
   *
   * @param \Drupal\social_auth\Event\LoginEvent $event
   *   The Social Auth login event.
   */
  public function onLogin(LoginEvent $event): void {
    $language_code = $this->dataHandler->get('social_auth_language');

    if (!$language_code) {
      $this->logger->warning(
        'Social Auth login completed, but no stored language was found.'
      );

      return;
    }

    $request = $this->requestStack->getCurrentRequest();

    if (!$request) {
      $this->logger->warning(
        'Social Auth login language @language could not be stored because there is no current request.',
        [
          '@language' => $language_code,
        ]
      );

      return;
    }

    $session = $request->getSession();

    // Store the language in the Drupal session. The post-login controller
    // will read this value and generate localized URLs.
    $session->set(
      'social_auth_language',
      $language_code
    );

    $this->logger->notice(
      'Social Auth login language: @language',
      [
        '@language' => $language_code,
      ]
    );
  }

}
