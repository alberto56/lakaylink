<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\commerce_store\Entity\Store;
use Drupal\my_custom_module\Service\GroceryImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Controller for displaying grocery product import status.
 */
class ImportStatusController extends ControllerBase {

  /**
   * The Drupal date formatter service.
   *
   * Used to format Unix timestamps into human-readable dates.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected DateFormatterInterface $dateFormatter;

  /**
   * The grocery import service.
   *
   * Used to retrieve the latest import status for a store.
   *
   * @var \Drupal\my_custom_module\Service\GroceryImportService
   */
  protected GroceryImportService $groceryImportService;

  /**
   * Constructs the import status controller.
   *
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   The date formatter service.
   * @param \Drupal\my_custom_module\Service\GroceryImportService $groceryImportService
   *   The grocery import service.
   */
  public function __construct(
    DateFormatterInterface $date_formatter,
    GroceryImportService $groceryImportService,
  ) {
    // Store the date formatter service for later use.
    $this->dateFormatter = $date_formatter;

    // Store the grocery import service for retrieving import information.
    $this->groceryImportService = $groceryImportService;
  }

  /**
   * Creates the controller using dependency injection.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new instance of the controller.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      // Inject Drupal's date formatter service.
      $container->get('date.formatter'),

      // Inject the custom grocery import service.
      $container->get('my_custom_module.grocery_import'),
    );
  }

  /**
   * Displays the latest grocery import status for a store.
   *
   * The Commerce Store entity is automatically loaded from the
   * {commerce_store} route parameter because the route uses
   * entity:commerce_store parameter conversion.
   *
   * @param \Drupal\commerce_store\Entity\Store $commerce_store
   *   The Commerce Store entity.
   *
   * @return array
   *   A render array containing the import status table.
   */
  public function status(Store $commerce_store) {

    // Retrieve the latest import status for the current store.
    $status = $this->groceryImportService
      ->getLatestImportStatus($commerce_store->id());

    // Build the table rows containing the import information.
    $rows = [
      [
        $this->t('Status'),
        $status->status ?: $this->t('Unknown'),
      ],
      [
        $this->t('Started'),
        $status->started
          ? $this->dateFormatter->format($status->started, 'medium')
          : $this->t('Never'),
      ],
      [
        $this->t('Finished'),
        $status->finished
          ? $this->dateFormatter->format($status->finished, 'medium')
          : $this->t('Never'),
      ],
      [
        $this->t('Products imported'),
        number_format((int) $status->products_imported),
      ],
      [
        $this->t('Failure reason'),
        $status->failure_reason ?: $this->t('None'),
      ],
      [
        $this->t('Import command'),
       'drush php:eval \'\\Drupal::service("my_custom_module.grocery_import")->import(' . $commerce_store->id() . ');\'',
      ],
    ];

    // Add the source URL to the table when one is available.
    if (!empty($status->url)) {
      $rows[] = [
        $this->t('Source URL'),
        [
          'data' => [
            // Render the source URL as a clickable Drupal link.
            '#type' => 'link',
            '#title' => $status->url,
            '#url' => Url::fromUri($status->url),

            // Open the source URL in a new browser tab and
            // prevent the new page from accessing the opener.
            '#attributes' => [
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
            ],
          ],
        ],
      ];
    }
    else {
      // Display a fallback message when no source URL is available.
      $rows[] = [
        $this->t('Source URL'),
        $this->t('Not available'),
      ];
    }

    // Return the page as a Drupal render array.
    return [
      '#type' => 'container',

      // Add a CSS class to the outer container for styling.
      '#attributes' => [
        'class' => ['import-status'],
      ],

      // Disable render caching because import status can change
      // after each queue/import execution.
      '#cache' => [
        'max-age' => 0,
      ],

      // Page heading.
      'title' => [
        '#markup' => '<h2>' . $this->t('Grocery Import Status') . '</h2>',
      ],

      // Display the import information in a Drupal table.
      'table' => [
        '#type' => 'table',
        '#header' => [
          $this->t('Field'),
          $this->t('Value'),
        ],
        '#rows' => $rows,
        '#attributes' => [
          'class' => ['import-status-table'],
        ],
      ],
    ];
  }

}
