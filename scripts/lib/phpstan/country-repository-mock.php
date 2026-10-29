<?php

declare(strict_types=1);

namespace CommerceGuys\Addressing\Country;

/**
 * Mock country repository implementation.
 */
class CountryRepository {

  /**
   * Country-to-currency mappings.
   *
   * @var array<string, string>
   */
  private array $currencies = [
    'FR' => 'EUR',
    'DE' => 'EUR',
    'CA' => 'CAD',
    'IN' => 'INR',
    'US' => 'USD',
  ];

  /**
   * Mock country definitions.
   *
   * @var array<string, array<string, string>>
   */
  private array $mockCountries = [
    'FR' => [
      'name' => 'France',
      'three_letter_code' => 'FRA',
      'numeric_code' => '250',
      'currency_code' => 'EUR',
    ],
    'CA' => [
      'name' => 'Canada',
      'three_letter_code' => 'CAN',
      'numeric_code' => '124',
      'currency_code' => 'CAD',
    ],
    'IN' => [
      'name' => 'India',
      'three_letter_code' => 'IND',
      'numeric_code' => '356',
      'currency_code' => 'INR',
    ],
    'US' => [
      'name' => 'United States',
      'three_letter_code' => 'USA',
      'numeric_code' => '840',
      'currency_code' => 'USD',
    ],
  ];

  /**
   * {@inheritdoc}
   */
  public function get(
    string $countryCode,
    ?string $locale = NULL,
  ): Country {
    $countryCode = strtoupper($countryCode);

    if (!isset($this->mockCountries[$countryCode])) {
      throw new UnknownCountryException($countryCode);
    }

    $definition = $this->mockCountries[$countryCode];

    return new Country([
      'country_code' => $countryCode,
      'name' => $definition['name'],
      'three_letter_code' => $definition['three_letter_code'],
      'numeric_code' => $definition['numeric_code'],
      'currency_code' => $definition['currency_code'],
      'locale' => $locale ?? 'en',
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCurrencyCode(string $country_code): ?string {
    return $this->currencies[strtoupper($country_code)] ?? NULL;
  }

}


/**
 * Mock country repository implementation.
 */
class Country {

  /**
   * Country-to-currency mappings.
   *
   * @var array<string, string>
   */
  private array $currencies = [
    'FR' => 'EUR',
    'DE' => 'EUR',
    'CA' => 'CAD',
    'IN' => 'INR',
    'US' => 'USD',
  ];

  /**
   * {@inheritdoc}
   */
  public function getCurrencyCode(string $country_code): ?string {
    return $this->currencies[strtoupper($country_code)] ?? NULL;
  }

}
