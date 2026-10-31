<?php

declare(strict_types=1);

namespace Drupal\ip2country;

/**
 * Interface for the mock IP-to-country lookup.
 */
interface Ip2CountryLookupInterface {

  /**
   * Gets the country code for an IP address.
   *
   * @param string|int|null $ip_address
   *   The IP address to look up.
   *
   * @return string|false
   *   The two-letter country code, or FALSE if not found.
   */
  public function getCountry($ip_address = NULL): string|false;

}

/**
 * Mock implementation of the IP-to-country lookup.
 */
class Ip2CountryLookup implements Ip2CountryLookupInterface {

  /**
   * IP-to-country mappings.
   *
   * @var array<string, string>
   */
  private array $countries = [
    '176.31.84.249' => 'FR',
    '152.57.129.130' => 'IN',
    '8.8.8.8' => 'US',
    '24.48.0.1' => 'CA',
  ];

  /**
   * {@inheritdoc}
   */
  public function getCountry($ip_address = NULL): string|false {
    if ($ip_address === NULL) {
      return FALSE;
    }

    return $this->countries[(string) $ip_address] ?? FALSE;
  }

}
