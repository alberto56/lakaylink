<?php

namespace Drupal\my_custom_module\traits;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Utility\Error;

/**
 * General utilities trait.
 *
 * If your class needs to use any of these, add "use Environment" your class
 * and these methods will be available and mockable in tests.
 */
trait Environment {

  /**
   * Mockable wrapper around drupal_set_message().
   */
  protected function drupalSetMessage($message = NULL, $type = 'status', $repeat = FALSE) {
    $messenger = \Drupal::messenger();

    switch ($type) {
      case 'warning':
        $messenger->addWarning($message);
        break;

      case 'status':
        $messenger->addStatus($message);
        break;

      case 'error':
        $messenger->addError($message);
        break;

      default:
        $messenger->addMessage($message);
        break;
    }
  }

  /**
   * Mockable wrapper around $form_state->getUserInput()().
   */
  protected function formStateGetUserInput(FormStateInterface $form_state) : array {
    return $form_state->getUserInput();
  }

  /**
   * Mockable wrapper around \Drupal::state()->get().
   */
  public function stateGet($variable, $default = NULL) {
    return \Drupal::state()->get($variable, $default);
  }

  /**
   * Mockable wrapper around \Drupal::state()->set().
   */
  public function stateSet($variable, $value) {
    \Drupal::state()->set($variable, $value);
  }

  /**
   * Log a \Throwable to the watchdog.
   *
   * Modeled after Core's watchdog_exception().
   *
   * @param \Throwable $t
   *   A \throwable.
   * @param mixed $message
   *   The message to store in the log. If empty, a text that contains all
   *   useful information about the passed-in exception is used.
   * @param mixed $variables
   *   Array of variables to replace in the message on display or NULL if
   *   message is already translated or not possible to translate.
   * @param mixed $severity
   *   The severity of the message, as per RFC 3164.
   * @param mixed $link
   *   A link to associate with the message.
   */
  public function watchdogThrowable(\Throwable $t, $message = NULL, $variables = [], $severity = RfcLogLevel::ERROR, $link = NULL) {

    // Use a default value if $message is not set.
    if (empty($message)) {
      $message = '%type: @message in %function (line %line of %file).';
    }

    if ($link) {
      $variables['link'] = $link;
    }

    $variables += Error::decodeException($t);

    \Drupal::logger('my_custom_module')->log($severity, $message, $variables);
  }

  /**
   * Returns the storage handler for a given entity type.
   *
   * @param string $storage
   *   The machine name of the entity type storage to retrieve.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The storage handler for the specified entity type.
   */
  public function getEntityTypeManager($storage) {
    return \Drupal::entityTypeManager()->getStorage($storage);
  }

  /**
   * Returns a queue object for the given queue name.
   *
   * @param string $queueName
   *   The name of the queue to retrieve.
   *
   * @return \Drupal\Core\Queue\QueueInterface
   *   The queue object corresponding to the given name.
   */
  public function getQueue($queueName) {
    return \Drupal::queue($queueName);
  }

  /**
   * Returns a logger instance for the given channel.
   *
   * @param string $loggerName
   *   The name of the logger channel.
   *
   * @return \Psr\Log\LoggerInterface
   *   A logger instance for the specified channel.
   */
  public function getLogger($loggerName) {
    return \Drupal::logger($loggerName);
  }

  /**
   * Returns the currently logged-in user.
   *
   * @return \Drupal\Core\Session\AccountProxyInterface
   *   The current user account proxy object.
   */
  public function getCurrentUser() {
    return \Drupal::currentUser();
  }

  /**
   * Returns a service from the Drupal service container.
   *
   * @param string $serviceName
   *   The machine name of the service to retrieve.
   *
   * @return object
   *   The requested service object.
   */
  public function getService($serviceName) {
    return \Drupal::service($serviceName);
  }

}
