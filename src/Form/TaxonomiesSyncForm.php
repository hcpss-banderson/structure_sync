<?php

namespace Drupal\structure_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\structure_sync\StructureSyncHelper;

/**
 * Import and export form for content in structure, like taxonomy terms.
 */
class TaxonomiesSyncForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'structure_sync_taxonomies';
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

    $form['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export'),
      '#weight' => 1,
      '#open' => TRUE,
    ];

    $form['export']['export_taxonomies'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export taxonomies'),
      '#name' => 'exportTaxonomies',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'exportTaxonomies']],
    ];

    // Get a list of all vocabularies (their machine names).
    $vocabulary_list = [];
    $vocabularies = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_list[$vocabulary->id()] = $vocabulary->id();
    }

    $form['export']['export_voc_list'] = [
      '#type' => 'checkboxes',
      '#options' => $vocabulary_list,
      '#title' => $this->t('Select the vocabularies you would like to export'),
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import'),
      '#weight' => 2,
      '#open' => TRUE,
    ];

    $form['import']['import_style_tax'] = [
      '#type' => 'select',
      '#title' => $this->t('Select import style'),
      '#options' => [
        'full' => $this->t('Full (not yet implemented)'),
        'safe' => $this->t('Safe'),
        'force' => $this->t('Force'),
      ],
      '#default_value' => 'safe',
    ];

    $form['import']['import_taxonomies'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import taxonomies'),
      '#name' => 'importTaxonomies',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'importTaxonomies']],
    ];

    return $form;
  }

}
