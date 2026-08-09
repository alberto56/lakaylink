<?php

namespace Drupal\my_custom_module\Service;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\Http\ClientFactory;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;

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
   * Images downloaded during the current import.
   *
   * @var array<string, \Drupal\file\FileInterface>
   */
  protected array $imageCache = [];

  protected Connection $database;

  protected FileSystemInterface $fileSystem;

  protected CacheBackendInterface $imageValidationCache;

  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * Constructs the grocery import service.
   */
  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    LoggerChannelFactoryInterface $loggerFactory,
    ClientInterface $httpClient,
    FileRepositoryInterface $fileRepository,
    LanguageManagerInterface $languageManager,
    Connection $database,
    FileSystemInterface $file_system,
    CacheBackendInterface $image_validation_cache,
    KeyValueFactoryInterface $keyValueFactory
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->loggerFactory = $loggerFactory;
    $this->httpClient = $httpClient;
    $this->fileRepository = $fileRepository;
    $this->languageManager = $languageManager;
    $this->database = $database;
    $this->fileSystem = $file_system;
    $this->imageValidationCache = $image_validation_cache;
    $this->keyValueFactory = $keyValueFactory;
  }

  /**
   * Returns the persistent import status storage.
   */
  protected function getImportStatusStorage() {
    return $this->keyValueFactory->get('my_custom_module.import_status');
  }

  /**
   * Returns the import logger.
   */
  protected function logger() {
    return $this->loggerFactory->get('grocery_import');
  }

  /**
   * Import grocery data for a store.
   *
   * @param int $store_id
   *   Commerce store ID.
   *
   * @return int
   *   Number of products imported.
   *
   * @throws \Throwable
   *   If the import fails.
   */
  public function import(int $store_id): int {
    $logger = $this->loggerFactory->get('grocery_import');

    $url = '';
    $import_id = NULL;

    // Get the store first so we can obtain the URL.
    $store = $this->loadStore($store_id);

    try {
      // if (!$this->hasValidValidation($store_id)) {
      //   throw new \RuntimeException(
      //     'Import blocked because the latest sheet validation did not pass.'
      //   );
      // }

      /*
       * Build/validate the Google Sheet URL.
       */
      $url = $this->buildGoogleSheetCsvUrl($store);

      /*
       * Create a database record BEFORE any network operation.
       */
      $import_id = $this->startImportStatus(
        $store_id,
        $url
      );

      $logger->notice(
        'Import started for store @store. Import ID: @import_id.',
        [
          '@store' => $store_id,
          '@import_id' => $import_id,
        ]
      );

      /*
       * 1. Download Google Sheet CSV.
       */
      $csv_raw = $this->fetchCsvFromUrl($url);

      /*
       * 2. Parse CSV and validate required fields.
       */
      $rows = $this->parseCsv($csv_raw);

      /*
       * 3. Verify ALL images before importing ANY row.
       *
       * If one image fails, an exception is thrown and processRows()
       * is NEVER called.
       */
      $this->verifyImagesDownloadable(
        $rows,
        $store_id
      );

      /*
       * 4. Import rows only after the entire pre-flight check passes.
       */
      $products_imported = $this->processRows(
        $rows,
        $store_id
      );

      /*
       * 5. Persist successful status.
       */
      $this->completeImportStatus(
        $import_id,
        $products_imported
      );

      $logger->notice(
        'Import completed successfully for store @store. @count products imported. Import ID: @import_id.',
        [
          '@store' => $store_id,
          '@count' => $products_imported,
          '@import_id' => $import_id,
        ]
      );

      return $products_imported;
    }
    catch (\Throwable $e) {
      /*
       * ALWAYS persist failure status.
       *
       * This happens BEFORE the exception is re-thrown.
       */
      if ($import_id !== NULL) {
        try {
          $this->failImportStatus(
            $import_id,
            $e->getMessage()
          );
        }
        catch (\Throwable $status_exception) {
          /*
           * Do not hide the original import exception if the status
           * update itself fails.
           */
          $logger->error(
            'Unable to update import status for store @store, import ID @import_id: @reason',
            [
              '@store' => $store_id,
              '@import_id' => $import_id,
              '@reason' => $status_exception->getMessage(),
            ]
          );
        }
      }

      /*
       * Log the actual import failure.
       */
      $logger->error(
        'Store @store import failed. Import ID: @import_id. Reason: @reason',
        [
          '@store' => $store_id,
          '@import_id' => $import_id ?? 'N/A',
          '@reason' => $e->getMessage(),
        ]
      );

      throw $e;
    }
  }

  /**
   * Load Commerce store.
   */
  protected function loadStore(int $store_id) {
    $store = $this->entityTypeManager
      ->getStorage('commerce_store')
      ->load($store_id);

    if (!$store) {
      throw new \RuntimeException(
        "Invalid store ID: {$store_id}"
      );
    }

    return $store;
  }

  /**
   * Validate Google Sheet configuration.
   *
   * The configured URL must be a publicly accessible CSV URL.
   *
   * We do NOT blindly append:
   *
   *   &gid=XXXX
   *
   * to the URL.
   *
   * The URL itself should point to the required sheet/tab CSV.
   */
  protected function validateGoogleSheetConfiguration($store): array {
    $url = trim(
      $store->get('field_google_sheet_url')->uri ?? ''
    );

    $gid = trim(
      $store->get('field_google_sheet_tab_gid')->value ?? ''
    );

    if ($url === '') {
      return [
        'valid' => FALSE,
        'url' => NULL,
        'reason' => sprintf(
          'Google Sheet CSV URL is missing for store %d.',
          $store->id()
        ),
      ];
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return [
        'valid' => FALSE,
        'url' => NULL,
        'reason' => sprintf(
          'Google Sheet CSV URL is invalid: %s',
          $url
        ),
      ];
    }

    $parts = parse_url($url);

    if (
      empty($parts['scheme']) ||
      !in_array(
        strtolower($parts['scheme']),
        ['http', 'https'],
        TRUE
      )
    ) {
      return [
        'valid' => FALSE,
        'url' => NULL,
        'reason' => sprintf(
          'Google Sheet URL must use HTTP or HTTPS: %s',
          $url
        ),
      ];
    }

    if (empty($parts['host']) ||
      !str_contains(
        strtolower($parts['host']),
        'google.com'
      )
    ) {
      return [
        'valid' => FALSE,
        'url' => NULL,
        'reason' => sprintf(
          'The configured URL is not a Google Sheets URL: %s',
          $url
        ),
      ];
    }

    /*
     * GID is useful for configuration validation/display.
     *
     * But we don't append it blindly because the URL may already
     * contain the correct gid or may be a published CSV URL.
     */
    if ($gid !== '' && !ctype_digit($gid)) {
      return [
        'valid' => FALSE,
        'url' => NULL,
        'reason' => sprintf(
          'Google Sheet Tab GID must contain only digits. Given: %s',
          $gid
        ),
      ];
    }

    /*
     * Validate that the URL can actually return the public CSV.
     *
     * This is more important than simply validating its syntax.
     */
    try {
      $response = $this->httpClient->request(
        'GET',
        $url,
        [
          'timeout' => 30,
          'connect_timeout' => 10,
          'http_errors' => FALSE,
          'headers' => [
            'Accept' => 'text/csv,text/plain,*/*',
            'User-Agent' => 'Drupal Grocery Import',
          ],
        ]
      );

      $status = $response->getStatusCode();

      if ($status < 200 || $status >= 300) {
        return [
          'valid' => FALSE,
          'url' => $url,
          'reason' => sprintf(
            'Google Sheet URL returned HTTP %d. The sheet may not be public or the CSV URL may be incorrect.',
            $status
          ),
        ];
      }

      $body = trim(
        $response->getBody()->getContents()
      );

      if ($body === '') {
        return [
          'valid' => FALSE,
          'url' => $url,
          'reason' => 'Google Sheet CSV response is empty.',
        ];
      }

      /*
       * Store useful validation information.
       */
      $this->logger()->notice(
        'Google Sheet URL is valid for store @store_id: @url',
        [
          '@store_id' => $store->id(),
          '@url' => $url,
        ]
      );

      return [
        'valid' => TRUE,
        'url' => $url,
        'reason' => NULL,
      ];
    }
    catch (\Throwable $e) {
      return [
        'valid' => FALSE,
        'url' => $url,
        'reason' => sprintf(
          'Unable to access Google Sheet CSV: %s',
          $e->getMessage()
        ),
      ];
    }
  }

  public function buildGoogleSheetCsvUrl($store): string {
    $url = trim(
      $store->get('field_google_sheet_url')->uri ?? ''
    );

    $gid = trim(
      $store->get('field_google_sheet_tab_gid')->value ?? ''
    );

    if ($url === '') {
      throw new \Exception(
        "Google Sheet URL is missing for store {$store->id()}."
      );
    }

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \Exception(
        "Invalid Google Sheet URL: {$url}"
      );
    }

    $parts = parse_url($url);

    parse_str(
      $parts['query'] ?? '',
      $query
    );

    /*
     * If this is already a CSV URL, preserve it.
     */
    if (
      isset($query['output']) &&
      $query['output'] === 'csv'
    ) {
      return $url;
    }

    /*
     * Otherwise this should be a Google export URL.
     */
    $query['format'] = 'csv';

    if ($gid !== '') {
      $query['gid'] = $gid;
    }

    $parts['query'] = http_build_query($query);

    $result =
      ($parts['scheme'] ?? 'https') . '://' .
      ($parts['host'] ?? '') .
      ($parts['path'] ?? '');

    if (!empty($parts['query'])) {
      $result .= '?' . $parts['query'];
    }

    return $result;
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
    // IMPORTANT:
    // Validate columns BEFORE processing any row.
    $this->verifyRequiredFields($header);

    // $required = [
    //   'product_id',
    //   'product_name',
    //   'variation_sku',
    // ];

    // foreach ($required as $column) {
    //   if (!in_array($column, $header, TRUE)) {
    //     throw new \Exception(
    //       "Missing required column: $column"
    //     );
    //   }
    // }

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

  public function fetchCsvFromUrl(string $url): string {
    try {
      $response = $this->httpClient->request('GET', $url, [
        'timeout' => 30,
        'connect_timeout' => 15,
        'http_errors' => FALSE,
        'allow_redirects' => TRUE,
      ]);

      $status = $response->getStatusCode();

      if ($status < 200 || $status >= 300) {
        throw new \Exception(
          "Google Sheet returned HTTP {$status}."
        );
      }

      $data = $response->getBody()->getContents();

      if (trim($data) === '') {
        throw new \Exception(
          'Google Sheet CSV is empty.'
        );
      }

      return $data;
    }
    catch (\Throwable $e) {
      throw new \Exception(
        'Unable to access Google Sheet CSV: ' .
        $e->getMessage(),
        0,
        $e
      );
    }
  }

  /**
   * Validate every image before importing any product.
   *
   * IMPORTANT:
   * This method does not save products or variations.
   */
  protected function validateAllImages(
    array $rows,
    int $store_id
  ): array {
    $checked = [];

    foreach ($rows as $index => $row) {
      $line = $index + 2;

      $image_url = trim(
        $row['image_url'] ?? ''
      );

      /*
       * No image URL means there is nothing to validate.
       */
      if ($image_url === '') {
        continue;
      }

      /*
       * Avoid checking the same image multiple times.
       */
      if (isset($checked[$image_url])) {
        continue;
      }

      $checked[$image_url] = TRUE;

      $result = $this->checkImageDownloadable(
        $image_url
      );

      if (!$result['valid']) {
        $reason = sprintf(
          'Image pre-flight validation failed. Line %d, store %d, URL: %s. Reason: %s',
          $line,
          $store_id,
          $image_url,
          $result['reason']
        );

        $this->logger()->error(
          $reason
        );

        return [
          'valid' => FALSE,
          'reason' => $reason,
        ];
      }
    }

    $this->logger()->notice(
      'Image pre-flight validation passed for store @store_id. @count unique images checked.',
      [
        '@store_id' => $store_id,
        '@count' => count($checked),
      ]
    );

    return [
      'valid' => TRUE,
      'reason' => NULL,
    ];
  }

  protected function checkImageDownloadable(string $url): array {
    // $this->clearImageValidationCache();

    $cid = 'image:' . hash('sha256', $url);

    /*
     * Check 24-hour cache first.
     */
    $cached = $this->imageValidationCache->get($cid);

    if ($cached) {
      return [
        ...$cached->data,
        'cached' => TRUE,
      ];
    }

    /*
     * Validate URL syntax first.
     */
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      $result = [
        'valid' => FALSE,
        'reason' => 'Invalid URL format.',
      ];

      /*
       * Invalid URLs are deterministic, so cache the failure.
       */
      $this->imageValidationCache->set(
        $cid,
        $result,
        time() + 86400
      );

      return [
        ...$result,
        'cached' => FALSE,
      ];
    }

    try {
      $response = $this->httpClient->request('GET', $url, [
        /*
         * Overall request timeout.
         */
        'timeout' => 30,

        /*
         * Your Docker environment can have slow/unreachable
         * GitHub IPs, so allow enough time to try another IP.
         */
        'connect_timeout' => 15,

        'http_errors' => FALSE,

        'allow_redirects' => [
          'max' => 5,
          'protocols' => ['http', 'https'],
        ],

        'stream' => TRUE,

        /*
         * Docker has no IPv6 route.
         * Force IPv4.
         */
        'curl' => [
          CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
        ],

        'headers' => [
          'User-Agent' => 'Drupal Grocery Import Validator/1.0',
          'Accept' => 'image/avif,image/webp,image/apng,image/svg+xml,image/*,*/*;q=0.8',
        ],
      ]);

      $status = $response->getStatusCode();

      /*
       * 404, 403, 500, etc.
       *
       * HTTP failures are actual responses, so these can safely
       * be cached for 24 hours.
       */
      if ($status < 200 || $status >= 300) {
        $result = [
          'valid' => FALSE,
          'reason' => "HTTP {$status}.",
        ];

        $this->imageValidationCache->set(
          $cid,
          $result,
          time() + 86400
        );

        return [
          ...$result,
          'cached' => FALSE,
        ];
      }

      $content_type = strtolower(
        trim($response->getHeaderLine('Content-Type'))
      );

      /*
       * Make sure the server actually says this is an image.
       *
       * Empty Content-Type is treated as invalid.
       */
      if (
        $content_type === '' ||
        !str_starts_with($content_type, 'image/')
      ) {
        $result = [
          'valid' => FALSE,
          'reason' => $content_type === ''
            ? 'Image response has no Content-Type.'
            : "URL does not return an image. Content-Type: {$content_type}",
        ];

        $this->imageValidationCache->set(
          $cid,
          $result,
          time() + 86400
        );

        return [
          ...$result,
          'cached' => FALSE,
        ];
      }

      /*
       * Read a small amount.
       *
       * This confirms that the response body isn't empty.
       */
      $sample = $response
        ->getBody()
        ->read(32);

      if ($sample === '') {
        $result = [
          'valid' => FALSE,
          'reason' => 'Image response body is empty.',
        ];

        $this->imageValidationCache->set(
          $cid,
          $result,
          time() + 86400
        );

        return [
          ...$result,
          'cached' => FALSE,
        ];
      }

      $result = [
        'valid' => TRUE,
        'reason' => NULL,
      ];

      /*
       * Cache successful validation for 24 hours.
       */
      $this->imageValidationCache->set(
        $cid,
        $result,
        time() + 86400
      );

      return [
        ...$result,
        'cached' => FALSE,
      ];
    }
    catch (\Throwable $e) {
      /*
       * Network failures are NOT cached.
       *
       * Examples:
       * - Connection refused
       * - Connection timeout
       * - DNS failure
       * - Temporary GitHub/network problem
       *
       * The next import should be allowed to retry.
       */
      return [
        'valid' => FALSE,
        'reason' => 'Network error: ' . $e->getMessage(),
        'cached' => FALSE,
      ];
    }
  }

  /**
   * Start a new import status record.
   *
   * @param int $store_id
   *   Store ID.
   * @param string $url
   *   Google Sheet CSV URL.
   *
   * @return int
   *   Import status record ID.
   */
  protected function startImportStatus(int $store_id, string $url): int {
    return (int) $this->database
      ->insert('my_custom_module_products_import_status')
      ->fields([
        'store_id' => $store_id,
        'status' => 'running',
        'started' => \Drupal::time()->getRequestTime(),
        'finished' => NULL,
        'products_imported' => 0,
        'failure_reason' => NULL,
        'url' => $url,
      ])
      ->execute();
  }


  /**
   * Mark an import as successfully completed.
   *
   * @param int $import_id
   *   Import status record ID.
   * @param int $products_imported
   *   Number of products imported.
   */
  protected function completeImportStatus(
    int $import_id,
    int $products_imported
  ): void {
    $this->database
      ->update('my_custom_module_products_import_status')
      ->fields([
        'status' => 'completed',
        'finished' => \Drupal::time()->getRequestTime(),
        'products_imported' => $products_imported,
        'failure_reason' => NULL,
      ])
      ->condition('id', $import_id)
      ->execute();
  }


  /**
   * Mark an import as failed.
   *
   * @param int $import_id
   *   Import status record ID.
   * @param string $reason
   *   Failure reason.
   */
  protected function failImportStatus(
    int $import_id,
    string $reason
  ): void {
    $this->database
      ->update('my_custom_module_products_import_status')
      ->fields([
        'status' => 'failed',
        'finished' => \Drupal::time()->getRequestTime(),
        'failure_reason' => $reason,
      ])
      ->condition('id', $import_id)
      ->execute();
  }


  /**
   * Get the latest import status for a store.
   *
   * @param int $store_id
   *   Store ID.
   *
   * @return object|null
   *   Latest import status.
   */
  public function getLatestImportStatus(int $store_id): ?object {
    $query = $this->database
      ->select('my_custom_module_products_import_status', 's')
      ->fields('s')
      ->condition('store_id', $store_id)
      ->orderBy('id', 'DESC')
      ->range(0, 1);

    $result = $query->execute()->fetchObject();

    return $result ?: NULL;
  }


  /**
   * Get all import attempts for a store.
   *
   * @param int $store_id
   *   Store ID.
   *
   * @return array
   *   Import records.
   */
  public function getImportStatuses(int $store_id): array {
    return $this->database
      ->select('my_custom_module_products_import_status', 's')
      ->fields('s')
      ->condition('store_id', $store_id)
      ->orderBy('id', 'DESC')
      ->execute()
      ->fetchAll();
  }

  /**
   * Get import status for a store.
   *
   * @param int $store_id
   *   Store ID.
   *
   * @return array
   *   Import status.
   */
  public function getImportStatus(int $store_id): array {
    $storage = $this->getImportStatusStorage();

    $status = $storage->get((string) $store_id);

    if (!$status) {
      return [
        'status' => 'never',
        'started' => NULL,
        'finished' => NULL,
        'products_imported' => 0,
        'failure_reason' => '',
        'url' => '',
      ];
    }

    return $status;
  }

  /**
   * Get state key for a store.
   */
  protected function getStatusKey(int $store_id): string {
    return 'my_custom_module.import_status.' . $store_id;
  }


  /**
   * Verify that every image can be downloaded.
   *
   * NO product rows are imported until this entire method succeeds.
   *
   * @param array $rows
   *   Parsed CSV rows.
   * @param int $store_id
   *   Store ID.
   *
   * @throws \Exception
   *   If any image cannot be downloaded.
   */
  public function verifyImagesDownloadable(
      array $rows,
      int $store_id
    ): void {

      $logger = $this->loggerFactory->get('grocery_import');

      /*
      * URL => CSV line numbers.
      *
      * This also prevents downloading/checking the same URL more than once.
      */
      $images = [];

      foreach ($rows as $index => $row) {
        $image_url = trim($row['image_url'] ?? '');

        if ($image_url === '') {
          continue;
        }

        /*
        * Adjust this if parseCsv() already stores the real CSV line.
        */
        $line = $row['_line'] ?? ($index + 2);

        $images[$image_url][] = $line;
      }

      if (empty($images)) {
        $logger->notice(
          'Image pre-flight validation passed for store @store. No images found.',
          [
            '@store' => $store_id,
          ]
        );

        return;
      }

      /*
      * Check every unique image.
      */
      foreach ($images as $image_url => $lines) {
        $result = $this->checkImageDownloadable($image_url);

        if (!$result['valid']) {
          $line_list = implode(', ', $lines);

          $reason = sprintf(
            'Image is not downloadable. CSV line(s): %s. URL: %s. Reason: %s',
            $line_list,
            $image_url,
            $result['reason']
          );

          /*
          * Log BEFORE throwing.
          */
          $logger->error(
            'Store @store image pre-flight failed: @reason',
            [
              '@store' => $store_id,
              '@reason' => $reason,
            ]
          );

          throw new \RuntimeException($reason);
        }
      }

      $logger->notice(
        'Image pre-flight validation passed for store @store. @count unique images checked.',
        [
          '@store' => $store_id,
          '@count' => count($images),
        ]
      );
    }


  public function processRows(array $rows, int $store_id): int {
    $products_imported = 0;

    foreach ($rows as $index => $data) {
      $line = $index + 2;

      $errors = $this->validateRow($data, $line);

      if (!empty($errors)) {
        foreach ($errors as $error) {
          $this->logger()->warning($error);
        }

        continue;
      }

      if (empty($data['variation_sku'])) {
        continue;
      }

      $context = [
        'line' => $line,
        'store_id' => $store_id,
      ];

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

      $products_imported++;
    }

    return $products_imported;
  }

  public function validateRow(
    array $row,
    int $line
  ): array {
    $errors = [];

    /*
     * Required fields.
     */
    $errors = array_merge(
      $errors,
      $this->validateRequiredFields($row, $line)
    );

    /*
     * Price.
     */
    $errors = array_merge(
      $errors,
      $this->validatePrice(
        $row['price'] ?? '',
        $line
      )
    );

    /*
     * Image URL syntax.
     *
     * Actual image existence is handled separately.
     */
    if (!empty($row['image_url'])) {
      if (!filter_var(
        trim($row['image_url']),
        FILTER_VALIDATE_URL
      )) {
        $errors[] = sprintf(
          'Line %d: Image URL "%s" is not a valid URL.',
          $line,
          $row['image_url']
        );
      }
    }

    return $errors;
  }

  public function validateSheet(
    string $csv,
    int $store_id
  ): array {
    $errors = [];

    /*
     * Parse CSV.
     */
    $rows = $this->parseCsv($csv);

    /*
     * Validate required columns.
     */
    $required_columns = [
      'product_name',
      'price',
      'image_url',
    ];

    if (empty($rows)) {
      return [
        'valid' => FALSE,
        'errors' => [
          'Google Sheet contains no product rows.',
        ],
        'rows_checked' => 0,
        'images_checked' => 0,
        'images_cached' => 0,
      ];
    }

    $first_row = $rows[array_key_first($rows)];

    foreach ($required_columns as $column) {
      if (!array_key_exists($column, $first_row)) {
        $errors[] = sprintf(
          'Required CSV column "%s" is missing.',
          $column
        );
      }
    }

    /*
     * If the CSV structure itself is wrong, stop here.
     */
    if (!empty($errors)) {
      return [
        'valid' => FALSE,
        'errors' => $errors,
        'rows_checked' => 0,
        'images_checked' => 0,
        'images_cached' => 0,
      ];
    }

    /*
     * Track unique images.
     *
     * URL => lines where it occurs.
     */
    $images = [];

    foreach ($rows as $index => $row) {
      $line = $row['_line'] ?? ($index + 2);

      /*
       * Validate ordinary fields.
       */
      $row_errors = $this->validateRow(
        $row,
        $line
      );

      $errors = array_merge(
        $errors,
        $row_errors
      );

      /*
       * Collect images.
       */
      $image_url = trim(
        (string) ($row['image_url'] ?? '')
      );

      if ($image_url !== '') {
        $images[$image_url][] = $line;
      }
    }

    /*
     * Validate unique images.
     */
    $images_checked = 0;
    $images_cached = 0;

    foreach ($images as $image_url => $lines) {
      $result = $this->checkImageDownloadable(
        $image_url
      );

      $images_checked++;

      if ($result['cached']) {
        $images_cached++;
      }

      if (!$result['valid']) {
        $line_numbers = implode(
          ', ',
          $lines
        );

        $errors[] = sprintf(
          'Line(s) %s: Image %s does not exist or is not downloadable. %s',
          $line_numbers,
          $image_url,
          $result['reason']
        );
      }
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'rows_checked' => count($rows),
      'images_checked' => $images_checked,
      'images_cached' => $images_cached,
    ];
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

      $this->loggerFactory
      ->get('grocery_import')
      ->notice(
        'Updating variation @variation_id SKU @sku with image file @file_id',
        [
          '@variation_id' => $variation->id(),
          '@sku' => $data['variation_sku'],
          '@file_id' => $file ? $file->id() : 'none',
        ]
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
   * Download an image, using a URL-based local cache.
   *
   * The same image URL will only be downloaded once.
   *
   * @param string $url
   *   Image URL.
   * @param array $context
   *   Import context.
   *
   * @return \Drupal\file\FileInterface|null
   *   File entity or NULL on failure.
   */
  public function downloadImage(
    string $url,
    array $context = []
  ) {
    $logger = $this->loggerFactory->get('grocery_import');

    $store_id = $context['store_id'] ?? 0;
    $line = $context['line'] ?? 0;

    $hash = hash('sha256', $url);

    $path = parse_url($url, PHP_URL_PATH);
    $extension = strtolower(
      pathinfo($path ?? '', PATHINFO_EXTENSION)
    );

    $allowed_extensions = [
      'jpg',
      'jpeg',
      'png',
      'gif',
      'webp',
      'avif',
    ];

    if (!in_array($extension, $allowed_extensions, TRUE)) {
      $extension = 'jpg';
    }

    $directory = 'public://grocery_import_images';

    $this->fileSystem->prepareDirectory(
      $directory,
      FileSystemInterface::CREATE_DIRECTORY |
      FileSystemInterface::MODIFY_PERMISSIONS
    );

    $destination = $directory . '/' . $hash . '.' . $extension;

    /*
     * ------------------------------------------------------------
     * 1. Check whether Drupal already has the file entity.
     * ------------------------------------------------------------
     */
    $files = $this->entityTypeManager
      ->getStorage('file')
      ->loadByProperties([
        'uri' => $destination,
      ]);

    if (!empty($files)) {
      $file = reset($files);

      $logger->debug(
        'Reusing existing image for store @store, line @line: @url',
        [
          '@store' => $store_id,
          '@line' => $line,
          '@url' => $url,
        ]
      );

      return $file;
    }

    /*
     * ------------------------------------------------------------
     * 2. Check whether the physical file exists but the
     *    Drupal file entity doesn't.
     * ------------------------------------------------------------
     */
    if ($this->fileSystem->getFileExists($destination)) {
      $logger->warning(
        'Image file exists without a Drupal file entity. Re-downloading: @destination',
        [
          '@destination' => $destination,
        ]
      );
    }

    /*
     * ------------------------------------------------------------
     * 3. Download only if it isn't already cached.
     * ------------------------------------------------------------
     */
    try {
      $response = $this->httpClient->request(
        'GET',
        $url,
        [
          'timeout' => 30,
          'connect_timeout' => 10,
          'http_errors' => FALSE,
          'allow_redirects' => TRUE,
          'headers' => [
            'User-Agent' => 'Drupal Grocery Import',
            'Accept' => 'image/*,*/*',
          ],
        ]
      );

      $status = $response->getStatusCode();

      if ($status < 200 || $status >= 300) {
        throw new \RuntimeException(
          "HTTP {$status}"
        );
      }

      $data = $response
        ->getBody()
        ->getContents();

      if ($data === '') {
        throw new \RuntimeException(
          'Image response body is empty.'
        );
      }

      $file = $this->fileRepository->writeData(
        $data,
        $destination,
        FileSystemInterface::EXISTS_REPLACE
      );

      /*
       * Make the file permanent.
       */
      $file->setPermanent();
      $file->save();

      $logger->debug(
        'Downloaded image for store @store, line @line: @url',
        [
          '@store' => $store_id,
          '@line' => $line,
          '@url' => $url,
        ]
      );

      return $file;
    }
    catch (\Throwable $e) {
      $logger->error(
        'Image download failed. Store @store, CSV line @line, URL @url: @reason',
        [
          '@store' => $store_id,
          '@line' => $line,
          '@url' => $url,
          '@reason' => $e->getMessage(),
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
   * Verify that the CSV contains all required columns.
   *
   * @param array $header
   *   CSV header.
   *
   * @throws \Exception
   *   When a required column is missing.
   */
  public function verifyRequiredFields(array $header): void {
    $header = array_map('trim', $header);

    $required = [
      'product_id',
      'product_name',
      'variation_sku',
    ];

    $missing = [];

    foreach ($required as $field) {
      if (!in_array($field, $header, TRUE)) {
        $missing[] = $field;
      }
    }

    if (!empty($missing)) {
      throw new \Exception(
        'CSV is missing required columns: ' . implode(', ', $missing)
      );
    }
  }

  public function validateGoogleSheet(
    string $url,
    ?string $gid = NULL
  ): array {
    $errors = [];

    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      return [
        'valid' => FALSE,
        'errors' => [
          'Google Sheet URL is not a valid URL.',
        ],
        'csv_url' => NULL,
      ];
    }

    $parts = parse_url($url);

    if (
      empty($parts['host']) ||
      !in_array(
        strtolower($parts['host']),
        [
          'docs.google.com',
          'docs.googleusercontent.com',
        ],
        TRUE
      )
    ) {
      return [
        'valid' => FALSE,
        'errors' => [
          'URL is not a Google Sheets URL.',
        ],
        'csv_url' => NULL,
      ];
    }

    /*
     * Convert the supplied Google Sheet URL to a public CSV URL.
     */
    $csv_url = $this->buildGoogleSheetCsvUrlFromInput(
      $url,
      $gid
    );

    try {
      $response = $this->httpClient->request(
        'GET',
        $csv_url,
        [
          'timeout' => 20,
          'connect_timeout' => 10,
          'http_errors' => FALSE,
          'allow_redirects' => TRUE,
          'headers' => [
            'User-Agent' => 'Drupal Grocery Import Validator/1.0',
            'Accept' => 'text/csv,text/plain,*/*',
          ],
        ]
      );

      $status = $response->getStatusCode();

      if ($status < 200 || $status >= 300) {
        $errors[] = sprintf(
          'Google Sheet returned HTTP %d.',
          $status
        );
      }

      $csv = (string) $response->getBody();

      if (trim($csv) === '') {
        $errors[] = 'Google Sheet returned an empty CSV.';
      }

      /*
       * If Google responds with HTML/login page instead of CSV,
       * this is not a publicly accessible sheet.
       */
      $content_type = strtolower(
        $response->getHeaderLine('Content-Type')
      );

      if (
        str_contains($content_type, 'text/html') &&
        !str_contains($csv, ',')
      ) {
        $errors[] =
          'Google Sheet did not return CSV data. ' .
          'Make sure the sheet is publicly accessible.';
      }
    }
    catch (\Throwable $e) {
      $errors[] = sprintf(
        'Unable to access Google Sheet: %s',
        $e->getMessage()
      );

      $csv = '';
    }

    return [
      'valid' => empty($errors),
      'errors' => $errors,
      'csv_url' => $csv_url,
      'csv' => $csv ?? '',
    ];
  }

  protected function validateRequiredFields(
    array $row,
    int $line
  ): array {
    $errors = [];

    $required = [
      'product_name' => 'Product name',
      'price' => 'Price',
      'image_url' => 'Image URL',
    ];

    foreach ($required as $key => $label) {
      if (
        !isset($row[$key]) ||
        trim((string) $row[$key]) === ''
      ) {
        $errors[] = sprintf(
          'Line %d: %s is required.',
          $line,
          $label
        );
      }
    }

    return $errors;
  }

  protected function validatePrice(
    mixed $price,
    int $line
  ): array {
    $price = trim((string) $price);

    if ($price === '') {
      return [
        sprintf(
          'Line %d: Price is required.',
          $line
        ),
      ];
    }

    /*
     * Accept:
     * 10
     * 10.5
     * 10.50
     *
     * Reject:
     * ₹10
     * 10/kg
     * 1,000
     */
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $price)) {
      return [
        sprintf(
          'Line %d: Price "%s" is not a valid number.',
          $line,
          $price
        ),
      ];
    }

    if ((float) $price < 0) {
      return [
        sprintf(
          'Line %d: Price cannot be negative.',
          $line
        ),
      ];
    }

    return [];
  }

  public function clearImageValidationCache(): void {
    $this->imageValidationCache->deleteAll();
  }

}
