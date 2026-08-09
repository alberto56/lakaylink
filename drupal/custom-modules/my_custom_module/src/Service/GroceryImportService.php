<?php

namespace Drupal\my_custom_module\Service;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\file\FileRepositoryInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\taxonomy\Entity\Term;
use GuzzleHttp\ClientInterface;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;

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

  /**
   * The database connection.
   *
   * Used to read and write custom grocery import status information
   * stored in the module's database tables.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $database;

  /**
   * The Drupal file system service.
   *
   * Used for creating directories, managing file paths, and performing
   * file-system operations during image imports.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected FileSystemInterface $fileSystem;

  /**
   * The cache backend for image validation results.
   *
   * Used to cache image validation results and avoid repeatedly
   * validating the same image.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected CacheBackendInterface $imageValidationCache;

  /**
   * The key-value store factory.
   *
   * Used to access persistent key-value storage for import-related
   * configuration or state data.
   *
   * @var \Drupal\Core\KeyValueStore\KeyValueFactoryInterface
   */
  protected KeyValueFactoryInterface $keyValueFactory;

  /**
   * The Drupal time service.
   *
   * Used to retrieve the current request time without using
   * Drupal's static service locator.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

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
    KeyValueFactoryInterface $keyValueFactory,
    TimeInterface $time,
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
    $this->time = $time;
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

  /**
   * Builds a CSV export URL for a store's Google Sheet.
   *
   * Reads the Google Sheet URL and optional tab GID from the Commerce Store
   * fields and converts the URL into a CSV export URL. If the provided URL
   * is already configured to return CSV output, it is returned unchanged.
   *
   * @param \Drupal\commerce_store\Entity\Store $store
   *   The Commerce Store containing the Google Sheet URL and tab GID.
   *
   * @return string
   *   The Google Sheet CSV export URL.
   *
   * @throws \Exception
   *   Thrown when the Google Sheet URL is missing or invalid.
   */
  public function buildGoogleSheetCsvUrl($store): string {
    // Get and clean the Google Sheet URL from the store.
    $url = trim(
      $store->get('field_google_sheet_url')->uri ?? ''
    );

    // Get and clean the optional Google Sheet tab GID.
    $gid = trim(
      $store->get('field_google_sheet_tab_gid')->value ?? ''
    );

    // Ensure that a Google Sheet URL has been configured.
    if ($url === '') {
      throw new \Exception(
        "Google Sheet URL is missing for store {$store->id()}."
      );
    }

    // Validate that the configured value is a valid URL.
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new \Exception(
        "Invalid Google Sheet URL: {$url}"
      );
    }

    // Parse the URL into its individual components.
    $parts = parse_url($url);

    // Parse the existing query parameters so they can be preserved
    // when building the CSV export URL.
    parse_str(
      $parts['query'] ?? '',
      $query
    );

    /*
     * If the URL is already configured to return CSV output
     * return it without modifying the URL.
     */
    if (
      isset($query['output']) &&
      $query['output'] === 'csv'
    ) {
      return $url;
    }

    /*
     * Configure the Google Sheet export format as CSV.
     */
    $query['format'] = 'csv';

    // Add the configured tab GID when one is available.
    if ($gid !== '') {
      $query['gid'] = $gid;
    }

    // Rebuild the query string with the updated parameters.
    $parts['query'] = http_build_query($query);

    // Reconstruct the URL from the parsed components.
    $result =
      ($parts['scheme'] ?? 'https') . '://' .
      ($parts['host'] ?? '') .
      ($parts['path'] ?? '');

    // Append the query string when parameters are present.
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
   * Fetches CSV data from a remote URL.
   *
   * Sends an HTTP GET request to the provided URL and returns the response
   * body when the request is successful. HTTP redirects are followed and
   * HTTP errors are handled manually so that a consistent exception can
   * be thrown for unsuccessful responses.
   *
   * @param string $url
   *   The URL from which the CSV data should be fetched.
   *
   * @return string
   *   The CSV data returned by the remote server.
   *
   * @throws \Exception
   *   Thrown when the remote server returns a non-2xx response, the CSV
   *   response is empty, or the URL cannot be accessed.
   */
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
  protected function validateAllImages(array $rows, int $store_id): array {
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

  /**
   * Checks whether an image URL is downloadable and returns validation details.
   *
   * The URL is first checked against the image validation cache. Cached
   * validation results are reused for 24 hours.
   *
   * If the URL is not cached, this method:
   * - Validates the URL syntax.
   * - Performs an HTTP GET request to the URL.
   * - Follows up to 5 HTTP/HTTPS redirects.
   * - Forces IPv4 connections for environments without IPv6 support.
   * - Verifies that the HTTP response has a successful 2xx status.
   * - Verifies that the response Content-Type starts with "image/".
   * - Reads a small portion of the response body to ensure it is not empty.
   *
   * Deterministic validation failures, such as invalid URLs, HTTP errors,
   * invalid Content-Type values, and empty responses, are cached for 24 hours.
   * Network-related failures are not cached so that subsequent imports can
   * retry the request.
   *
   * @param string $url
   *   The image URL to validate.
   *
   * @return array
   *   {
   *     valid: bool,
   *     reason: string|null,
   *     cached: bool
   *   }
   *   An associative array containing:
   *   - valid: Whether the URL appears to point to a downloadable image.
   *   - reason: A human-readable reason when validation fails, or NULL when
   *     validation succeeds.
   *   - cached: Whether the result was retrieved from the validation cache.
   */
  protected function checkImageDownloadable(string $url): array {
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
        'started' => $this->time->getRequestTime(),
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
    int $products_imported,
  ): void {
    $this->database
      ->update('my_custom_module_products_import_status')
      ->fields([
        'status' => 'completed',
        'finished' => $this->time->getRequestTime(),
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
    string $reason,
  ): void {
    $this->database
      ->update('my_custom_module_products_import_status')
      ->fields([
        'status' => 'failed',
        'finished' => $this->time->getRequestTime(),
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
    int $store_id,
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

  /**
   * Processes imported CSV rows for a store.
   *
   * Validates each imported row and creates or updates the corresponding
   * product and product variation. Rows with validation errors, missing
   * variation SKUs, or unsupported languages are skipped.
   *
   * @param array $rows
   *   The imported CSV rows containing product data.
   * @param int $store_id
   *   The Commerce Store ID associated with the import.
   *
   * @return int
   *   The number of products successfully processed.
   */
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

  /**
   * Validates a single CSV import row.
   *
   * Performs validation for the fields required to import a grocery product,
   * including required fields, price formatting, and image URL syntax.
   *
   * Image existence or accessibility is not checked here. That validation
   * is handled separately when the image is processed.
   *
   * @param array $row
   *   The CSV row containing the product data.
   * @param int $line
   *   The CSV line number being validated.
   *
   * @return array
   *   An array of validation error messages. Returns an empty array when
   *   the row passes all validations.
   */
  public function validateRow(
    array $row,
    int $line,
  ): array {
    // Store all validation errors found for this row.
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
    $langcode = 'en',
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
    $langcode = 'en',
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
    $langcode = 'en',
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
    $context = [],
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
          'alt' => $data['variant_name'] ?? $data['product_name'],
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
    $variation,
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
    array $context = [],
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
    $langcode = 'en',
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

  /**
   * Validates that a Google Sheet is accessible and returns CSV data.
   *
   * Validates the supplied URL, ensures that it belongs to a supported
   * Google Sheets domain, converts it to a CSV export URL, and attempts
   * to fetch the CSV content.
   *
   * The method also detects common cases where Google returns an HTML
   * login or access page instead of the expected CSV response.
   *
   * @param string $url
   *   The Google Sheet URL to validate.
   * @param string|null $gid
   *   The optional Google Sheet tab GID used to select a specific sheet tab.
   *
   * @return array
   *   An array containing:
   *   - valid: TRUE if the Google Sheet is accessible and valid,
   *     FALSE otherwise.
   *   - errors: An array of validation or access error messages.
   *   - csv_url: The generated CSV export URL, or NULL when the URL itself
   *     is invalid.
   *   - csv: The CSV content returned by Google, or an empty string on failure.
   */
  public function validateGoogleSheet(
    string $url,
    ?string $gid = NULL,
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

  /**
   * Validates that all required import fields are present.
   *
   * Checks the current CSV row for the fields required to create or
   * update a grocery product. An error message is returned for each
   * missing or empty field.
   *
   * @param array $row
   *   The imported CSV row containing product data.
   * @param int $line
   *   The CSV line number being validated.
   *
   * @return array
   *   An array of validation error messages. Returns an empty array
   *   when all required fields are present.
   */
  protected function validateRequiredFields(
    array $row,
    int $line,
  ): array {
    // Store validation errors found in the current row.
    $errors = [];

    // Define the required CSV fields and their human-readable labels.
    $required = [
      'product_name' => 'Product name',
      'price' => 'Price',
      'image_url' => 'Image URL',
    ];

    // Check each required field for a missing or empty value.
    foreach ($required as $key => $label) {
      if (
        !isset($row[$key]) ||
        trim((string) $row[$key]) === ''
      ) {
        // Add a descriptive error containing the CSV line number
        // and the name of the missing field.
        $errors[] = sprintf(
          'Line %d: %s is required.',
          $line,
          $label
        );
      }
    }

    // Return all validation errors found for this row.
    return $errors;
  }

  /**
   * Validates a product price from the import data.
   *
   * Ensures that the price is provided and contains only a valid numeric
   * value with an optional decimal portion of up to two digits.
   *
   * @param mixed $price
   *   The price value to validate.
   * @param int $line
   *   The CSV line number where the price was found.
   *
   * @return array
   *   An array of validation error messages. Returns an empty array when
   *   the price is valid.
   */
  protected function validatePrice(
    mixed $price,
    int $line,
  ): array {
    // Convert the price to a string and remove surrounding whitespace.
    $price = trim((string) $price);

    // Ensure that a price value has been provided.
    if ($price === '') {
      return [
        sprintf(
          'Line %d: Price is required.',
          $line
        ),
      ];
    }

    /*
     * Accept valid numeric prices such as:
     * 10
     * 10.5
     * 10.50
     *
     * Reject values containing currency symbols, units, commas,
     * or more than two decimal places, such as:
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

    // Prevent negative prices.
    //
    // Note: The regular expression above already rejects negative values,
    // so this check is currently defensive and may be useful if the
    // validation rule is changed in the future.
    if ((float) $price < 0) {
      return [
        sprintf(
          'Line %d: Price cannot be negative.',
          $line
        ),
      ];
    }

    // Return an empty array when the price passes all validations.
    return [];
  }

  /**
   * Clears all cached image validation results.
   *
   * Removes all entries from the image validation cache so that images
   * will be validated again the next time they are processed.
   */
  public function clearImageValidationCache(): void {
    // Delete all entries from the image validation cache.
    $this->imageValidationCache->deleteAll();
  }

}
