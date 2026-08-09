<?php

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Url;
use Drupal\commerce_store\Entity\Store;
use Drupal\my_custom_module\Service\GroceryImportService;
use Symfony\Component\DependencyInjection\ContainerInterface;

class ImportStatusController extends ControllerBase {

  protected DateFormatterInterface $dateFormatter;

  protected GroceryImportService $groceryImportService;

  public function __construct(
    DateFormatterInterface $date_formatter,
    GroceryImportService $groceryImportService
  ) {
    $this->dateFormatter = $date_formatter;
    $this->groceryImportService = $groceryImportService;
  }

  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('date.formatter'),
      $container->get('my_custom_module.grocery_import'),
    );
  }

  public function status(Store $commerce_store) {
    $status = $this->groceryImportService
      ->getLatestImportStatus($commerce_store->id());

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
    ];

    // Add the URL as a render array wrapped in "data".
    if (!empty($status->url)) {
      $rows[] = [
        $this->t('Source URL'),
        [
          'data' => [
            '#type' => 'link',
            '#title' => $status->url,
            '#url' => Url::fromUri($status->url),
            '#attributes' => [
              'target' => '_blank',
              'rel' => 'noopener noreferrer',
            ],
          ],
        ],
      ];
    }
    else {
      $rows[] = [
        $this->t('Source URL'),
        $this->t('Not available'),
      ];
    }

    return [
      '#type' => 'container',
      '#attributes' => [
        'class' => ['import-status'],
      ],
      '#cache' => [
        'max-age' => 0,
      ],
      'title' => [
        '#markup' => '<h2>' . $this->t('Grocery Import Status') . '</h2>',
      ],
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
