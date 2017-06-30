<?php

namespace Drupal\structure_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\structure_sync\StructureSyncHelper;

/**
 * Import and export form for taxonomy terms.
 */
class StructureSyncForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'structure_sync';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'structure_sync.data',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $helper = new StructureSyncHelper();

    $form['taxonomies'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Taxonomies'),
      '#weight' => 1,
    ];

    $form['taxonomies']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export all taxonomies'),
      '#name' => 'exportTaxonomies',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'exportTaxonomies']],
    ];

    $form['taxonomies']['import_style_tax'] = [
      '#type' => 'select',
      '#title' => $this->t('Select import style'),
      '#options' => [
        'full' => $this->t('Full (not yet implemented)'),
        'safe' => $this->t('Safe'),
        'force' => $this->t('Force'),
      ],
      '#default_value' => 'safe',
    ];

    $form['taxonomies']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import taxonomies'),
      '#name' => 'importTaxonomies',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'importTaxonomies']],
    ];

    $form['blocks'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Custom blocks'),
      '#weight' => 2,
    ];

    $form['blocks']['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export all custom blocks'),
      '#name' => 'exportBlocks',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'exportCustomBlocks']],
    ];

    $form['blocks']['import_style_bls'] = [
      '#type' => 'select',
      '#title' => $this->t('Select import style'),
      '#options' => [
        'full' => $this->t('Full (not yet implemented)'),
        'safe' => $this->t('Safe'),
        'force' => $this->t('Force'),
      ],
      '#default_value' => 'safe',
    ];

    $form['blocks']['import'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import custom blocks'),
      '#name' => 'importBlocks',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'importCustomBlocks']],
    ];

    return $form;
  }

}
