<?php

namespace Drupal\my_custom_module\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\my_custom_module\Service\GroceryImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes store import queue.
 *
 * @QueueWorker(
 *   id = "my_custom_module_import_queue",
 *   title = @Translation("Store Import Queue"),
 *   cron = {"time" = 100}
 * )
 */
class StoreImportQueue extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The grocery import service.
   *
   * @var \Drupal\my_custom_module\Service\GroceryImportService
   */
  protected GroceryImportService $importService;

  /**
   * Constructs the queue worker.
   */
  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    GroceryImportService $importService,
  ) {
    parent::__construct(
      $configuration,
      $plugin_id,
      $plugin_definition
    );

    $this->importService = $importService;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('my_custom_module.grocery_import')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($store_id) {
    $store_id = (int) $store_id;

    try {
      $this->importService->import((int) $store_id);
      \Drupal::logger('my_custom_module')->notice(
        'Store @store import completed.',
        ['@store' => $store_id]
      );
    }
    catch (\Throwable $e) {
      \Drupal::logger('my_custom_module')->error(
        'Store @store import failed: @message',
        [
          '@store' => $store_id,
          '@message' => $e->getMessage(),
        ]
      );

      // Do not allow a permanent validation failure
      // to endlessly retry.
      return;
    }
  }

}
