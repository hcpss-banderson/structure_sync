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

    $form['export_taxonomies'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export all taxonomies'),
      '#name' => 'exportTaxonomies',
      '#submit' => [[$helper, 'Test']],
    ];

    $form['export_blocks'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export all custom blocks'),
      '#name' => 'exportBlocks',
      '#submit' => [[$helper, 'Test']],
    ];

    return $form;
  }
}
