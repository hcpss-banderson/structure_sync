<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;

/**
 * Controller routines for taxonomy_sync routes.
 */
class MainController extends ControllerBase {

  /**
   * List of vocabularies, which link to Taxonomy Sync interface.
   *
   * @return array
   *   A render array representing the page.
   */
  public function listVocabularies() {
    $vocabulary_list = array();
    $vocabularies = $this->entityTypeManager()->getStorage('taxonomy_vocabulary')->loadMultiple();
    foreach ($vocabularies as $vocabulary) {
      $vocabulary_list[] = $vocabulary->id();
    }
    if (!count($vocabulary_list)) {
      $vocabulary_list[] = ['#markup' => $this->t('No vocabularies available')];
    }

    $build['vocabularies'] = [
      '#markup' => '<h2>' . $this->t('Vocabularies') . '</h2>',
    ];

    // TODO: Export button.

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
      $tags = $query->execute()->fetchAll();

      $listItems = '';
      foreach ($tags as $item) {
        $listItems .= '<li>' . $item->parent . ' - ' . $item->tid . ': ' . $item->name . ' (' . $item->uuid . ')</li>';
      }

      $build['vocabularies'][] = [
        '#markup' => '<p><h3>' . $vocabulary . '</h3>
                      <ul>' . $listItems . '</ul></p>',
      ];
    }

    return $build;
  }

}
