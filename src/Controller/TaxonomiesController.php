<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\structure_sync\StructureSyncHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\taxonomy\Entity\Term;

/**
 * Controller for syncing taxonomy terms.
 */
class TaxonomiesController extends ControllerBase {

  private $config;

  /**
   * Constructor for taxonomies controller.
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
   * Function to export taxonomy terms.
   */
  public function exportTaxonomies(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Taxonomies export started');

    if (is_object($form_state) && $form_state->hasValue('export_voc_list')) {
      $vocabulary_list = $form_state->getValue('export_voc_list');
      $vocabulary_list = array_filter($vocabulary_list, 'is_string');
    }

    // Get a list of all vocabularies (their machine names).
    if (!isset($vocabulary_list)) {
      $vocabulary_list = [];
      $vocabularies = $this->entityTypeManager
        ->getStorage('taxonomy_vocabulary')->loadMultiple();
      foreach ($vocabularies as $vocabulary) {
        $vocabulary_list[] = $vocabulary->id();
      }
    }
    if (!count($vocabulary_list)) {
      StructureSyncHelper::logMessage('No vocabularies available', 'warning');

      drupal_set_message($this->t('No vocabularies selected/available'), 'warning');
      return;
    }

    // Clear the (previous) taxonomies data in the config.
    $this->config->clear('taxonomies')->save();

    // Get all taxonomies from each (previously retrieved) vocabulary.
    foreach ($vocabulary_list as $vocabulary) {
      $query = \Drupal::entityQuery('taxonomy_term');
      $query->condition('vid', $vocabulary);
      $tids = $query->execute();
      $controller = $this->entityTypeManager
        ->getStorage('taxonomy_term');
      $entities = $controller->loadMultiple($tids);

      $parents = [];
      foreach ($tids as $tid) {
        $parent = $this->entityTypeManager
          ->getStorage('taxonomy_term')->loadParents($tid);
        $parent = reset($parent);

        if (is_object($parent)) {
          $parents[$tid] = $parent->id();
        }
      }

      $taxonomies = [];
      foreach ($entities as $entity) {
        $taxonomies[] = [
          'vid' => $vocabulary,
          'tid' => $entity->id(),
          'langcode' => $entity->langcode->getValue()[0]['value'],
          'name' => $entity->name->getValue()[0]['value'],
          'description__value' => $entity->get('description')->getValue()[0]['value'],
          'description__format' => $entity->get('description')->getValue()[0]['format'],
          'weight' => $entity->weight->getValue()[0]['value'],
          'parent' => isset($parents[$entity->id()]) ? $parents[$entity->id()] : '0',
        ];
      }

      // Save the retrieved taxonomies to the config.
      $this->config->set('taxonomies.' . $vocabulary, $taxonomies)->save();

      StructureSyncHelper::logMessage('Exported ' . $vocabulary);
    }

    drupal_set_message($this->t('The taxonomies have been successfully exported.'));
    StructureSyncHelper::logMessage('Taxonomies exported');
  }

  /**
   * Function to import taxonomy terms.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public function importTaxonomies(array $form, FormStateInterface $form_state = NULL) {
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
    $taxonomiesConfig = $this->config->get('taxonomies');

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
        $tidsDone = [];
        $tidsLeft = [];
        $newTids = [];
        $firstRun = TRUE;
        while ($firstRun || count($tidsLeft) > 0) {
          foreach ($taxonomies as $vid => $vocabulary) {
            foreach ($vocabulary as $taxonomy) {
              $query = \Drupal::entityQuery('taxonomy_term');
              $query->condition('vid', $vid);
              $query->condition('name', $taxonomy['name']);
              $tids = $query->execute();

              if (count($tids) <= 0) {
                if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
                  if (!in_array($taxonomy['tid'], $tidsDone)) {
                    $parent = $taxonomy['parent'];
                    if (isset($newTids[$taxonomy['parent']])) {
                      $parent = $newTids[$taxonomy['parent']];
                    }

                    Term::create([
                      'vid' => $vid,
                      'langcode' => $taxonomy['langcode'],
                      'name' => $taxonomy['name'],
                      'description' => [
                        'value' => $taxonomy['description__value'],
                        'format' => $taxonomy['description__format'],
                      ],
                      'weight' => $taxonomy['weight'],
                      'parent' => [$parent],
                    ])->save();

                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('vid', $vid);
                    $query->condition('name', $taxonomy['name']);
                    $tids = $query->execute();
                    if (count($tids) > 0) {
                      $terms = Term::loadMultiple($tids);
                    }

                    if (isset($terms) && count($terms) > 0) {
                      reset($terms);
                      $newTid = key($terms);
                      $newTids[$taxonomy['tid']] = $newTid;
                    }

                    $tidsDone[] = $taxonomy['tid'];

                    if (in_array($taxonomy['tid'], $tidsLeft)) {
                      unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
                    }

                    StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
                  }
                }
                else {
                  if (!in_array($taxonomy['tid'], $tidsLeft)) {
                    $tidsLeft[] = $taxonomy['tid'];
                  }
                }
              }
              elseif (!in_array($taxonomy['tid'], $tidsDone)) {
                $query = \Drupal::entityQuery('taxonomy_term');
                $query->condition('vid', $vid);
                $query->condition('name', $taxonomy['name']);
                $tids = $query->execute();
                if (count($tids) > 0) {
                  $terms = Term::loadMultiple($tids);
                }

                if (isset($terms) && count($terms) > 0) {
                  reset($terms);
                  $newTid = key($terms);
                  $newTids[$taxonomy['tid']] = $newTid;
                  $tidsDone[] = $taxonomy['tid'];
                }
              }
            }
          }

          $firstRun = FALSE;
        }

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message($this->t('Successfully imported taxonomies'));

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

        drupal_set_message($this->t('Successfully imported taxonomies'));
        break;

      case 'safe':
        $tidsDone = [];
        $tidsLeft = [];
        $newTids = [];
        $firstRun = TRUE;
        while ($firstRun || count($tidsLeft) > 0) {
          foreach ($taxonomies as $vid => $vocabulary) {
            foreach ($vocabulary as $taxonomy) {
              $query = \Drupal::entityQuery('taxonomy_term');
              $query->condition('vid', $vid);
              $query->condition('name', $taxonomy['name']);
              $tids = $query->execute();

              if (count($tids) <= 0) {
                if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
                  if (!in_array($taxonomy['tid'], $tidsDone)) {
                    $parent = $taxonomy['parent'];
                    if (isset($newTids[$taxonomy['parent']])) {
                      $parent = $newTids[$taxonomy['parent']];
                    }

                    Term::create([
                      'vid' => $vid,
                      'langcode' => $taxonomy['langcode'],
                      'name' => $taxonomy['name'],
                      'description' => [
                        'value' => $taxonomy['description__value'],
                        'format' => $taxonomy['description__format'],
                      ],
                      'weight' => $taxonomy['weight'],
                      'parent' => [$parent],
                    ])->save();

                    $query = \Drupal::entityQuery('taxonomy_term');
                    $query->condition('vid', $vid);
                    $query->condition('name', $taxonomy['name']);
                    $tids = $query->execute();
                    if (count($tids) > 0) {
                      $terms = Term::loadMultiple($tids);
                    }

                    if (isset($terms) && count($terms) > 0) {
                      reset($terms);
                      $newTid = key($terms);
                      $newTids[$taxonomy['tid']] = $newTid;
                    }

                    $tidsDone[] = $taxonomy['tid'];

                    if (in_array($taxonomy['tid'], $tidsLeft)) {
                      unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
                    }

                    StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
                  }
                }
                else {
                  if (!in_array($taxonomy['tid'], $tidsLeft)) {
                    $tidsLeft[] = $taxonomy['tid'];
                  }
                }
              }
              elseif (!in_array($taxonomy['tid'], $tidsDone)) {
                $query = \Drupal::entityQuery('taxonomy_term');
                $query->condition('vid', $vid);
                $query->condition('name', $taxonomy['name']);
                $tids = $query->execute();
                if (count($tids) > 0) {
                  $terms = Term::loadMultiple($tids);
                }

                if (isset($terms) && count($terms) > 0) {
                  reset($terms);
                  $newTid = key($terms);
                  $newTids[$taxonomy['tid']] = $newTid;
                  $tidsDone[] = $taxonomy['tid'];
                }
              }
            }
          }

          $firstRun = FALSE;
        }

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message($this->t('Successfully imported taxonomies'));
        break;

      case 'force':
        $query = \Drupal::entityQuery('taxonomy_term');
        $tids = $query->execute();
        $controller = $this->entityTypeManager()
          ->getStorage('taxonomy_term');
        $entities = $controller->loadMultiple($tids);
        $controller->delete($entities);

        StructureSyncHelper::logMessage('Deleted all taxonomies');

        $tidsDone = [];
        $tidsLeft = [];
        $newTids = [];
        $firstRun = TRUE;
        while ($firstRun || count($tidsLeft) > 0) {
          foreach ($taxonomies as $vid => $vocabulary) {
            foreach ($vocabulary as $taxonomy) {
              if (!in_array($taxonomy['tid'], $tidsDone) && ($taxonomy['parent'] === '0' || in_array($taxonomy['parent'], $tidsDone))) {
                if (!in_array($taxonomy['tid'], $tidsDone)) {
                  $parent = $taxonomy['parent'];
                  if (isset($newTids[$taxonomy['parent']])) {
                    $parent = $newTids[$taxonomy['parent']];
                  }

                  Term::create([
                    'vid' => $vid,
                    'langcode' => $taxonomy['langcode'],
                    'name' => $taxonomy['name'],
                    'description' => [
                      'value' => $taxonomy['description__value'],
                      'format' => $taxonomy['description__format'],
                    ],
                    'weight' => $taxonomy['weight'],
                    'parent' => [$parent],
                  ])->save();

                  $query = \Drupal::entityQuery('taxonomy_term');
                  $query->condition('vid', $vid);
                  $query->condition('name', $taxonomy['name']);
                  $tids = $query->execute();
                  if (count($tids) > 0) {
                    $terms = Term::loadMultiple($tids);
                  }

                  if (isset($terms) && count($terms) > 0) {
                    reset($terms);
                    $newTid = key($terms);
                    $newTids[$taxonomy['tid']] = $newTid;
                  }

                  $tidsDone[] = $taxonomy['tid'];

                  if (in_array($taxonomy['tid'], $tidsLeft)) {
                    unset($tidsLeft[array_search($taxonomy['tid'], $tidsLeft)]);
                  }

                  StructureSyncHelper::logMessage('Imported "' . $taxonomy['name'] . '" into ' . $vid);
                }
              }
              else {
                if (!in_array($taxonomy['tid'], $tidsLeft)) {
                  $tidsLeft[] = $taxonomy['tid'];
                }
              }
            }
          }

          $firstRun = FALSE;
        }

        StructureSyncHelper::logMessage('Successfully imported taxonomies');

        drupal_set_message($this->t('Successfully imported taxonomies'));
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

}
