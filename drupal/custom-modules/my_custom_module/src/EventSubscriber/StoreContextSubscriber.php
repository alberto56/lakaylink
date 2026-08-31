<?php

namespace Drupal\my_custom_module\EventSubscriber;

use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Event\RequestEvent;

/**
 * Subscribes to kernel request events to store active store context in session.
 *
 * This subscriber inspects incoming requests and, if the URL matches
 * a shop pattern (/shop/{slug}/{id}), it extracts the store slug and ID
 * and stores them in the user session for later use across requests.
 */
class StoreContextSubscriber implements EventSubscriberInterface {

 /**
   * Constructs a StoreContextSubscriber object.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The cache tags invalidator service.
   */
  public function __construct(
    protected CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {}

  /**
   * Reacts to the kernel request event.
   *
   * This method checks if the current request path matches the pattern:
   * /shop/{slug}/{id}
   *
   * If matched, it extracts:
   * - slug: the store slug (URL-friendly name)
   * - id: the numeric store identifier
   *
   * These values are then stored in the session under:
   * - active_store_slug
   * - active_store_id
   *
   * @param \Symfony\Component\HttpKernel\Event\RequestEvent $event
   *   The request event object containing the current HTTP request.
   */
  public function onRequest(RequestEvent $event) {
    $request = $event->getRequest();

    // Get session from current request.
    $session = $request->getSession();

    // Extract the path portion of the URL (e.g., /shop/store-name/123).
    $path = $request->getPathInfo();
    // Match shop URL pattern: /shop/{slug}/{id}.
    if (preg_match('#^/store/(\d+)(/.*)?$#', $path, $matches)) {
      $slug = $matches[0];
      $id = $matches[1];
      // Store extracted values in session for later use.
      $session->set('active_store_slug', $slug);
      $session->set('active_store_id', (int) $id);

      // Invalidate main menu cache.
      $this->cacheTagsInvalidator->invalidateTags([
        'config:system.menu.main',
      ]);
    }
  }

  /**
   * {@inheritdoc}
   *
   * Registers the subscriber to listen to kernel request events.
   *
   * Priority 100 ensures this runs early in the request lifecycle,
   * before most routing and controller logic.
   *
   * @return array<string, array{0: string, 1: int}>
   *   The event subscription configuration.
   */
  public static function getSubscribedEvents() {
    return [
      KernelEvents::REQUEST => ['onRequest', 100],
    ];
  }

}
