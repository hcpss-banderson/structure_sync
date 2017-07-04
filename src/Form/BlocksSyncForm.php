<?php

namespace Drupal\structure_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\structure_sync\StructureSyncHelper;

/**
 * Import and export form for content in structure, like taxonomy terms.
 */
class BlocksSyncForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'structure_sync_blocks';
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

    $form['title'] = [
      '#type' => 'page_title',
      '#title' => $this->t('Custom blocks'),
    ];

    $form['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export'),
      '#weight' => 1,
      '#open' => TRUE,
    ];

    $form['export']['blocks'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export custom blocks'),
      '#name' => 'exportBlocks',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'exportCustomBlocks']],
    ];

    // Get a list of all blocks (their current names and uuids).
    $block_list = [];
    $query = \Drupal::database()
      ->select('block_content', 'bc');
    $query->fields('bc', ['uuid',]);
    $query->addField('bcfd', 'info');
    $query->join('block_content_field_data', 'bcfd', 'bcfd.id = bc.id');
    $blocks = $query->execute()->fetchAll();
    foreach ($blocks as $block) {
      $block_list[$block->uuid] = $block->info;
    }

    $form['export']['export_block_list'] = [
      '#type' => 'checkboxes',
      '#options' => $block_list,
      '#default_value' => array_keys($block_list),
      '#title' => $this->t('Select the custom blocks you would like to export'),
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import'),
      '#weight' => 2,
      '#open' => TRUE,
    ];

    $form['import']['import_blocks_safe'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import custom blocks (safely)'),
      '#name' => 'importBlocksSafe',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'importCustomBlocksSafe']],
    ];

    $form['import']['import_blocks_full'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import custom blocks (full)'),
      '#name' => 'importBlocksFull',
      '#submit' => [[$helper, 'importCustomBlocksFull']],
    ];

    $form['import']['import_blocks_force'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import custom blocks (force)'),
      '#name' => 'importBlocksForce',
      '#submit' => [[$helper, 'importCustomBlocksForce']],
    ];

    $block_list = \Drupal::config('structure_sync.data')
      ->get('blocks');

    $block_list_config = [];

    foreach ($block_list as $block) {
      if ($block['revision_id'] === $block['rev_id_current']) {
        $block_list_config[$block['uuid']] = $block['info'];
      }
    }

    $form['import']['import_block_list'] = [
      '#type' => 'checkboxes',
      '#options' => $block_list_config,
      '#default_value' => array_keys($block_list_config),
      '#title' => $this->t('Select the custom blocks you would like to import'),
    ];

    return $form;
  }

}
