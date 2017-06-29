<?php

/**
 * @file Functions to sync structure content.
 */

namespace Drupal\structure_sync;

use Drupal\Core\Form\FormStateInterface;

class StructureSyncHelper {
  public static function Test(array $form, FormStateInterface &$form_state) {
    \Drupal::logger('structure_sync')->notice('It works');

    drupal_set_message(t('The action has been successfully saved.'));
  }

  public static function ExportTaxonomies() {
    \Drupal::logger('structure_sync')
      ->notice('Taxonomies export started');

    $vocabulary_list = array();
    $vocabularies = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_list[] = $vocabulary->id();
    }
    if (!count($vocabulary_list)) {
      \Drupal::logger('structure_sync')
        ->warning('No vocabularies available');
      return;
    }

    $config = \Drupal::service('config.factory')
      ->getEditable('structure_sync.data');
    $config->clear('taxonomies');

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

      $config
        ->set('taxonomies' . '.' . $vocabulary, json_decode(json_encode($taxonomies), TRUE))
        ->save();

      \Drupal::logger('structure_sync')
        ->notice('Exported ' . $vocabulary);
    }

    drupal_set_message(t('The taxonomies have been successfully exported.'));
    \Drupal::logger('structure_sync')
      ->notice('Taxonomies exported');
  }

  public static function ExportCustomBlocks() {
    // TODO: Doesn't work yet with custom blocks without content in body.

    \Drupal::logger('structure_sync')
      ->notice('Custom blocks export started');

    $config = \Drupal::service('config.factory')
      ->getEditable('structure_sync.data');
    $config->clear('blocks');

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
    $blocks = $query->execute()->fetchAll();

    $blocks = json_decode(json_encode($blocks), TRUE);

    $config->set('blocks', $blocks)->save();

    foreach ($blocks as $block) {
      \Drupal::logger('structure_sync')
        ->notice('Exported "' . $block['info'] . '" revision (' . $block['revision_id'] . ')');
    }

    drupal_set_message(t('The custom blocks have been successfully exported.'));
    \Drupal::logger('structure_sync')
      ->notice('Custom blocks exported');
  }

  public static function ImportTaxonomies($form, $form_state) {
    \Drupal::logger('structure_sync')
      ->notice('Taxonomy import started');

    $style = 'safe';

    if (is_object($form_state) && $form_state->hasValue('import_style_tax')) {
      $style = $form_state->getValue('import_style_tax');
    }
    else if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      \Drupal::logger('structure_sync')
        ->error('No style defined on taxonomy import');
      return;
    }

    \Drupal::logger('structure_sync')
      ->notice('Using "' . $style . '" style for taxonomy import');

    switch ($style) {
      case 'full':
        \Drupal::logger('structure_sync')
          ->warning('"Full" style not yet implemented');

        // Full style is same as safe but with deletes and updates.
        break;
      case 'safe':

        break;
      case 'force':

        break;
      default:
        \Drupal::logger('structure_sync')
          ->error('Style not recognized');
        break;
    }
  }
}
