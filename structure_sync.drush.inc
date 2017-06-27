<?php

/**
 * @file Drush commands for .
 */

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
  return $items;
}

/**
 * Call back function drush_structure_sync_export_taxonomies()
 * The call back function name in the  following format
 *   drush_{module_name}_{item_id_for_command}()
 */
function drush_structure_sync_export_taxonomies() {
  drush_print('Exporting taxonomies...');

  $vocabulary_list = array();
  $vocabularies = Drupal::entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
  foreach ($vocabularies as $vocabulary) {
    $vocabulary_list[] = $vocabulary->id();
  }
  if (!count($vocabulary_list)) {
    drush_log('No vocabularies available', 'warning');
    return;
  }

  $config = \Drupal::service('config.factory')->getEditable('structure_sync.data');
  $config->clear('taxonomies')->save();

  foreach ($vocabulary_list as $vocabulary) {
    $query = \Drupal::database()->select('taxonomy_term_field_data', 'ttfd');
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

    $config
      ->set('taxonomies' . '.' . $vocabulary, json_decode(json_encode($taxonomies), TRUE))
      ->save();

    drush_print('Exported ' . $vocabulary);
  }

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

    $taxonomies = \Drupal::config('structure_sync.data')
      ->get('taxonomies');

    $query = \Drupal::database();

    switch ($choice) {
      case 'full':
        drush_print('Not yet implemented');
        break;
      case 'safe':
        drush_print('Not yet implemented');
        break;
      case 'force':
        $query->delete('taxonomy_term_field_data')->execute();
        $query->delete('taxonomy_term_hierarchy')->execute();
        $query->delete('taxonomy_term_data')->execute();

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
          }
        }
        break;
    }
  }
}
