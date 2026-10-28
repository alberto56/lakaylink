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
   * {@inheritdoc}
   */
  public function getCurrencyCode(string $country_code): ?string {
    return $this->currencies[strtoupper($country_code)] ?? NULL;
  }

}
