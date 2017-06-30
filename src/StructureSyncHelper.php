<?php

namespace Drupal\structure_sync;

use Drupal\Core\Form\FormStateInterface;

/**
 *
 */
class StructureSyncHelper {

  /**
   *
   */
  public static function exportTaxonomies() {
    \Drupal::logger('structure_sync')
      ->notice('Taxonomies export started');

    $vocabulary_list = [];
    $vocabularies = \Drupal::entityTypeManager()
      ->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_list[] = $vocabulary->id();
    }
    if (!count($vocabulary_list)) {
      \Drupal::logger('structure_sync')
        ->warning('No vocabularies available');

      drupal_set_message(t('No vocabularies available'), 'warning');
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

  /**
   *
   */
  public static function exportCustomBlocks() {
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

  /**
   *
   */
  public static function importTaxonomies(array $form, FormStateInterface $form_state = NULL) {
    \Drupal::logger('structure_sync')
      ->notice('Taxonomy import started');

    if (is_object($form_state) && $form_state->hasValue('import_style_tax')) {
      $style = $form_state->getValue('import_style_tax');
    }
    elseif (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      \Drupal::logger('structure_sync')
        ->error('No style defined on taxonomy import');
      return;
    }

    \Drupal::logger('structure_sync')
      ->notice('Using "' . $style . '" style for taxonomy import');

    $taxonomies = \Drupal::config('structure_sync.data')
      ->get('taxonomies');

    $query = \Drupal::database();

    switch ($style) {
      case 'full':
        \Drupal::logger('structure_sync')
          ->warning('"Full" style not yet implemented');

        drupal_set_message(t('"Full" style not yet implemented'), 'warning');

        // TODO: Full style is same as safe but with deletes and updates.
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

              \Drupal::logger('structure_sync')
                ->notice('Imported "' . $taxonomy['name'] . '" into ' . $vid);
            }
          }
        }

        \Drupal::logger('structure_sync')
          ->notice('Successfully imported taxonomies');

        drupal_set_message(t('Successfully imported taxonomies'));
        break;

      case 'force':
        $query->delete('taxonomy_term_field_data')->execute();
        $query->delete('taxonomy_term_hierarchy')->execute();
        $query->delete('taxonomy_term_data')->execute();

        \Drupal::logger('structure_sync')
          ->notice('Deleted all taxonomies');

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

            \Drupal::logger('structure_sync')
              ->notice('Imported "' . $taxonomy['name'] . '" into ' . $vid);
          }
        }

        \Drupal::logger('structure_sync')
          ->notice('Successfully imported taxonomies');

        drupal_set_message(t('Successfully imported taxonomies'));
        break;

      default:
        \Drupal::logger('structure_sync')
          ->error('Style not recognized');
        break;
    }
  }

  /**
   *
   */
  public static function importCustomBlocks(array $form, FormStateInterface $form_state = NULL) {
    \Drupal::logger('structure_sync')
      ->notice('Custom blocks import started');

    if (is_object($form_state) && $form_state->hasValue('import_style_bls')) {
      $style = $form_state->getValue('import_style_bls');
    }
    elseif (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      \Drupal::logger('structure_sync')
        ->error('No style defined on custom blocks import');
      return;
    }

    \Drupal::logger('structure_sync')
      ->notice('Using "' . $style . '" style for custom blocks import');

    $blocks = \Drupal::config('structure_sync.data')->get('blocks');

    $query = \Drupal::database();

    switch ($style) {
      case 'full':
        \Drupal::logger('structure_sync')
          ->warning('"Full" style not yet implemented');

        drupal_set_message(t('"Full" style not yet implemented'), 'warning');

        // TODO: Full style is same as safe but with deletes and updates.
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

              \Drupal::logger('structure_sync')
                ->notice('Imported current revision of "' . $block_revision['info'] . '"');
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

            \Drupal::logger('structure_sync')
              ->notice('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
          }
        }

        \Drupal::logger('structure_sync')
          ->notice('Flushing all caches');

        drupal_flush_all_caches();

        \Drupal::logger('structure_sync')
          ->notice('Succesfully flushed caches');

        \Drupal::logger('structure_sync')
          ->notice('Successfully imported blocks');

        drupal_set_message(t('Successfully imported blocks'));
        break;

      case 'force':
        $query->delete('block_content_revision__body')->execute();
        $query->delete('block_content_revision')->execute();
        $query->delete('block_content_field_revision')->execute();
        $query->delete('block_content_field_data')->execute();
        $query->delete('block_content__body')->execute();
        $query->delete('block_content')->execute();

        \Drupal::logger('structure_sync')
          ->notice('Deleted all blocks');

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

            \Drupal::logger('structure_sync')
              ->notice('Imported current revision of "' . $block_revision['info'] . '"');
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

          \Drupal::logger('structure_sync')
            ->notice('Imported "' . $block_revision['info'] . '" revision ' . $block_revision['revision_id']);
        }

        \Drupal::logger('structure_sync')
          ->notice('Flushing all caches');

        drupal_flush_all_caches();

        \Drupal::logger('structure_sync')
          ->notice('Succesfully flushed caches');

        \Drupal::logger('structure_sync')
          ->notice('Successfully imported blocks');

        drupal_set_message(t('Successfully imported blocks'));
        break;

      default:
        \Drupal::logger('structure_sync')
          ->error('Style not recognized');
        break;
    }
  }

}
