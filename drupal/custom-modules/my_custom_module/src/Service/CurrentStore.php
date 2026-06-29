<?php

namespace Drupal\my_custom_module\Service;

use Symfony\Component\HttpFoundation\RequestStack;

class CurrentStore {

  public function __construct(private RequestStack $requestStack) {}

  public function getStoreId(): ?int {
    return $this->requestStack->getCurrentRequest()->getSession()->get('active_store_id');
  }

  public function getStoreSlug(): ?string {
    return $this->requestStack->getCurrentRequest()->getSession()->get('active_store_slug');
  }
}