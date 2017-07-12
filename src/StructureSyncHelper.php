<?php

namespace Drupal\structure_sync;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Drupal\structure_sync\Controller\MenuLinksController;
use Drupal\taxonomy\Entity\Term;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\structure_sync\Controller\TaxonomiesController;

/**
 * Container of functions for importing and exporting content in structure.
 */
class StructureSyncHelper {

  /**
   * Function to export taxonomy terms.
   */
  public static function exportTaxonomies(array $form = NULL, FormStateInterface $form_state = NULL) {
    $taxonomiesController = new TaxonomiesController();
    $taxonomiesController->exportTaxonomies($form, $form_state);
    return;
  }

  /**
   * Function to export custom blocks.
   */
  public static function exportCustomBlocks(array $form = NULL, FormStateInterface $form_state = NULL) {
    // TODO: Doesn't work yet with custom blocks without content in body.
    StructureSyncHelper::logMessage('Custom blocks export started');

    if (is_object($form_state) && $form_state->hasValue('export_block_list')) {
      $block_list = $form_state->getValue('export_block_list');
      $block_list = array_filter($block_list, 'is_string');
    }

    // Clear the (previous) custom blocks data in the config, but don't save yet
    // (just in case anything goes wrong).
    $config = \Drupal::service('config.factory')
      ->getEditable('structure_sync.data');
    $config->clear('blocks');

    // Get all custom blocks.
    $query = \Drupal::database()
      ->select('block_content_field_revision', 'bcfr');
    $query->fields('bcfr', [
      'id',
      'revision_id',
      'langcode',
      'info',
      'changed',
      'revision_created',
      'revision_user',
      'revision_translation_affected',
      'default_langcode',
    ]);
    $query->addField('bc', 'uuid');
    $query->addField('bc', 'revision_id', 'rev_id_current');
    $query->join('block_content', 'bc', 'bcfr.id = bc.id');
    $query->addField('bcr', 'revision_log');
    $query->join('block_content_revision', 'bcr', 'bcfr.id = bcr.id AND bcfr.revision_id = bcr.revision_id');
    $query->addField('bcrb', 'bundle');
    $query->addField('bcrb', 'deleted');
    $query->addField('bcrb', 'delta');
    $query->addField('bcrb', 'body_value');
    $query->addField('bcrb', 'body_summary');
    $query->addField('bcrb', 'body_format');
    $query->join('block_content_revision__body', 'bcrb', 'bcfr.id = bcrb.entity_id AND bcfr.revision_id = bcrb.revision_id');
    if (isset($block_list)) {
      $query->condition('bc.uuid', array_keys($block_list), 'IN');
    }
    $blocks = $query->execute()->fetchAll();

    $blocks = json_encode($blocks);
    $blocks = json_decode($blocks, TRUE);

    // Save the retrieved custom blocks to the config.
    $config->set('blocks', $blocks)->save();

    foreach ($blocks as $block) {
      StructureSyncHelper::logMessage('Exported "' . $block['info'] . '" revision (' . $block['revision_id'] . ')');
    }

    drupal_set_message(t('The custom blocks have been successfully exported.'));
    StructureSyncHelper::logMessage('Custom blocks exported');
  }

  /**
   * Function to export menu links.
   */
  public static function exportMenuLinks(array $form = NULL, FormStateInterface $form_state = NULL) {
    $menuLinksController = new MenuLinksController();
    $menuLinksController->exportMenuLinks($form, $form_state);
    return;
  }

  /**
   * Function to import taxonomy terms.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public static function importTaxonomies(array $form, FormStateInterface $form_state = NULL) {
    $taxonomiesController = new TaxonomiesController();
    $taxonomiesController->importTaxonomies($form, $form_state);
    return;
  }

  /**
   * Function to import custom blocks.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public static function importCustomBlocks(array $form, FormStateInterface $form_state = NULL) {
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
    $blocksConfig = \Drupal::config('structure_sync.data')->get('blocks');

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

    $query = \Drupal::database();

    // Import the custom blocks with the chosen style of importing.
    switch ($style) {
      case 'full':
        $queryCheck = $query->select('block_content', 'bc');
        $queryCheck->fields('bc', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $blockRevisionIds = [];
        foreach ($blocks as $block) {
          $blockRevisionIds[] = $block['revision_id'];
        }

        $query->delete('block_content_revision__body')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();
        $query->delete('block_content_revision')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();
        $query->delete('block_content_field_revision')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();
        $query->delete('block_content_field_data')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();
        $query->delete('block_content__body')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();
        $query->delete('block_content')
          ->condition('revision_id', $blockRevisionIds, 'NOT IN')
          ->execute();

        foreach ($blocks as $block_revision) {
          if (!in_array($block_revision['uuid'], $uuids)) {
            if ($block_revision['revision_id'] == $block_revision['rev_id_current']) {
              $query->insert('block_content')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'uuid' => $block_revision['uuid'],
                'langcode' => $block_revision['langcode'],
              ])->execute();
              $query->insert('block_content__body')->fields([
                'bundle' => $block_revision['bundle'],
                'deleted' => $block_revision['deleted'],
                'entity_id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'langcode' => $block_revision['langcode'],
                'delta' => $block_revision['delta'],
                'body_value' => $block_revision['body_value'],
                'body_summary' => $block_revision['body_summary'],
                'body_format' => $block_revision['body_format'],
              ])->execute();
              $query->insert('block_content_field_data')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'langcode' => $block_revision['langcode'],
                'info' => $block_revision['info'],
                'changed' => $block_revision['changed'],
                'revision_created' => $block_revision['revision_created'],
                'revision_user' => $block_revision['revision_user'],
                'revision_translation_affected' => $block_revision['revision_translation_affected'],
                'default_langcode' => $block_revision['default_langcode'],
              ])->execute();

              StructureSyncHelper::logMessage('Imported current revision of "' . $block_revision['info'] . '"');
            }

            $query->insert('block_content_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'revision_log' => $block_revision['revision_log'],
            ])->execute();
            $query->insert('block_content_revision__body')->fields([
              'bundle' => $block_revision['bundle'],
              'deleted' => $block_revision['deleted'],
              'entity_id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'delta' => $block_revision['delta'],
              'body_value' => $block_revision['body_value'],
              'body_summary' => $block_revision['body_summary'],
              'body_format' => $block_revision['body_format'],
            ])->execute();
            $query->insert('block_content_field_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'info' => $block_revision['info'],
              'changed' => $block_revision['changed'],
              'revision_created' => $block_revision['revision_created'],
              'revision_user' => $block_revision['revision_user'],
              'revision_translation_affected' => $block_revision['revision_translation_affected'],
              'default_langcode' => $block_revision['default_langcode'],
            ])->execute();

            StructureSyncHelper::logMessage('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
          }
          else {
            if ($block_revision['revision_id'] == $block_revision['rev_id_current']) {
              $query->update('block_content')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'uuid' => $block_revision['uuid'],
                'langcode' => $block_revision['langcode'],
              ])->condition('revision_id', $block_revision['revision_id'], '=')
                ->execute();
              $query->update('block_content__body')->fields([
                'bundle' => $block_revision['bundle'],
                'deleted' => $block_revision['deleted'],
                'entity_id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'langcode' => $block_revision['langcode'],
                'delta' => $block_revision['delta'],
                'body_value' => $block_revision['body_value'],
                'body_summary' => $block_revision['body_summary'],
                'body_format' => $block_revision['body_format'],
              ])->condition('revision_id', $block_revision['revision_id'], '=')
                ->execute();
              $query->update('block_content_field_data')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'langcode' => $block_revision['langcode'],
                'info' => $block_revision['info'],
                'changed' => $block_revision['changed'],
                'revision_created' => $block_revision['revision_created'],
                'revision_user' => $block_revision['revision_user'],
                'revision_translation_affected' => $block_revision['revision_translation_affected'],
                'default_langcode' => $block_revision['default_langcode'],
              ])->condition('revision_id', $block_revision['revision_id'], '=')
                ->execute();

              StructureSyncHelper::logMessage('Imported current revision of "' . $block_revision['info'] . '"');
            }

            $query->update('block_content_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'revision_log' => $block_revision['revision_log'],
            ])->condition('revision_id', $block_revision['revision_id'], '=')
              ->execute();
            $query->update('block_content_revision__body')->fields([
              'bundle' => $block_revision['bundle'],
              'deleted' => $block_revision['deleted'],
              'entity_id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'delta' => $block_revision['delta'],
              'body_value' => $block_revision['body_value'],
              'body_summary' => $block_revision['body_summary'],
              'body_format' => $block_revision['body_format'],
            ])->condition('revision_id', $block_revision['revision_id'], '=')
              ->execute();
            $query->update('block_content_field_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'info' => $block_revision['info'],
              'changed' => $block_revision['changed'],
              'revision_created' => $block_revision['revision_created'],
              'revision_user' => $block_revision['revision_user'],
              'revision_translation_affected' => $block_revision['revision_translation_affected'],
              'default_langcode' => $block_revision['default_langcode'],
            ])->condition('revision_id', $block_revision['revision_id'], '=')
              ->execute();

            StructureSyncHelper::logMessage('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
          }
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported blocks');

        drupal_set_message(t('Successfully imported blocks'));
        break;

      case 'safe':
        $queryCheck = $query->select('block_content', 'bc');
        $queryCheck->fields('bc', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        foreach ($blocks as $block_revision) {
          if (!in_array($block_revision['uuid'], $uuids)) {
            if ($block_revision['revision_id'] == $block_revision['rev_id_current']) {
              $query->insert('block_content')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'uuid' => $block_revision['uuid'],
                'langcode' => $block_revision['langcode'],
              ])->execute();
              $query->insert('block_content__body')->fields([
                'bundle' => $block_revision['bundle'],
                'deleted' => $block_revision['deleted'],
                'entity_id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'langcode' => $block_revision['langcode'],
                'delta' => $block_revision['delta'],
                'body_value' => $block_revision['body_value'],
                'body_summary' => $block_revision['body_summary'],
                'body_format' => $block_revision['body_format'],
              ])->execute();
              $query->insert('block_content_field_data')->fields([
                'id' => $block_revision['id'],
                'revision_id' => $block_revision['revision_id'],
                'type' => $block_revision['bundle'],
                'langcode' => $block_revision['langcode'],
                'info' => $block_revision['info'],
                'changed' => $block_revision['changed'],
                'revision_created' => $block_revision['revision_created'],
                'revision_user' => $block_revision['revision_user'],
                'revision_translation_affected' => $block_revision['revision_translation_affected'],
                'default_langcode' => $block_revision['default_langcode'],
              ])->execute();

              StructureSyncHelper::logMessage('Imported current revision of "' . $block_revision['info'] . '"');
            }

            $query->insert('block_content_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'revision_log' => $block_revision['revision_log'],
            ])->execute();
            $query->insert('block_content_revision__body')->fields([
              'bundle' => $block_revision['bundle'],
              'deleted' => $block_revision['deleted'],
              'entity_id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'delta' => $block_revision['delta'],
              'body_value' => $block_revision['body_value'],
              'body_summary' => $block_revision['body_summary'],
              'body_format' => $block_revision['body_format'],
            ])->execute();
            $query->insert('block_content_field_revision')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'info' => $block_revision['info'],
              'changed' => $block_revision['changed'],
              'revision_created' => $block_revision['revision_created'],
              'revision_user' => $block_revision['revision_user'],
              'revision_translation_affected' => $block_revision['revision_translation_affected'],
              'default_langcode' => $block_revision['default_langcode'],
            ])->execute();

            StructureSyncHelper::logMessage('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
          }
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported blocks');

        drupal_set_message(t('Successfully imported blocks'));
        break;

      case 'force':
        $query->delete('block_content_revision__body')->execute();
        $query->delete('block_content_revision')->execute();
        $query->delete('block_content_field_revision')->execute();
        $query->delete('block_content_field_data')->execute();
        $query->delete('block_content__body')->execute();
        $query->delete('block_content')->execute();

        StructureSyncHelper::logMessage('Deleted all blocks');

        foreach ($blocks as $block_revision) {
          if ($block_revision['revision_id'] == $block_revision['rev_id_current']) {
            $query->insert('block_content')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'type' => $block_revision['bundle'],
              'uuid' => $block_revision['uuid'],
              'langcode' => $block_revision['langcode'],
            ])->execute();
            $query->insert('block_content__body')->fields([
              'bundle' => $block_revision['bundle'],
              'deleted' => $block_revision['deleted'],
              'entity_id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'langcode' => $block_revision['langcode'],
              'delta' => $block_revision['delta'],
              'body_value' => $block_revision['body_value'],
              'body_summary' => $block_revision['body_summary'],
              'body_format' => $block_revision['body_format'],
            ])->execute();
            $query->insert('block_content_field_data')->fields([
              'id' => $block_revision['id'],
              'revision_id' => $block_revision['revision_id'],
              'type' => $block_revision['bundle'],
              'langcode' => $block_revision['langcode'],
              'info' => $block_revision['info'],
              'changed' => $block_revision['changed'],
              'revision_created' => $block_revision['revision_created'],
              'revision_user' => $block_revision['revision_user'],
              'revision_translation_affected' => $block_revision['revision_translation_affected'],
              'default_langcode' => $block_revision['default_langcode'],
            ])->execute();

            StructureSyncHelper::logMessage('Imported current revision of "' . $block_revision['info'] . '"');
          }

          $query->insert('block_content_revision')->fields([
            'id' => $block_revision['id'],
            'revision_id' => $block_revision['revision_id'],
            'langcode' => $block_revision['langcode'],
            'revision_log' => $block_revision['revision_log'],
          ])->execute();
          $query->insert('block_content_revision__body')->fields([
            'bundle' => $block_revision['bundle'],
            'deleted' => $block_revision['deleted'],
            'entity_id' => $block_revision['id'],
            'revision_id' => $block_revision['revision_id'],
            'langcode' => $block_revision['langcode'],
            'delta' => $block_revision['delta'],
            'body_value' => $block_revision['body_value'],
            'body_summary' => $block_revision['body_summary'],
            'body_format' => $block_revision['body_format'],
          ])->execute();
          $query->insert('block_content_field_revision')->fields([
            'id' => $block_revision['id'],
            'revision_id' => $block_revision['revision_id'],
            'langcode' => $block_revision['langcode'],
            'info' => $block_revision['info'],
            'changed' => $block_revision['changed'],
            'revision_created' => $block_revision['revision_created'],
            'revision_user' => $block_revision['revision_user'],
            'revision_translation_affected' => $block_revision['revision_translation_affected'],
            'default_langcode' => $block_revision['default_langcode'],
          ])->execute();

          StructureSyncHelper::logMessage('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported blocks');

        drupal_set_message(t('Successfully imported blocks'));
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

  /**
   * Function to import menu links.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public static function importMenuLinks(array $form, FormStateInterface $form_state = NULL) {
    $menuLinksController = new MenuLinksController();
    $menuLinksController->importMenuLinks($form, $form_state);
    return;
  }

  /**
   * General function for logging messages.
   */
  public static function logMessage($message, $type = NULL) {
    $log = \Drupal::config('structure_sync.data')->get('log');

    if (isset($log) && ($log === FALSE)) {
      return;
    }

    switch ($type) {
      case 'error':
        \Drupal::logger('structure_sync')->error($message);
        break;

      case 'warning':
        \Drupal::logger('structure_sync')->warning($message);
        break;

      default:
        \Drupal::logger('structure_sync')->notice($message);
        break;
    }
  }

  /**
   * Function to start importing taxonomies with the 'full' style.
   */
  public static function importTaxonomiesFull(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'full';

    StructureSyncHelper::importTaxonomies($form, $form_state);
  }

  /**
   * Function to start importing taxonomies with the 'safe' style.
   */
  public static function importTaxonomiesSafe(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'safe';

    StructureSyncHelper::importTaxonomies($form, $form_state);
  }

  /**
   * Function to start importing taxonomies with the 'force' style.
   */
  public static function importTaxonomiesForce(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'force';

    StructureSyncHelper::importTaxonomies($form, $form_state);
  }

  /**
   * Function to start importing custom blocks with the 'full' style.
   */
  public static function importCustomBlocksFull(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'full';

    StructureSyncHelper::importCustomBlocks($form, $form_state);
  }

  /**
   * Function to start importing custom blocks with the 'safe' style.
   */
  public static function importCustomBlocksSafe(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'safe';

    StructureSyncHelper::importCustomBlocks($form, $form_state);
  }

  /**
   * Function to start importing custom blocks with the 'force' style.
   */
  public static function importCustomBlocksForce(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'force';

    StructureSyncHelper::importCustomBlocks($form, $form_state);
  }

  /**
   * Function to start importing menu links with the 'full' style.
   */
  public static function importMenuLinksFull(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'full';

    StructureSyncHelper::importMenuLinks($form, $form_state);
  }

  /**
   * Function to start importing menu links with the 'safe' style.
   */
  public static function importMenuLinksSafe(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'safe';

    StructureSyncHelper::importMenuLinks($form, $form_state);
  }

  /**
   * Function to start importing menu links with the 'force' style.
   */
  public static function importMenuLinksForce(array &$form, FormStateInterface $form_state = NULL) {
    $form['style'] = 'force';

    StructureSyncHelper::importMenuLinks($form, $form_state);
  }

}
