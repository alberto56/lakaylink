
<?php

declare(strict_types=1);

namespace CommerceGuys\Addressing\Country;

/**
 * Interface for the mock country repository.
 */
// interface CountryRepositoryInterface {

//   /**
//    * Gets a country's currency code.
//    *
//    * @param string $country_code
//    *   The ISO country code.
//    *
//    * @return string|null
//    *   The currency code, or NULL if not found.
//    */
//   public function getCurrencyCode(string $country_code): ?string;

// }


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
