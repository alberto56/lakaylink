<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Tests\Fixtures;

use CommerceGuys\Addressing\Country\Country;
use CommerceGuys\Addressing\Country\CountryRepository;

/**
 * Mock country repository for testing.
 */
class CountryRepositoryMock extends CountryRepository {

  /**
   * Mock country data.
   *
   * @var array<string, string>
   */
  private array $countries = [
    'FR' => 'EUR',
    'CA' => 'CAD',
    'IN' => 'INR',
    'US' => 'USD',
  ];

  /**
   * {@inheritdoc}
   */
  public function get(string $countryCode): Country {
    $countryCode = strtoupper($countryCode);

    if (!isset($this->countries[$countryCode])) {
      throw new \InvalidArgumentException(
        sprintf('Unknown country code: %s', $countryCode)
      );
    }

    // Country creation depends on the installed Commerce Guys Addressing
    // version. Use the real repository for constructing Country objects.
    return parent::get($countryCode);
  }

}
