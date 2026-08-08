<?php

namespace Drupal\my_custom_module\Service;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\ClientInterface;

/**
 * Handles grocery store imports.
 */
class GroceryImportService {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The HTTP client.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected ClientInterface $httpClient;

  /**
   * The file repository.
   *
   * @var \Drupal\Core\File\FileRepositoryInterface
   */
  protected FileRepositoryInterface $fileRepository;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs the grocery import service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    ClientInterface $httpClient,
    FileRepositoryInterface $fileRepository,
    LanguageManagerInterface $languageManager
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory;
    $this->httpClient = $httpClient;
    $this->fileRepository = $fileRepository;
    $this->languageManager = $languageManager;
  }

  /**
   * Entry point: Import grocery data for a store.
   *
   * @param int|string $store_id
   *   The Commerce store ID.
   *
   * @throws \Throwable
   *   When the import fails.
   */
  public function import($store_id): void {

    $logger = $this->loggerFactory->get('grocery_import');

    try {
      $logger->notice('Import started for store: @store_id', [
        '@store_id' => $store_id,
      ]);

      $store = $this->loadStore($store_id);

      $csv_url = $this->buildGoogleSheetCsvUrl($store);

      $csv_raw = $this->fetchCsvFromUrl($csv_url);

      $rows = $this->parseCsv($csv_raw);

      $this->processRows($rows, $store_id);

      $logger->notice('Import completed successfully for store: @store_id', [
        '@store_id' => $store_id,
      ]);
    }
    catch (\Throwable $e) {
      $logger->error(
        'Import FAILED for store @store_id: @message',
        [
          '@store_id' => $store_id,
          '@message' => $e->getMessage(),
        ]
      );

      throw $e;
    }
  }

  /**
   * Load a Commerce store.
   *
   * @param int|string $store_id
   *   The store ID.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The store.
   *
   * @throws \Exception
   *   If the store does not exist.
   */
  public function loadStore($store_id) {
    $store = $this->entityTypeManager
      ->getStorage('commerce_store')
      ->load($store_id);

    if (!$store) {
      throw new \Exception("Invalid store ID: $store_id");
    }

    return $store;
  }

  /**
   * Build Google Sheet CSV URL.
   *
   * @param object $store
   *   The Commerce store.
   *
   * @return string
   *   The CSV URL.
   *
   * @throws \Exception
   *   If the URL or GID is missing/invalid.
   */
  public function buildGoogleSheetCsvUrl($store): string {
    $url = $store->get('field_google_sheet_url')->uri ?? '';
    $sheet = $store->get('field_google_sheet_tab_gid')->value ?? '';

    if (empty($url)) {
      throw new \Exception(
        'Google Sheet URL is missing for store ID: ' . $store->id()
      );
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \Exception("Invalid Google Sheet URL format: $url");
    }

    if ($sheet === '') {
      throw new \Exception(
        'Sheet GID is missing for store ID: ' . $store->id()
      );
    }

    $separator = str_contains($url, '?') ? '&' : '?';
    $full_url = $url . $separator . 'gid=' . $sheet;

    $this->loggerFactory
      ->get('grocery_import')
      ->notice('CSV URL built: @url', [
        '@url' => $full_url,
      ]);

    return $full_url;
  }

  /**
   * Fetch and parse CSV data.
   *
   * Kept for compatibility with the original implementation.
   *
   * @param string $url
   *   The URL.
   *
   * @return array
   *   Parsed CSV rows.
   *
   * @throws \Exception
   *   If the CSV cannot be fetched.
   */
  public function fetchAndParseCsv($url): array {
    $csv_data = file_get_contents($url);

    if (!$csv_data) {
      throw new \Exception(
        'Cannot fetch CSV from Google Sheets.'
      );
    }

    $csv_data = str_replace(["\r\n", "\r"], "\n", $csv_data);
    $lines = array_filter(explode("\n", $csv_data));

    $rows = array_map('str_getcsv', $lines);

    $header = array_map('trim', array_shift($rows));

    $parsed = [];

    foreach ($rows as $row) {
      if (count($row) !== count($header)) {
        continue;
      }

      $parsed[] = array_combine(
        $header,
        array_map('trim', $row)
      );
    }

    return $parsed;
  }

  /**
   * Parse CSV data safely.
   *
   * @param string $csv_data
   *   Raw CSV data.
   *
   * @return array
   *   Parsed rows.
   *
   * @throws \Exception
   *   If CSV is invalid.
   */
  public function parseCsv($csv_data): array {
    $csv_data = str_replace(["\r\n", "\r"], "\n", $csv_data);
    $lines = array_filter(explode("\n", $csv_data));

    if (count($lines) < 2) {
      throw new \Exception(
        'CSV must contain header and at least one data row.'
      );
    }

    $rows = array_map('str_getcsv', $lines);

    $header = array_map('trim', array_shift($rows));

    if (empty($header)) {
      throw new \Exception(
        'CSV header row is empty or malformed.'
      );
    }

    $required = [
      'product_id',
      'product_name',
      'variation_sku',
    ];

    foreach ($required as $column) {
      if (!in_array($column, $header, TRUE)) {
        throw new \Exception(
          "Missing required column: $column"
        );
      }
    }

    $parsed = [];

    foreach ($rows as $index => $row) {
      if (count($row) !== count($header)) {
        $this->loggerFactory
          ->get('grocery_import')
          ->warning(
            'Skipping malformed row at line @line.',
            [
              '@line' => $index + 2,
            ]
          );

        continue;
      }

      $parsed[] = array_combine(
        $header,
        array_map('trim', $row)
      );
    }

    if (empty($parsed)) {
      throw new \Exception(
        'No valid data rows found in CSV.'
      );
    }

    return $parsed;
  }

  /**
   * Fetch CSV data with detailed error handling.
   *
   * @param string $url
   *   CSV URL.
   *
   * @return string
   *   CSV content.
   *
   * @throws \Exception
   *   If the request fails.
   */
  public function fetchCsvFromUrl($url): string {
    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 30,
        'http_errors' => FALSE,
      ]);

      $status_code = $response->getStatusCode();

      if ($status_code < 200 || $status_code >= 300) {
        throw new \Exception(
          "CSV request failed with HTTP status $status_code: $url"
        );
      }

      $csv_data = $response->getBody()->getContents();
      if (empty(trim($csv_data))) {
        throw new \Exception(
          "CSV file is empty. URL: $url"
        );
      }

      return $csv_data;
    }
    catch (\Throwable $e) {
      $this->loggerFactory
        ->get('grocery_import')
        ->error(
          'CSV fetch failed: @message',
          [
            '@message' => $e->getMessage(),
          ]
        );

      throw $e;
    }
  }

  /**
   * Process all CSV rows.
   *
   * @param array $rows
   *   CSV rows.
   * @param int|string $store_id
   *   Store ID.
   */
  public function processRows(array $rows, $store_id): void {
    foreach ($rows as $index => $data) {
      $line = $index + 2;

      if (!$this->validateRow($data, $line, $store_id)) {
        continue;
      }

      $context = [
        'line' => $line,
        'store_id' => $store_id,
      ];

      if (empty($data['variation_sku'])) {
        continue;
      }

      $this->loggerFactory
        ->get('grocery_import')
        ->notice(
          'Processing SKU: @sku',
          [
            '@sku' => $data['variation_sku'],
          ]
        );

      $langcode = !empty($data['lang'])
        ? $data['lang']
        : 'en';

      if (!in_array($langcode, ['en', 'fr'], TRUE)) {
        continue;
      }
      $product = $this->loadOrCreateProduct(
        $data,
        $store_id,
        $langcode
      );

      $this->updateProduct(
        $product,
        $data,
        $context,
        $langcode
      );

      $variation = $this->loadOrCreateVariation(
        $data,
        $langcode
      );

      if ($variation) {
        $this->updateVariation(
          $variation,
          $data,
          $langcode,
          $context
        );

        $this->attachVariationToProduct(
          $product,
          $variation
        );
      }
    }
  }

  /**
   * Reload variation by SKU.
   *
   * @param string $sku
   *   SKU.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface|null
   *   Variation or NULL.
   */
  public function reloadVariationBySku($sku) {
    $storage = $this->entityTypeManager
      ->getStorage('commerce_product_variation');

    $variations = $storage->loadByProperties([
      'sku' => $sku,
    ]);

    return $variations ? reset($variations) : NULL;
  }

  /**
   * Load or create a product.
   */
  public function loadOrCreateProduct(
    array $data,
    $store_id,
    $langcode = 'en'
  ) {
    $storage = $this->entityTypeManager
      ->getStorage('commerce_product');

    $existing = $storage->loadByProperties([
      'field_product_id' => $data['product_id'],
      'type' => 'grocery_product',
      'stores' => [$store_id],
    ]);

    $product = reset($existing);

    if (!$product) {
      $product = $storage->create([
        'type' => 'grocery_product',
        'title' => $data['product_name'],
        'stores' => [$store_id],
        'field_product_id' => $data['product_id'],
        'langcode' => $langcode,
      ]);
    }
    elseif ($product->language()->getId() !== $langcode) {
      if ($product->hasTranslation($langcode)) {
        return $product->getTranslation($langcode);
      }

      return $product->addTranslation($langcode);
    }

    return $product;
  }

  /**
   * Update product fields.
   */
  public function updateProduct(
    $product,
    array $data,
    $context,
    $langcode = 'en'
  ): void {
    if ($product->hasTranslation($langcode)) {
      $translated = $product->getTranslation($langcode);
    }
    else {
      $translated = $product->addTranslation($langcode);
    }

    $translated->setTitle($data['product_name']);

    $translated->set(
      'status',
      (bool) $data['status']
    );

    if (!empty($data['product_description'])) {
      $translated->set('body', [
        'value' => $data['product_description'],
        'format' => 'basic_html',
      ]);
    }

    $translated->set(
      'field_brand',
      $this->getOrCreateTerm(
        'brand',
        $data['brand'],
        $data['brand_code'],
        $langcode
      )
    );

    $translated->set(
      'field_category',
      $this->getOrCreateTerm(
        'category',
        $data['category'],
        $data['category_code'],
        $langcode
      )
    );

    $translated->set(
      'field_sub_category',
      $this->getOrCreateTerm(
        'sub_category',
        $data['sub_category'],
        $data['sub_category_code'],
        $langcode
      )
    );

    $translated->save();

    $product->save();
  }

  /**
   * Load or create a product variation.
   */
  public function loadOrCreateVariation(
    array $data,
    $langcode = 'en'
  ) {
    $storage = $this->entityTypeManager
      ->getStorage('commerce_product_variation');

    $existing = $storage->loadByProperties([
      'sku' => $data['variation_sku'],
    ]);

    $variation = reset($existing);

    if (!$variation) {
      $variation = ProductVariation::create([
        'type' => 'grocery_variation',
        'sku' => $data['variation_sku'],
        'langcode' => $langcode,
      ]);
    }
    elseif ($variation->language()->getId() !== $langcode) {
      if ($variation->hasTranslation($langcode)) {
        return $variation->getTranslation($langcode);
      }

      return $variation->addTranslation($langcode);
    }

    return $variation;
  }

  /**
   * Update variation fields.
   */
  public function updateVariation(
    $variation,
    array $data,
    $langcode = 'en',
    $context = []
  ): void {
    if ($variation->hasTranslation($langcode)) {
      $translated = $variation->getTranslation($langcode);
    }
    else {
      $translated = $variation->addTranslation($langcode);
    }

    $translated->setTitle($data['variant_name']);
    $translated->save();

    $variation->set('price', [
      'number' => $data['price'],
      'currency_code' => $data['currency'],
    ]);

    $variation->set(
      'field_quantity',
      $data['quantity']
    );

    $variation->set(
      'field_unit',
      $data['unit']
    );

    $variation->set(
      'field_pack_type',
      $data['pack_type']
    );

    $variation->set(
      'field_stock',
      $data['stock']
    );

    $variation->set(
      'field_storage_type',
      $data['storage_type']
    );

    $variation->set(
      'field_expiry_days',
      $data['expiry_days']
    );

    $variation->set(
      'field_origin',
      $data['origin']
    );

    $status = trim($data['status'] ?? '');

    $variation->set('status', $status);

    // Image handling.
    if (!empty($data['image_url'])) {
      $file = $this->downloadImage(
        $data['image_url'],
        $context
      );

      if ($file) {
        $variation->set('field_image', [
          'target_id' => $file->id(),
          'alt' => $data['variant_name']
            ?? $data['product_name'],
        ]);
      }
      else {
        $this->loggerFactory
          ->get('grocery_import')
          ->error(
            'Line @line store @store invalid image URL: @url',
            [
              '@line' => $context['line'],
              '@store' => $context['store_id'],
              '@url' => $data['image_url'],
            ]
          );
      }
    }

    $variation->save();
  }

  /**
   * Attach variation to product.
   */
  public function attachVariationToProduct(
    $product,
    $variation
  ): void {
    foreach ($product->getVariations() as $existing) {
      if ($existing->id() == $variation->id()) {
        return;
      }
    }

    $product->addVariation($variation);
    $product->save();
  }

  /**
   * Download an image.
   */
  public function downloadImage($url, array $context) {
    try {
      $response = $this->httpClient->get($url, [
        'timeout' => 30,
      ]);

      if ($response->getStatusCode() !== 200) {
        return NULL;
      }

      $data = $response->getBody()->getContents();

      $path = parse_url($url, PHP_URL_PATH);
      $file_name = basename($path);

      if (!$file_name) {
        $file_name = 'grocery-image-' . uniqid() . '.jpg';
      }

      return $this->fileRepository->writeData(
        $data,
        'public://' . $file_name,
        FileSystemInterface::EXISTS_REPLACE
      );
    }
    catch (\Throwable $e) {
      $this->loggerFactory
        ->get('grocery_import')
        ->error(
          'Image download failed at line @line store @store: @url - @message',
          [
            '@line' => $context['line'],
            '@store' => $context['store_id'],
            '@url' => $url,
            '@message' => $e->getMessage(),
          ]
        );

      return NULL;
    }
  }

  /**
   * Get or create taxonomy term.
   */
  public function getOrCreateTerm(
    $vocab,
    $name,
    $code,
    $langcode = 'en'
  ) {
    $storage = $this->entityTypeManager
      ->getStorage('taxonomy_term');

    $terms = $storage->loadByProperties([
      'vid' => $vocab,
      'field_' . $vocab . '_code' => $code,
    ]);

    $term = reset($terms);

    $default_langcode = $this->languageManager
      ->getDefaultLanguage()
      ->getId();

    if (!$term) {
      $term = Term::create([
        'vid' => $vocab,
        'name' => $name,
        'langcode' => $default_langcode,
        'field_' . $vocab . '_code' => $code,
      ]);

      $term->save();
    }

    if ($langcode !== $default_langcode) {
      if ($term->hasTranslation($langcode)) {
        $translated = $term->getTranslation($langcode);
      }
      else {
        $translated = $term->addTranslation($langcode);
      }

      $translated->setName($name);
      $translated->save();
    }

    return $term;
  }

  /**
   * Validate a CSV row.
   */
  public function validateRow(
    array $row,
    $line,
    $store_id
  ): bool {
    $required = [
      'product_id',
      'product_name',
      'variation_sku',
    ];

    foreach ($required as $field) {
      if (empty($row[$field])) {
        $this->loggerFactory
          ->get('grocery_import')
          ->warning(
            'Line @line of CSV for store @store has an empty entry for @field. Skipping row.',
            [
              '@line' => $line,
              '@store' => $store_id,
              '@field' => $field,
            ]
          );

        return FALSE;
      }
    }

    return TRUE;
  }

}
