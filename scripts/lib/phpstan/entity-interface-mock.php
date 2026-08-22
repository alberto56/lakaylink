<?php

namespace Drupal\Core\Entity;

interface EntityInterface {

  public function hasTranslation(string $langcode): bool;

  public function getTranslation(string $langcode): static;

  public function addTranslation(string $langcode, array $values = []): static;

}
