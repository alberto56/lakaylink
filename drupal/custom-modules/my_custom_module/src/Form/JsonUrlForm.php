<?php

declare(strict_types=1);

namespace Drupal\my_custom_module\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configure My custom module settings for this site.
 */
final class JsonUrlForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'my_custom_module_json_url';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['my_custom_module.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $form['frontend_footer_menu_json'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Frontend Footer Menu Json'),
      '#default_value' => $this->config('my_custom_module.settings')->get('frontend_footer_menu_json'),
      '#required' => true,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('my_custom_module.settings')
      ->set('frontend_footer_menu_json', $form_state->getValue('frontend_footer_menu_json'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
