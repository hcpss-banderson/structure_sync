<?php

namespace Drupal\structure_sync\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\structure_sync\StructureSyncHelper;

/**
 * Import and export form for content in structure, like taxonomy terms.
 */
class MenuSyncForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'structure_sync_menus';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'structure_sync.data',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $helper = new StructureSyncHelper();

    $form['title'] = [
      '#type' => 'page_title',
      '#title' => $this->t('Menu links'),
    ];

    $form['export'] = [
      '#type' => 'details',
      '#title' => $this->t('Export'),
      '#weight' => 1,
      '#open' => TRUE,
    ];

    $form['export']['export_menu_links'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export menu links'),
      '#name' => 'exportMenuLinks',
      '#button_type' => 'primary',
      '#submit' => [[$helper, 'exportMenuLinks']],
    ];

    // Get a list of all menus (and their machine names).
    $menu_list = [];
    $query = \Drupal::database()
      ->select('menu_tree', 'mt')
      ->fields('mt', ['menu_name']);
    $query->condition('provider', 'menu_link_content', '=');
    $menus = $query->execute()->fetchAll();
    foreach ($menus as $menu) {
      $menuName = \Drupal::config('system.menu.' . $menu->menu_name)
        ->get('label');
      $menu_list[$menu->menu_name] = $menuName;
    }

    $form['export']['export_menu_list'] = [
      '#type' => 'checkboxes',
      '#options' => $menu_list,
      '#default_value' => array_keys($menu_list),
      '#title' => $this->t('Select the menus you would like to export'),
    ];

    $form['import'] = [
      '#type' => 'details',
      '#title' => $this->t('Import'),
      '#weight' => 2,
      '#open' => TRUE,
    ];

    $form['import']['import_menus_force'] = [
      '#type' => 'submit',
      '#value' => $this->t('Import menu links (force)'),
      '#name' => 'importMenusForce',
      '#submit' => [[$helper, 'importMenuLinksForce']],
    ];

    $menus = \Drupal::config('structure_sync.data')->get('menus');
    $menu_list = [];
    foreach ($menus as $menu) {
      $menuName = \Drupal::config('system.menu.' . $menu['menu_name'])
        ->get('label');
      $menu_list[$menu['menu_name']] = $menuName;
    }

    $form['import']['import_menu_list'] = [
      '#type' => 'checkboxes',
      '#options' => $menu_list,
      '#default_value' => array_keys($menu_list),
      '#title' => $this->t('Select the menus you would like to import'),
    ];

    return $form;
  }

}
