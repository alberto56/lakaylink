<?php

namespace Drupal\my_custom_module\Drush\Commands;

use Drush\Attributes as CLI;
use Drush\Commands\DrushCommands;

/**
 * Drush commands for my_custom_module.
 */
class ImportProductsCommand extends DrushCommands {

  /**
   * Imports products for a given store.
   *
   * @param string $store_id
   *   The store ID.
   *
   * @command my-custom-module:import
   * @aliases mcmi
   */
  #[CLI\Command(name: 'my-custom-module:import')]
  #[CLI\Argument(name: 'store_id', description: 'Store ID')]
  public function import($store_id) {

    // Ensure module file is loaded (important!).
    \Drupal::moduleHandler()->loadInclude('my_custom_module', 'module');

    // Direct function call.
    my_custom_module_import($store_id);

    $this->output()->writeln("Done: $store_id");
  }

}
