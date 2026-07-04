<?php

/**
 * @file
 * Contains the GenerateCodeController class.
 */

namespace Drupal\my_custom_module\Controller;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\commerce_store\Entity\StoreInterface;
use Drupal\my_custom_module\Service\InvitationCodeGenerator;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Handles AJAX generation of invitation codes for commerce stores.
 */
class GenerateCodeController extends ControllerBase {

  /**
   * Constructs a GenerateCodeController object.
   *
   * @param \Drupal\my_custom_module\Service\InvitationCodeGenerator $codeGenerator
   *   The invitation code generator service.
   */
  public function __construct(
    protected InvitationCodeGenerator $codeGenerator,
  ) {}

  /**
   * Creates a new controller instance.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The service container.
   *
   * @return static
   *   A new GenerateCodeController instance.
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('my_custom_module.invitation_code'),
    );
  }

  /**
   * Generates an invitation code via AJAX and injects it into the page.
   *
   * @param \Drupal\commerce_store\Entity\StoreInterface $commerce_store
   *   The commerce store entity.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The AJAX response containing the generated code.
   */
  public function generate(StoreInterface $commerce_store): AjaxResponse {

    // Generate a signed invitation code for the given store.
    $code = $this->codeGenerator->generate($commerce_store);

    // Prepare AJAX response.
    $response = new AjaxResponse();

    // Replace the target HTML element with the generated code.
    $response->addCommand(
      new HtmlCommand(
        '#invitation-code-' . $commerce_store->id(),
        $code,
      )
    );

    return $response;
  }

}
