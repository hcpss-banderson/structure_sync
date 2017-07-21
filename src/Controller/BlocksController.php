<?php

namespace Drupal\structure_sync\Controller;

use Drupal\block_content\Entity\BlockContent;
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

  /**
   * Function to import custom blocks.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public function importBlocks(array $form, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Custom blocks import started');

    // Check if the import style has been defined in the form (state) and else
    // get it from the form array.
    if (is_object($form_state) && $form_state->hasValue('import_block_list')) {
      $blocksSelected = $form_state->getValue('import_block_list');
      $blocksSelected = array_filter($blocksSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on custom blocks import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for custom blocks import');

    // Get custom blocks from config.
    $blocksConfig = $this->config->get('blocks');

    $blocks = [];

    if (isset($blocksSelected)) {
      foreach ($blocksConfig as $block) {
        if (in_array($block['uuid'], array_keys($blocksSelected))) {
          $blocks[] = $block;
        }
      }
    }
    else {
      $blocks = $blocksConfig;
    }

    // Import the custom blocks with the chosen style of importing.
    switch ($style) {
      case 'full':
        $batch = [
          'title' => $this->t('Importing custom blocks...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\BlocksController::deleteDeletedMenuLinks',
              [$menus],
            ],
            [
              '\Drupal\structure_sync\Controller\BlocksController::importMenuLinksFull',
              [$menus],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\BlocksController::blocksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'safe':
        $batch = [
          'title' => $this->t('Importing custom blocks...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\BlocksController::importMenuLinksSafe',
              [$menus],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\BlocksController::blocksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      case 'force':
        $batch = [
          'title' => $this->t('Importing custom blocks...'),
          'operations' => [
            [
              '\Drupal\structure_sync\Controller\BlocksController::deleteBlocks',
              [],
            ],
            [
              '\Drupal\structure_sync\Controller\BlocksController::importBlocksForce',
              [$blocks],
            ],
          ],
          'finished' => '\Drupal\structure_sync\Controller\BlocksController::blocksImportFinishedCallback',
        ];
        batch_set($batch);
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

  /**
   * Function to delete all custom blocks.
   */
  public static function deleteBlocks(&$context) {
    $entities = StructureSyncHelper::getEntityManager()
      ->getStorage('block_content')
      ->loadMultiple();
    StructureSyncHelper::getEntityManager()
      ->getStorage('block_content')
      ->delete($entities);

    StructureSyncHelper::logMessage('Deleted all custom blocks');
  }

  /**
   * Function to import (create) all custom blocks that need to be imported.
   */
  public static function importBlocksForce($blocks, &$context) {
    foreach ($blocks as $block) {
      BlockContent::create([
        'info' => $block['info'],
        'langcode' => $block['langcode'],
        'uuid' => $block['uuid'],
        'bundle' => $block['bundle'],
        'body' => [
          'value' => $block['body_value'],
          'summary' => $block['body_summary'],
          'format' => $block['body_format'],
        ],
      ])->save();

      StructureSyncHelper::logMessage('Imported "' . $block['info'] . '"');
    }
  }

  /**
   * Function that signals that the import of custom blocks has finished.
   */
  public static function blocksImportFinishedCallback($success, $results, $operations) {
    StructureSyncHelper::logMessage('Flushing all caches');

    drupal_flush_all_caches();

    StructureSyncHelper::logMessage('Successfully flushed caches');

    StructureSyncHelper::logMessage('Successfully imported custom blocks');

    drupal_set_message(t('Successfully imported custom blocks'));
  }

}
