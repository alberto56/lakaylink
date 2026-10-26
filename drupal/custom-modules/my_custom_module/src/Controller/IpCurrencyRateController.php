<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Controller;

use CommerceGuys\Addressing\Country\CountryRepository;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\ip2country\Ip2CountryLookupInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Provides an API endpoint for visitor currency exchange rates.
 *
 * Determines a visitor's country using their IP address, maps the country
 * to a supported display currency, and retrieves the latest exchange rate
 * from the Commerce Exchanger database table.
 *
 * USD is the default store currency. CAD and EUR are supported display
 * currencies; all other currencies fall back to USD.
 *
 * The response is marked as non-cacheable because the result depends on
 * the visitor's IP address.
 *
 * @package Drupal\my_custom_module\Controller
 *
 * ex 1:- Indian ip address we should get USD
 *   /api/get-ip-to-currency-rate?SIMULATE-IP=152.57.129.130
 *   {"rates":{},"date":null,"client-currency":"USD"}
 *
 * ex 2:- Indian ip address we should get USD
 *   /api/get-ip-to-currency-rate?SIMULATE-IP=176.31.84.249
 *   {
 *     "rates": {"usd-eur":0.89267},
 *     "date":"2026-10-25-18:55:29 UTC",
 *     "client-currency":"EUR"
 *   }
 */
final class IpCurrencyRateController extends ControllerBase {

  /**
   * Constructs an IpCurrencyRateController object.
   *
   * @param \Drupal\Core\Database\Connection $database_connection
   *   The database connection used to retrieve exchange rates.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   The currently authenticated user.
   * @param \Drupal\ip2country\Ip2CountryLookupInterface $ip2countryLookup
   *   The IP-to-country lookup service.
   */
  public function __construct(
    private readonly Connection $database_connection,
    private readonly AccountProxyInterface $current_user,
    private readonly Ip2CountryLookupInterface $ip2countryLookup,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new self(
      $container->get('database'),
      $container->get('current_user'),
      $container->get('ip2country.lookup'),
    );
  }

  /**
   * Returns the exchange rate for the visitor's display currency.
   *
   * Determines the visitor's country from their IP address and maps its
   * currency to CAD, EUR, or USD. An optional SIMULATE-IP query parameter
   * can be used for testing by users with the site configuration
   * administration permission.
   *
   * If the selected currency is CAD or EUR, the latest matching USD
   * exchange rate is retrieved from the Commerce Exchanger rates table.
   * USD visitors do not require an exchange rate lookup.
   *
   * The response contains the exchange rate, its UTC timestamp, and the
   * selected client currency.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The current HTTP request.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   A JSON response containing the rate, timestamp, and currency.
   *
   * @example
   *   Positive example: A visitor from Canada is assigned CAD, and the
   *   latest USD-to-CAD exchange rate is returned when available.
   *
   * @example
   *   Negative example: An unauthorized visitor supplying a SIMULATE-IP
   *   parameter cannot override their actual client IP address.
   */
  public function getCurrencyRate(Request $request): JsonResponse {
    // Use the client IP address provided by the HTTP request.
    $ip = $request->getClientIp();

    // Permit IP simulation only for users with the required permission.
    // Invalid IP addresses and unauthorized requests are ignored.
    $simulated_ip = $request->query->get('SIMULATE-IP');

    if (
      $simulated_ip !== NULL &&
      $this->current_user->hasPermission('administer site configuration') &&
      filter_var($simulated_ip, FILTER_VALIDATE_IP)
    ) {
      $ip = $simulated_ip;
    }

    $country_code = FALSE;

    // Resolve the visitor's country only when the IP address is valid.
    if ($ip && filter_var($ip, FILTER_VALIDATE_IP)) {
      $country_code = $this->ip2countryLookup->getCountry($ip);
    }

    // Determine the currency associated with the visitor's country.
    $country_currency = NULL;

    if ($country_code) {
      try {
        $country_repository = new CountryRepository();
        $country = $country_repository->get(
          strtoupper($country_code)
        );
        $country_currency = $country->getCurrencyCode();
      }
      catch (\InvalidArgumentException $e) {
        // If the country code is invalid, use the default currency.
        $country_currency = NULL;
      }
    }

    // Support only CAD and EUR as display currencies.
    // Visitors from other countries default to USD.
    $client_currency = match (strtoupper((string) $country_currency)) {
      'CAD' => 'CAD',
      'EUR' => 'EUR',
      default => 'USD',
    };

    $rates = [];
    $timestamp = NULL;

    // USD is the store currency, so fetch a rate only when conversion
    // to CAD or EUR is required.
    if ($client_currency !== 'USD') {
      $row = $this->database_connection
        ->select('commerce_exchanger_latest_rates', 'r')
        ->fields('r', ['value', 'timestamp'])
        ->condition('r.source', 'USD')
        ->condition('r.target', $client_currency)
        ->condition('r.exchanger', 'currencylayer')
        ->orderBy('r.timestamp', 'DESC')
        ->range(0, 1)
        ->execute()
        ->fetchAssoc();

      if ($row) {
        $rate_key = 'usd-' . strtolower($client_currency);
        $rates[$rate_key] = (float) $row['value'];
        $timestamp = (int) $row['timestamp'];
      }
    }

    // Format the exchange rate timestamp in UTC.
    $date = $timestamp
      ? gmdate('Y-m-d-H:i:s', $timestamp) . ' UTC'
      : NULL;

    // Return a consistent JSON structure even when no rate is available.
    $response = new JsonResponse([
      'rates' => (object) $rates,
      'date' => $date,
      'client-currency' => $client_currency,
    ]);

    // Prevent shared caches from serving one visitor's currency response
    // to another visitor.
    $response->headers->set(
      'Cache-Control',
      'private, no-store, no-cache, must-revalidate'
    );

    return $response;
  }

}
