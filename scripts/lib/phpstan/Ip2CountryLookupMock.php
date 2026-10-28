<?php

namespace Drupal\ip2country;

interface Ip2CountryLookupInterface {

  public function getCountry($ip_address = NULL): string|false;

}

// if (!class_exists(ProductVariation::class)) {
//       class ProductVariation {
//         /**
//          * Creates a product variation.
//          *
//          * @param array<string, mixed> $values
//          *
//          * @return ProductVariationInterface
//          */
//         public static function create(array $values): ProductVariationInterface {
//           return new class implements ProductVariationInterface {
//             public function language(): \Drupal\Core\Language\LanguageInterface {
//               throw new \LogicException('PHPStan mock.');
//             }
//           };
//         }
//       }
// }



/**
 * Mock implementation of Ip2CountryLookupInterface.
 */
// class Ip2CountryLookupMock implements Ip2CountryLookupInterface {

//   /**
//    * Mock IP-to-country mappings.
//    *
//    * @var array<string, string>
//    */
//   private array $countries = [
//     '176.31.84.249' => 'FR',
//     '152.57.129.130' => 'IN',
//     '8.8.8.8' => 'US',
//     '1.1.1.1' => 'AU',
//     '24.48.0.1' => 'CA',
//   ];

//   /**
//    * {@inheritdoc}
//    */
//   public function getCountry($ip_address = NULL): string|false {
//     if ($ip_address === NULL) {
//       return FALSE;
//     }

//     $ip = (string) $ip_address;

//     return $this->countries[$ip] ?? FALSE;
//   }

// }
