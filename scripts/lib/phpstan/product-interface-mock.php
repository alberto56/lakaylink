<?php

namespace Drupal\commerce_product\Entity;

if (!interface_exists(ProductInterface::class)) {
  interface ProductInterface {

    /**
     * Returns the entity's language.
     *
     * @return \Drupal\Core\Language\LanguageInterface
     */
    public function language();

    /**
     * Checks whether the entity has a translation.
     *
     * @param string $langcode
     *   The language code.
     *
     * @return bool
     */
    public function hasTranslation(string $langcode): bool;

    /**
     * Gets a translation.
     *
     * @param string $langcode
     *   The language code.
     *
     * @return static
     */
    public function getTranslation(string $langcode): static;

    /**
     * Adds a translation.
     *
     * @param string $langcode
     *   The language code.
     *
     * @return static
     */
    public function addTranslation(string $langcode, array $values = []): static;

  }
}
