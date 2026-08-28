<?php

namespace Drupal\my_custom_module\Plugin\QueueWorker;

use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\my_custom_module\Service\GroceryImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes store import queue items.
 *
 * Each queue item contains a Commerce Store ID. The queue worker
 * passes the store ID to the grocery import service for processing.
 *
 * @QueueWorker(
 *   id = "my_custom_module_import_queue",
 *   title = @Translation("Store Import Queue"),
 *   cron = {"time" = 100}
 * )
 */
final class StoreImportQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The grocery import service.
   *
   * @var \Drupal\my_custom_module\Service\GroceryImportService
   */
  protected GroceryImportService $importService;

  /**
   * The logger channel for this module.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * Constructs the store import queue worker.
   *
   * The required services are injected through the service container
   * instead of being accessed statically.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the queue worker.
   * @param mixed $plugin_definition
   *   The plugin definition.
   * @param \Drupal\my_custom_module\Service\GroceryImportService $importService
   *   The service responsible for importing grocery products.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $loggerFactory
   *   The logger channel factory.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GroceryImportService $importService,
    LoggerChannelFactoryInterface $loggerFactory,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    // Store the grocery import service for processing queue items.
    $this->importService = $importService;

    // Create and store the logger channel for this module.
    // This replaces the use of \Drupal::logger().
    $this->logger = $loggerFactory->get('my_custom_module');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,

      // Inject the grocery import service.
      $container->get('my_custom_module.grocery_import'),

      // Inject the logger channel factory.
      $container->get('logger.factory')
    );
  }

  /**
   * Processes a single store import queue item.
   *
   * @param mixed $store_id
   *   The Commerce Store ID stored in the queue.
   *
   * @throws \Throwable
   *   Any exception thrown by the import service is caught and logged.
   */
  public function processItem($store_id) {
    // Ensure the store ID is always treated as an integer.
    $store_id = (int) $store_id;

    try {
      // Run the grocery product import for the specified store.
      $this->importService->import($store_id);

      // Log successful completion of the store import.
      $this->logger->notice(
        'Store @store import completed.',
        [
          '@store' => $store_id,
        ]
      );
    }
    catch (\Throwable $e) {
      // Log the exception details when the import fails.
      $this->logger->error(
        'Store @store import failed: @message',
        [
          '@store' => $store_id,
          '@message' => $e->getMessage(),
        ]
      );

      // Do not allow a permanent validation failure to endlessly retry.
      return;
    }
  }

}
