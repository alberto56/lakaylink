<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Tests\Fixtures;

use Drupal\ip2country\Ip2CountryLookupInterface;

/**
 * Mock implementation of the IP-to-country lookup interface.
 */
class Ip2CountryLookupMock implements Ip2CountryLookupInterface {

  /**
   * {@inheritdoc}
   */
  public function getCountry($ip) {
    $mock_countries = [
      '176.31.84.249' => 'FR',
      '152.57.129.130' => 'IN',
    ];

    return $mock_countries[$ip] ?? FALSE;
  }

}
