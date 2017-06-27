<?php

namespace Drupal\structure_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Import and export form for taxonomy terms.
 */
class taxonomySyncForm extends ConfigFormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'taxonomy_sync';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'structure_sync.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('structure_sync.settings');

    $form['export_taxonomies'] = [
      '#type' => 'button',
      '#value' => $this->t('Button'),
    ];

    $form['other_things'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Other things'),
      '#default_value' => $config->get('other_things'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Retrieve the configuration
    $this->config('structure_sync.settings')
      // Set the submitted configuration setting
      ->set('things', $form_state->getValue('example_thing'))
      // You can set multiple configurations at once by making
      // multiple calls to set()
      ->set('other_things', $form_state->getValue('other_things'))
      ->save();

    parent::submitForm($form, $form_state);
  }
}
