<?php

namespace Drupal\structure_sync;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;

/**
 * Container of functions for importing and exporting content in structure.
 */
class StructureSyncHelper {

  /**
   * Function to export taxonomy terms.
   */
  public static function exportTaxonomies(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Taxonomies export started');

    if (is_object($form_state) && $form_state->hasValue('export_voc_list')) {
      $vocabulary_list = $form_state->getValue('export_voc_list');
      $vocabulary_list = array_filter($vocabulary_list, 'is_string');
    }

    // Get a list of all vocabularies (their machine names).
    if (!isset($vocabulary_list)) {
      $vocabulary_list = [];
      $vocabularies = \Drupal::entityTypeManager()
        ->getStorage('taxonomy_vocabulary')->loadMultiple();
      foreach ($vocabularies as $vocabulary) {
        if (in_array($vocabulary->id(), $vocabulary_list)) {
          $vocabulary_list[] = $vocabulary->id();
        }
      }
    }
    if (!count($vocabulary_list)) {
      StructureSyncHelper::logMessage('No vocabularies available', 'warning');

      drupal_set_message(t('No vocabularies selected/available'), 'warning');
      return;
    }

    // Clear the (previous) taxonomies data in the config, but don't save yet
    // (just in case anything goes wrong).
    $config = \Drupal::service('config.factory')
      ->getEditable('structure_sync.data');
    $config->clear('taxonomies');

    // Get all taxonomies from each (previously retrieved) vocabulary.
    foreach ($vocabulary_list as $vocabulary) {
      $query = \Drupal::database()
        ->select('taxonomy_term_field_data', 'ttfd');
      $query->fields('ttfd', [
        'tid',
        'name',
        'langcode',
        'description__value',
        'description__format',
        'weight',
        'changed',
        'default_langcode',
      ]);
      $query->addField('tth', 'parent');
      $query->join('taxonomy_term_hierarchy', 'tth', 'ttfd.tid = tth.tid');
      $query->addField('ttd', 'uuid');
      $query->join('taxonomy_term_data', 'ttd', 'ttfd.tid = ttd.tid');
      $query->condition('ttfd.vid', $vocabulary);
      $taxonomies = $query->execute()->fetchAll();

      // Save the retrieved taxonomies to the config.
      $config
        ->set('taxonomies' . '.' . $vocabulary, json_decode(json_encode($taxonomies), TRUE))
        ->save();

      StructureSyncHelper::logMessage('Exported ' . $vocabulary);
    }

    drupal_set_message(t('The taxonomies have been successfully exported.'));
    StructureSyncHelper::logMessage('Taxonomies exported');
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
    StructureSyncHelper::logMessage('Menu links export started');

    if (is_object($form_state) && $form_state->hasValue('export_menu_list')) {
      $menu_list = $form_state->getValue('export_menu_list');
      $menu_list = array_filter($menu_list, 'is_string');
    }

    $config = \Drupal::service('config.factory')
      ->getEditable('structure_sync.data');
    $config->clear('menus');

    $query = \Drupal::database()->select('menu_tree', 'mt')
      ->fields('mt', [
        'menu_name',
        'mlid',
        'id',
        'parent',
        'route_name',
        'route_param_key',
        'route_parameters',
        'url',
        'title',
        'description',
        'class',
        'options',
        'provider',
        'enabled',
        'discovered',
        'expanded',
        'weight',
        'metadata',
        'has_children',
        'depth',
        'p1',
        'p2',
        'p3',
        'p4',
        'p5',
        'p6',
        'p7',
        'p8',
        'p9',
        'form_class',
      ]);
    $query->addField('mlc', 'id');
    $query->addField('mlc', 'bundle');
    $query->addField('mlc', 'uuid');
    $query->addField('mlc', 'langcode');
    $query->join('menu_link_content', 'mlc', "mlc.uuid = TRIM('menu_link_content:' FROM mt.id)");
    $query->addField('mlcd', 'title');
    $query->addField('mlcd', 'description');
    $query->addField('mlcd', 'link__uri');
    $query->addField('mlcd', 'link__title');
    $query->addField('mlcd', 'link__options');
    $query->addField('mlcd', 'external');
    $query->addField('mlcd', 'rediscover');
    $query->addField('mlcd', 'weight');
    $query->addField('mlcd', 'expanded');
    $query->addField('mlcd', 'enabled');
    $query->addField('mlcd', 'parent');
    $query->addField('mlcd', 'changed');
    $query->addField('mlcd', 'default_langcode');
    $query->join('menu_link_content_data', 'mlcd', 'mlcd.id = mlc.id');
    if (isset($menu_list)) {
      $query->condition('mlcd.menu_name', $menu_list, 'IN');
    }

    $menus = $query->execute()->fetchAll();

    $config
      ->set('menus', json_decode(json_encode($menus), TRUE))
      ->save();

    drupal_set_message(t('The menu links have been successfully exported.'));
    StructureSyncHelper::logMessage('Menu links exported');
  }

  /**
   * Function to import taxonomy terms.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public static function importTaxonomies(array $form, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Taxonomy import started');

    // Check if the import style has been defined in the form (state) and else
    // get it from the form array.
    if (is_object($form_state) && $form_state->hasValue('import_voc_list')) {
      $taxonomiesSelected = $form_state->getValue('import_voc_list');
      $taxonomiesSelected = array_filter($taxonomiesSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on taxonomy import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for taxonomy import');

    // Get taxonomies from config.
    $taxonomiesConfig = \Drupal::config('structure_sync.data')
      ->get('taxonomies');

    $taxonomies = [];

    if (isset($taxonomiesSelected)) {
      foreach ($taxonomiesConfig as $taxKey => $taxValue) {
        if (in_array($taxKey, $taxonomiesSelected)) {
          $taxonomies[$taxKey] = $taxValue;
        }
      }
    }
    else {
      $taxonomies = $taxonomiesConfig;
    }

    $query = \Drupal::database();

    // Import the taxonomies with the chosen style of importing.
    switch ($style) {
      case 'full':
        // TODO: Check taxonomy_index.
        $queryCheck = $query->select('taxonomy_term_data', 'ttd');
        $queryCheck->fields('ttd', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $taxonomiesTids = [];
        foreach ($taxonomies as $vocabulary) {
          foreach ($vocabulary as $taxonomy) {
            $taxonomiesTids[] = $taxonomy['tid'];
          }
        }

        $query->delete('taxonomy_term_field_data')
          ->condition('tid', $taxonomiesTids, 'NOT IN')->execute();
        $query->delete('taxonomy_term_hierarchy')
          ->condition('tid', $taxonomiesTids, 'NOT IN')->execute();
        $query->delete('taxonomy_term_data')
          ->condition('tid', $taxonomiesTids, 'NOT IN')->execute();

        foreach ($taxonomies as $vid => $vocabulary) {
          foreach ($vocabulary as $taxonomy) {
            $tid = $taxonomy['tid'];

            if (!in_array($taxonomy['uuid'], $uuids)) {
              $query->insert('taxonomy_term_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'uuid' => $taxonomy['uuid'],
                'langcode' => $taxonomy['langcode'],
              ])->execute();
              $query->insert('taxonomy_term_hierarchy')->fields([
                'tid' => $tid,
                'parent' => $taxonomy['parent'],
              ])->execute();
              $query->insert('taxonomy_term_field_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'langcode' => $taxonomy['langcode'],
                'name' => $taxonomy['name'],
                'description__value' => $taxonomy['description__value'],
                'description__format' => $taxonomy['description__format'],
                'weight' => $taxonomy['weight'],
                'changed' => $taxonomy['changed'],
                'default_langcode' => $taxonomy['default_langcode'],
              ])->execute();

              StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
            }
            else {
              $query->update('taxonomy_term_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'uuid' => $taxonomy['uuid'],
                'langcode' => $taxonomy['langcode'],
              ])->condition('tid', $tid, '=')->execute();
              $query->update('taxonomy_term_hierarchy')->fields([
                'tid' => $tid,
                'parent' => $taxonomy['parent'],
              ])->condition('tid', $tid, '=')->execute();
              $query->update('taxonomy_term_field_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'langcode' => $taxonomy['langcode'],
                'name' => $taxonomy['name'],
                'description__value' => $taxonomy['description__value'],
                'description__format' => $taxonomy['description__format'],
                'weight' => $taxonomy['weight'],
                'changed' => $taxonomy['changed'],
                'default_langcode' => $taxonomy['default_langcode'],
              ])->condition('tid', $tid, '=')->execute();

              StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
            }
          }
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message(t('Successfully imported taxonomies'));
        break;

      case 'safe':
        $queryCheck = $query->select('taxonomy_term_data', 'ttd');
        $queryCheck->fields('ttd', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $queryCheck->fields('ttd', ['tid']);
        $tids = $queryCheck->execute()->fetchAll();
        $tids = array_column($tids, 'tid');

        $parents = [];
        foreach ($taxonomies as $vocabulary) {
          foreach ($vocabulary as $taxonomy) {
            $parents[] = $taxonomy['parent'];
          }
        }

        foreach ($taxonomies as $vid => $vocabulary) {
          foreach ($vocabulary as $taxonomy) {
            if (!in_array($taxonomy['uuid'], $uuids)) {
              $tid = $taxonomy['tid'];

              if (in_array($tid, $tids)) {
                $changeParent = FALSE;

                if (in_array($tid, $parents)) {
                  $changeParent = TRUE;
                }

                $tid = ((int) max($tids)) + 1;
                $tids[] = $tid;

                // TODO: Change this in database if previously inserted.
                if ($changeParent) {
                  foreach ($taxonomies as &$voc) {
                    foreach ($voc as &$tax) {
                      $tax['parent'] = $tid;
                    }
                  }
                }
              }

              $query->insert('taxonomy_term_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'uuid' => $taxonomy['uuid'],
                'langcode' => $taxonomy['langcode'],
              ])->execute();
              $query->insert('taxonomy_term_hierarchy')->fields([
                'tid' => $tid,
                'parent' => $taxonomy['parent'],
              ])->execute();
              $query->insert('taxonomy_term_field_data')->fields([
                'tid' => $tid,
                'vid' => $vid,
                'langcode' => $taxonomy['langcode'],
                'name' => $taxonomy['name'],
                'description__value' => $taxonomy['description__value'],
                'description__format' => $taxonomy['description__format'],
                'weight' => $taxonomy['weight'],
                'changed' => $taxonomy['changed'],
                'default_langcode' => $taxonomy['default_langcode'],
              ])->execute();

              StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
            }
          }
        }

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message(t('Successfully imported taxonomies'));
        break;

      case 'force':
        // TODO: Check taxonomy_index.
        $query->delete('taxonomy_term_field_data')->execute();
        $query->delete('taxonomy_term_hierarchy')->execute();
        $query->delete('taxonomy_term_data')->execute();

        StructureSyncHelper::logMessage('Deleted all taxonomies');

        foreach ($taxonomies as $vid => $vocabulary) {
          foreach ($vocabulary as $taxonomy) {
            $query->insert('taxonomy_term_data')->fields([
              'tid' => $taxonomy['tid'],
              'vid' => $vid,
              'uuid' => $taxonomy['uuid'],
              'langcode' => $taxonomy['langcode'],
            ])->execute();
            $query->insert('taxonomy_term_hierarchy')->fields([
              'tid' => $taxonomy['tid'],
              'parent' => $taxonomy['parent'],
            ])->execute();
            $query->insert('taxonomy_term_field_data')->fields([
              'tid' => $taxonomy['tid'],
              'vid' => $vid,
              'langcode' => $taxonomy['langcode'],
              'name' => $taxonomy['name'],
              'description__value' => $taxonomy['description__value'],
              'description__format' => $taxonomy['description__format'],
              'weight' => $taxonomy['weight'],
              'changed' => $taxonomy['changed'],
              'default_langcode' => $taxonomy['default_langcode'],
            ])->execute();

            StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
          }
        }

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message(t('Successfully imported taxonomies'));
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
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
    StructureSyncHelper::logMessage('Menu links import started');

    // Check if the there is a selection made in a form for what menus need to
    // be imported.
    if (is_object($form_state) && $form_state->hasValue('import_menu_list')) {
      $menusSelected = $form_state->getValue('import_menu_list');
      $menusSelected = array_filter($menusSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on menu links import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for menu links import');

    // Get menu links from config.
    $menusConfig = \Drupal::config('structure_sync.data')->get('menus');

    $menus = [];

    if (isset($menusSelected)) {
      foreach ($menusConfig as $menu) {
        if (in_array($menu['menu_name'], array_keys($menusSelected))) {
          $menus[] = $menu;
        }
      }
    }
    else {
      $menus = $menusConfig;
    }

    $query = \Drupal::database();

    // Import the menu links with the chosen style of importing.
    switch ($style) {
      case 'full':
        $queryCheck = $query->select('menu_link_content', 'mlc');
        $queryCheck->fields('mlc', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $newUuids = array_map(function ($menu) {
          return $menu['uuid'];
        }, $menus);

        foreach ($menus as $menu) {
          $newUuids[] = $menu['uuid'];
        }

        $uuidsToDelete = [];
        foreach ($uuids as $uuid) {
          if (!in_array($uuid, $newUuids)) {
            $uuidsToDelete[] = $uuid;
          }
        }

        if (count($uuidsToDelete) > 0) {
          $queryIds = \Drupal::database()
            ->select('menu_link_content', 'mlc')
            ->fields('mlc', ['id'])
            ->condition('uuid', $uuidsToDelete, 'IN');
          $idsToDelete = $queryIds->execute()->fetchAll();
          $idsToDelete = array_column($idsToDelete, 'id');
        }

        $uuidsToDeletePrefixed = array_map(function ($uuid) {
          return "menu_link_content:$uuid";
        }, $uuidsToDelete);

        if (isset($idsToDelete) && count($idsToDelete) > 0) {
          $query->delete('menu_link_content')
            ->condition('id', $idsToDelete, 'IN')
            ->execute();
          $query->delete('menu_link_content_data')
            ->condition('id', $idsToDelete, 'IN')
            ->execute();
          $query->delete('menu_tree')
            ->condition('id', $uuidsToDeletePrefixed, 'IN')
            ->execute();
        }

        foreach ($menus as $menu) {
          if (!in_array($menu['uuid'], $uuids)) {
            $id = $query->insert('menu_link_content')->fields([
              'bundle' => $menu['bundle'],
              'uuid' => $menu['uuid'],
              'langcode' => $menu['langcode'],
            ])->execute();
            $query->insert('menu_link_content_data')->fields([
              'id' => $id,
              'bundle' => $menu['bundle'],
              'langcode' => $menu['langcode'],
              'title' => $menu['mlcd_title'],
              'description' => $menu['mlcd_description'],
              'menu_name' => $menu['menu_name'],
              'link__uri' => $menu['link__uri'],
              'link__title' => $menu['link__title'],
              'link__options' => $menu['link__options'],
              'external' => $menu['external'],
              'rediscover' => $menu['rediscover'],
              'weight' => $menu['mlcd_weight'],
              'expanded' => $menu['mlcd_expanded'],
              'enabled' => $menu['mlcd_enabled'],
              'parent' => $menu['mlcd_parent'],
              'changed' => $menu['changed'],
              'default_langcode' => $menu['default_langcode'],
            ])->execute();
            $query->insert('menu_tree')->fields([
              'menu_name' => $menu['menu_name'],
              'id' => $menu['id'],
              'parent' => $menu['parent'],
              'route_name' => $menu['route_name'],
              'route_param_key' => $menu['route_param_key'],
              'route_parameters' => $menu['route_parameters'],
              'url' => $menu['url'],
              'title' => $menu['title'],
              'description' => $menu['description'],
              'class' => $menu['class'],
              'options' => $menu['options'],
              'provider' => $menu['provider'],
              'enabled' => $menu['enabled'],
              'discovered' => $menu['discovered'],
              'expanded' => $menu['expanded'],
              'weight' => $menu['weight'],
              'metadata' => $menu['metadata'],
              'has_children' => $menu['has_children'],
              'depth' => $menu['depth'],
              'p1' => $menu['p1'],
              'p2' => $menu['p2'],
              'p3' => $menu['p3'],
              'p4' => $menu['p4'],
              'p5' => $menu['p5'],
              'p6' => $menu['p6'],
              'p7' => $menu['p7'],
              'p8' => $menu['p8'],
              'p9' => $menu['p9'],
              'form_class' => $menu['form_class'],
            ])->execute();
          }
          else {
            $query->update('menu_link_content')->fields([
              'bundle' => $menu['bundle'],
              'uuid' => $menu['uuid'],
              'langcode' => $menu['langcode'],
            ])->condition('uuid', $menu['uuid'])->execute();
            $connection = Database::getConnection();
            $idQuery = $connection->select('menu_link_content', 'mlc')
              ->fields('mlc', ['id'])->condition('mlc.uuid', $menu['uuid']);
            $id = $idQuery->execute()->fetchField();
            $query->update('menu_link_content_data')->fields([
              'bundle' => $menu['bundle'],
              'langcode' => $menu['langcode'],
              'title' => $menu['mlcd_title'],
              'description' => $menu['mlcd_description'],
              'menu_name' => $menu['menu_name'],
              'link__uri' => $menu['link__uri'],
              'link__title' => $menu['link__title'],
              'link__options' => $menu['link__options'],
              'external' => $menu['external'],
              'rediscover' => $menu['rediscover'],
              'weight' => $menu['mlcd_weight'],
              'expanded' => $menu['mlcd_expanded'],
              'enabled' => $menu['mlcd_enabled'],
              'parent' => $menu['mlcd_parent'],
              'changed' => $menu['changed'],
              'default_langcode' => $menu['default_langcode'],
            ])->condition('id', $id)->execute();
            $query->update('menu_tree')->fields([
              'menu_name' => $menu['menu_name'],
              'parent' => $menu['parent'],
              'route_name' => $menu['route_name'],
              'route_param_key' => $menu['route_param_key'],
              'route_parameters' => $menu['route_parameters'],
              'url' => $menu['url'],
              'title' => $menu['title'],
              'description' => $menu['description'],
              'class' => $menu['class'],
              'options' => $menu['options'],
              'provider' => $menu['provider'],
              'enabled' => $menu['enabled'],
              'discovered' => $menu['discovered'],
              'expanded' => $menu['expanded'],
              'weight' => $menu['weight'],
              'metadata' => $menu['metadata'],
              'has_children' => $menu['has_children'],
              'depth' => $menu['depth'],
              'p1' => $menu['p1'],
              'p2' => $menu['p2'],
              'p3' => $menu['p3'],
              'p4' => $menu['p4'],
              'p5' => $menu['p5'],
              'p6' => $menu['p6'],
              'p7' => $menu['p7'],
              'p8' => $menu['p8'],
              'p9' => $menu['p9'],
              'form_class' => $menu['form_class'],
            ])->condition('id', $menu['id'])->execute();
          }

          StructureSyncHelper::logMessage('Imported "' . $menu['mlcd_title'] . '" into "' . $menu['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message(t('Successfully imported menu links'));
        break;

      case 'safe':
        $queryCheck = $query->select('menu_link_content', 'mlc');
        $queryCheck->fields('mlc', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $menusNew = [];

        foreach ($menus as $menu) {
          if (!in_array($menu['uuid'], $uuids)) {
            $menusNew[] = $menu;
          }
        }

        foreach ($menusNew as $menu) {
          $id = $query->insert('menu_link_content')->fields([
            'bundle' => $menu['bundle'],
            'uuid' => $menu['uuid'],
            'langcode' => $menu['langcode'],
          ])->execute();
          $query->insert('menu_link_content_data')->fields([
            'id' => $id,
            'bundle' => $menu['bundle'],
            'langcode' => $menu['langcode'],
            'title' => $menu['mlcd_title'],
            'description' => $menu['mlcd_description'],
            'menu_name' => $menu['menu_name'],
            'link__uri' => $menu['link__uri'],
            'link__title' => $menu['link__title'],
            'link__options' => $menu['link__options'],
            'external' => $menu['external'],
            'rediscover' => $menu['rediscover'],
            'weight' => $menu['mlcd_weight'],
            'expanded' => $menu['mlcd_expanded'],
            'enabled' => $menu['mlcd_enabled'],
            'parent' => $menu['mlcd_parent'],
            'changed' => $menu['changed'],
            'default_langcode' => $menu['default_langcode'],
          ])->execute();
          $query->insert('menu_tree')->fields([
            'menu_name' => $menu['menu_name'],
            'id' => $menu['id'],
            'parent' => $menu['parent'],
            'route_name' => $menu['route_name'],
            'route_param_key' => $menu['route_param_key'],
            'route_parameters' => $menu['route_parameters'],
            'url' => $menu['url'],
            'title' => $menu['title'],
            'description' => $menu['description'],
            'class' => $menu['class'],
            'options' => $menu['options'],
            'provider' => $menu['provider'],
            'enabled' => $menu['enabled'],
            'discovered' => $menu['discovered'],
            'expanded' => $menu['expanded'],
            'weight' => $menu['weight'],
            'metadata' => $menu['metadata'],
            'has_children' => $menu['has_children'],
            'depth' => $menu['depth'],
            'p1' => $menu['p1'],
            'p2' => $menu['p2'],
            'p3' => $menu['p3'],
            'p4' => $menu['p4'],
            'p5' => $menu['p5'],
            'p6' => $menu['p6'],
            'p7' => $menu['p7'],
            'p8' => $menu['p8'],
            'p9' => $menu['p9'],
            'form_class' => $menu['form_class'],
          ])->execute();

          StructureSyncHelper::logMessage('Imported "' . $menu['mlcd_title'] . '" into "' . $menu['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message(t('Successfully imported menu links'));
        break;

      case 'force':
        $query->delete('menu_link_content')->execute();
        $query->delete('menu_link_content_data')->execute();
        $query->delete('menu_tree')
          ->condition('provider', 'menu_link_content')
          ->execute();

        StructureSyncHelper::logMessage('Deleted all (content) menu links');

        foreach ($menus as $menu) {
          $id = $query->insert('menu_link_content')->fields([
            'bundle' => $menu['bundle'],
            'uuid' => $menu['uuid'],
            'langcode' => $menu['langcode'],
          ])->execute();
          $query->insert('menu_link_content_data')->fields([
            'id' => $id,
            'bundle' => $menu['bundle'],
            'langcode' => $menu['langcode'],
            'title' => $menu['mlcd_title'],
            'description' => $menu['mlcd_description'],
            'menu_name' => $menu['menu_name'],
            'link__uri' => $menu['link__uri'],
            'link__title' => $menu['link__title'],
            'link__options' => $menu['link__options'],
            'external' => $menu['external'],
            'rediscover' => $menu['rediscover'],
            'weight' => $menu['mlcd_weight'],
            'expanded' => $menu['mlcd_expanded'],
            'enabled' => $menu['mlcd_enabled'],
            'parent' => $menu['mlcd_parent'],
            'changed' => $menu['changed'],
            'default_langcode' => $menu['default_langcode'],
          ])->execute();
          $query->insert('menu_tree')->fields([
            'menu_name' => $menu['menu_name'],
            'id' => $menu['id'],
            'parent' => $menu['parent'],
            'route_name' => $menu['route_name'],
            'route_param_key' => $menu['route_param_key'],
            'route_parameters' => $menu['route_parameters'],
            'url' => $menu['url'],
            'title' => $menu['title'],
            'description' => $menu['description'],
            'class' => $menu['class'],
            'options' => $menu['options'],
            'provider' => $menu['provider'],
            'enabled' => $menu['enabled'],
            'discovered' => $menu['discovered'],
            'expanded' => $menu['expanded'],
            'weight' => $menu['weight'],
            'metadata' => $menu['metadata'],
            'has_children' => $menu['has_children'],
            'depth' => $menu['depth'],
            'p1' => $menu['p1'],
            'p2' => $menu['p2'],
            'p3' => $menu['p3'],
            'p4' => $menu['p4'],
            'p5' => $menu['p5'],
            'p6' => $menu['p6'],
            'p7' => $menu['p7'],
            'p8' => $menu['p8'],
            'p9' => $menu['p9'],
            'form_class' => $menu['form_class'],
          ])->execute();

          StructureSyncHelper::logMessage('Imported "' . $menu['mlcd_title'] . '" into "' . $menu['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message(t('Successfully imported menu links'));
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
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
