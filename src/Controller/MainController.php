<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;

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
//      $vocabulary_form = Url::fromRoute('taxonomy_manager.admin_vocabulary',
//        array('taxonomy_vocabulary' => $vocabulary->id()));
//      $vocabulary_list[] = Link::fromTextAndUrl($vocabulary->id(), $vocabulary_form);
      $vocabulary_list[] = $vocabulary->id();
    }
    if (!count($vocabulary_list)) {
      $vocabulary_list[] = array('#markup' => $this->t('No vocabularies available'));
    }

    $build['vocabularies'] = [
      '#markup' => '<h2>' . $this->t('Vocabularies') . '</h2>',
    ];

    foreach ($vocabulary_list as $vocabulary) {
      $build['vocabularies'][] = [
        '#markup' => '<p><h3 class="button">' . $vocabulary . '</h3></p>',
      ];
    }

    return $build;
  }

}
