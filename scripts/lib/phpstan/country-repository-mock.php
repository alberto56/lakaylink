<?php

declare(strict_types=1);

namespace CommerceGuys\Addressing\Country;

/**
 * Mock country repository implementation.
 */
class CountryRepository {

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

}

/**
 * Mock country repository implementation.
 */
class Country {

  /**
   * Constructs the mock country.
   *
   * @param string|null $currencyCode
   *   The currency code to return.
   */
  public function __construct(
    private readonly ?string $currencyCode = 'EUR',
  ) {}

  /**
   * Gets the currency code.
   */
  public function getCurrencyCode(): ?string {
    return $this->currencyCode;
  }

}
