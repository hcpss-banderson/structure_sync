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

  $options = [
    'full' => 'Full',
    'safe' => 'Safe',
    'force' => 'Force',
  ];

  $choice = drush_choice($options, 'What import style would you like?');

  if ($choice) {
    drush_print('Using "' . $options[$choice] . '" import style');

    StructureSyncHelper::ImportTaxonomies(['style' => $choice]);

    drush_log('Successfully imported taxonomies', 'ok');
  }
  else {
    drush_log('No choice made for import style on importing taxonomies', 'error');
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

    StructureSyncHelper::ImportCustomBlocks(['style' => $choice]);

    drush_log('Successfully imported custom blocks', 'ok');
  }
  else {
    drush_log('No choice made for import style on importing custom blocks', 'error');
  }
}
