<?php

namespace Drupal\my_custom_module\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Processes store import queue.
 *
 * @QueueWorker(
 *   id = "my_custom_module_import_queue",
 *   title = @Translation("Store Import Queue"),
 *   cron = {"time" = 100}
 * )
 */
class StoreImportQueue extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($store_id) {
    try {
      my_custom_module_import($store_id);
    }
    catch (\Throwable $e) {
      \Drupal::logger('my_custom_module')->error(
        'Store import failed for store @id: @msg',
        [
          '@id' => $store_id,
          '@msg' => $e->getMessage(),
        ]
      );

      // Throwing exception lets Drupal retry later if needed.
      throw $e;
    }
  }

}
