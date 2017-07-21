<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\structure_sync\StructureSyncHelper;
use Drupal\Core\Form\FormStateInterface;

/**
 * Controller for syncing custom blocks.
 */
class BlocksController extends ControllerBase {

  private $config;

  /**
   * Constructor for custom blocks controller.
   */
  public function __construct() {
    $this->config = $this->getEditableConfig();
    $this->entityTypeManager();
  }

  /**
   * Gets the editable version of the config.
   */
  private function getEditableConfig() {
    $this->config('structure_sync.data');

    return $this->configFactory->getEditable('structure_sync.data');
  }

  /**
   * Function to export custom blocks.
   */
  public function exportBlocks(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Custom blocks export started');

    if (is_object($form_state) && $form_state->hasValue('export_block_list')) {
      $blockList = $form_state->getValue('export_block_list');
      $blockList = array_filter($blockList, 'is_string');
    }

    $this->config->clear('blocks')->save();

    if (isset($blockList)) {
      $blocks = [];

      foreach ($blockList as $blockName) {
        $blocks = array_merge($this->entityTypeManager
          ->getStorage('block_content')
          ->loadByProperties(['menu_name' => $blockName]), $blocks);
      }
    }
    else {
      $blocks = $this->entityTypeManager()->getStorage('block_content')
        ->loadMultiple();
    }

    $customBlocks = [];
    foreach ($blocks as $block) {
      $customBlocks[] = [
        'info' => $block->info->getValue()[0]['value'],
        'langcode' => $block->langcode->getValue()[0]['value'],
        'uuid' => $block->uuid(),
        'bundle' => $block->bundle(),
        'body_value' => $block->body->getValue()[0]['value'],
        'body_summary' => $block->body->getValue()[0]['summary'],
        'body_format' => $block->body->getValue()[0]['format'],
      ];
    }

    $this->config->set('blocks', $customBlocks)->save();

    foreach ($customBlocks as $customBlock) {
      StructureSyncHelper::logMessage('Exported "' . $customBlock['info'] . '"');
    }

    drupal_set_message($this->t('The custom blocks have been successfully exported.'));
    StructureSyncHelper::logMessage('Custom blocks exported');
  }

}
