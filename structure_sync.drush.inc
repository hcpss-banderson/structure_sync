<?php

/**
 * @file Drush commands for syncing structure content.
 */

use Drupal\structure_sync\StructureSyncHelper;

/**
 * Implements hook_drush_command().
 */
function structure_sync_drush_command() {
  $items = [];
  $items['export-taxonomies'] = [
    'description' => 'Export the taxonomies',
    'drupal dependencies' => ['structure_sync'],
    'aliases' => ['et'],
  ];
  $items['import-taxonomies'] = [
    'description' => 'Import the taxonomies',
    'drupal dependencies' => ['structure_sync'],
    'aliases' => ['it'],
  ];
  $items['export-blocks'] = [
    'description' => 'Export blocks',
    'drupal dependencies' => ['structure_sync'],
    'aliases' => ['eb'],
  ];
  $items['import-blocks'] = [
    'description' => 'Import blocks',
    'drupal dependencies' => ['structure_sync'],
    'aliases' => ['ib'],
  ];
  return $items;
}

/**
 * Call back function drush_structure_sync_export_taxonomies()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_structure_sync_export_taxonomies() {
  drush_print('Exporting taxonomies...');

  StructureSyncHelper::ExportTaxonomies();

  drush_log('Successfully exported taxonomies', 'ok');
}

/**
 * Call back function drush_structure_sync_import_taxonomies()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_structure_sync_import_taxonomies() {
  drush_print('Importing taxonomies...');

  StructureSyncHelper::ImportTaxonomies(['style' => 'safe'], []);
  return;

  $options = [
    'full' => 'Full',
    'safe' => 'Safe',
    'force' => 'Force',
  ];

  $choice = drush_choice($options, 'What import style would you like?');

  if ($choice) {
    drush_print('Using "' . $options[$choice] . '" import style');

    $taxonomies = \Drupal::config('structure_sync.data')
      ->get('taxonomies');

    $query = \Drupal::database();

    switch ($choice) {
      case 'full':
        drush_print('Not yet implemented');
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

                if ($changeParent) {
                  foreach ($taxonomies as $voc) {
                    foreach ($voc as $tax) {
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

              drush_log('Imported "' . $taxonomy['name'] . '" into ' . $vid, 'ok');
            }
          }
        }

        drush_log('Successfully imported taxonomies', 'ok');
        break;
      case 'force':
        $query->delete('taxonomy_term_field_data')->execute();
        $query->delete('taxonomy_term_hierarchy')->execute();
        $query->delete('taxonomy_term_data')->execute();

        drush_log('Deleted all taxonomies', 'ok');

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

            drush_log('Imported "' . $taxonomy['name'] . '" into ' . $vid, 'ok');
          }
        }

        drush_log('Successfully imported taxonomies', 'ok');
        break;
    }
  }
}

/**
 * Call back function drush_structure_sync_export_blocks()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_structure_sync_export_blocks() {
  drush_print('Exporting blocks...');

  StructureSyncHelper::ExportCustomBlocks();

  drush_log('Successfully exported blocks', 'ok');
}

/**
 * Call back function drush_structure_sync_import_blocks()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_structure_sync_import_blocks() {
  drush_print('Importing blocks...');

  $options = [
    'full' => 'Full',
    'safe' => 'Safe',
    'force' => 'Force',
  ];

  $choice = drush_choice($options, 'What import style would you like?');

  if ($choice) {
    drush_print('Using "' . $options[$choice] . '" import style');

    $blocks = \Drupal::config('structure_sync.data')->get('blocks');

    $query = \Drupal::database();

    switch ($choice) {
      case 'full':
        drush_print('Not yet implemented');
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

              drush_log('Imported current revision of "' . $block_revision['info'] . '"', 'ok');
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

            drush_log('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id'], 'ok');
          }
        }

        drush_print('Flushing all caches');

        drupal_flush_all_caches();

        drush_log('Succesfully flushed caches', 'ok');

        drush_log('Successfully imported blocks', 'ok');
        break;
      case 'force':
        $query->delete('block_content_revision__body')->execute();
        $query->delete('block_content_revision')->execute();
        $query->delete('block_content_field_revision')->execute();
        $query->delete('block_content_field_data')->execute();
        $query->delete('block_content__body')->execute();
        $query->delete('block_content')->execute();

        drush_log('Deleted all blocks', 'ok');

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

            drush_log('Imported current revision of "' . $block_revision['info'] . '"', 'ok');
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

          drush_log('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id'], 'ok');
        }

        drush_print('Flushing all caches');

        drupal_flush_all_caches();

        drush_log('Succesfully flushed caches', 'ok');

        drush_log('Successfully imported blocks', 'ok');
        break;
    }
  }
}
