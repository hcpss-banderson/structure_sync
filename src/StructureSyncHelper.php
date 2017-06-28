<?php

/**
 * @file Functions to sync structure content.
 */

namespace Drupal\structure_sync;

use Drupal\Core\Form\FormStateInterface;

class StructureSyncHelper {
  public static function Test(array $form, FormStateInterface &$form_state) {
    \Drupal::logger('structure_sync')->notice('It works');
  }
}
