<?php

namespace Drupal\commerce_product\Entity;

interface ProductVariationInterface {

  public function language();

  public function hasTranslation(string $langcode): bool;

  public function getTranslation(string $langcode): static;

  public function addTranslation(string $langcode, array $values = []): static;

}

if (!class_exists(ProductVariation::class)) {
    class ProductVariation {
      /**
       * Creates a product variation.
       *
       * @param array<string, mixed> $values
       *
       * @return ProductVariationInterface
       */
      public static function create(array $values): ProductVariationInterface {
        return new class implements ProductVariationInterface {
          public function language(): \Drupal\Core\Language\LanguageInterface {
            throw new \LogicException('PHPStan mock.');
          }
        };
      }
    }
  }
