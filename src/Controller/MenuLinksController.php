<?php

namespace Drupal\structure_sync\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\structure_sync\StructureSyncHelper;
use Drupal\Core\Form\FormStateInterface;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Controller for syncing menu links.
 */
class MenuLinksController extends ControllerBase {

  private $config;

  /**
   * Constructor for menu links controller.
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
   * Function to export menu links.
   */
  public function exportMenuLinks(array $form = NULL, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Menu links export started');

    if (is_object($form_state) && $form_state->hasValue('export_menu_list')) {
      $menu_list = $form_state->getValue('export_menu_list');
      $menu_list = array_filter($menu_list, 'is_string');
    }

    $this->config->clear('menus')->save();

    if (isset($menu_list)) {
      $menuLinks = [];

      foreach ($menu_list as $menu_name) {
        $menuLinks = array_merge($this->entityTypeManager
          ->getStorage('menu_link_content')
          ->loadByProperties(['menu_name' => $menu_name]), $menuLinks);
      }
    }
    else {
      $menuLinks = $this->entityTypeManager()->getStorage('menu_link_content')
        ->loadMultiple();
    }

    $customMenuLinks = [];
    foreach ($menuLinks as $menuLink) {
      $customMenuLinks[] = [
        'menu_name' => $menuLink->menu_name->getValue()[0]['value'],
        'title' => $menuLink->title->getValue()[0]['value'],
        'parent' => $menuLink->parent->getValue()[0]['value'],
        'uri' => $menuLink->link->getValue()[0]['uri'],
        'link_title' => $menuLink->link->getValue()[0]['title'],
        'description' => $menuLink->description->getValue()[0]['value'],
        'enabled' => $menuLink->enabled->getValue()[0]['value'],
        'expanded' => $menuLink->expanded->getValue()[0]['value'],
        'weight' => $menuLink->weight->getValue()[0]['value'],
        'langcode' => $menuLink->langcode->getValue()[0]['value'],
      ];
    }

    $this->config->set('menus', $customMenuLinks)->save();

    drupal_set_message($this->t('The menu links have been successfully exported.'));
    StructureSyncHelper::logMessage('Menu links exported');
  }

  /**
   * Function to import menu links.
   *
   * When this function is used without the designated form, you should assign
   * an array with a key value pair for form with key 'style' and value 'full',
   * 'safe' or 'force' to apply that import style.
   */
  public function importMenuLinks(array $form, FormStateInterface $form_state = NULL) {
    StructureSyncHelper::logMessage('Menu links import started');

    // Check if the there is a selection made in a form for what menus need to
    // be imported.
    if (is_object($form_state) && $form_state->hasValue('import_menu_list')) {
      $menusSelected = $form_state->getValue('import_menu_list');
      $menusSelected = array_filter($menusSelected, 'is_string');
    }
    if (array_key_exists('style', $form)) {
      $style = $form['style'];
    }
    else {
      StructureSyncHelper::logMessage('No style defined on menu links import', 'error');
      return;
    }

    StructureSyncHelper::logMessage('Using "' . $style . '" style for menu links import');

    // Get menu links from config.
    $menusConfig = $this->config->get('menus');

    $menus = [];

    if (isset($menusSelected)) {
      foreach ($menusConfig as $menu) {
        if (in_array($menu['menu_name'], array_keys($menusSelected))) {
          $menus[] = $menu;
        }
      }
    }
    else {
      $menus = $menusConfig;
    }

    $query = \Drupal::database();

    // Import the menu links with the chosen style of importing.
    switch ($style) {
      case 'full':
        $queryCheck = $query->select('menu_link_content', 'mlc');
        $queryCheck->fields('mlc', ['uuid']);
        $uuids = $queryCheck->execute()->fetchAll();
        $uuids = array_column($uuids, 'uuid');

        $newUuids = array_map(function ($menu) {
          return $menu['uuid'];
        }, $menus);

        foreach ($menus as $menu) {
          $newUuids[] = $menu['uuid'];
        }

        $uuidsToDelete = [];
        foreach ($uuids as $uuid) {
          if (!in_array($uuid, $newUuids)) {
            $uuidsToDelete[] = $uuid;
          }
        }

        if (count($uuidsToDelete) > 0) {
          $queryIds = \Drupal::database()
            ->select('menu_link_content', 'mlc')
            ->fields('mlc', ['id'])
            ->condition('uuid', $uuidsToDelete, 'IN');
          $idsToDelete = $queryIds->execute()->fetchAll();
          $idsToDelete = array_column($idsToDelete, 'id');
        }

        $uuidsToDeletePrefixed = array_map(function ($uuid) {
          return "menu_link_content:$uuid";
        }, $uuidsToDelete);

        if (isset($idsToDelete) && count($idsToDelete) > 0) {
          $query->delete('menu_link_content')
            ->condition('id', $idsToDelete, 'IN')
            ->execute();
          $query->delete('menu_link_content_data')
            ->condition('id', $idsToDelete, 'IN')
            ->execute();
          $query->delete('menu_tree')
            ->condition('id', $uuidsToDeletePrefixed, 'IN')
            ->execute();
        }

        foreach ($menus as $menu) {
          if (!in_array($menu['uuid'], $uuids)) {
            $id = $query->insert('menu_link_content')->fields([
              'bundle' => $menu['bundle'],
              'uuid' => $menu['uuid'],
              'langcode' => $menu['langcode'],
            ])->execute();
            $query->insert('menu_link_content_data')->fields([
              'id' => $id,
              'bundle' => $menu['bundle'],
              'langcode' => $menu['langcode'],
              'title' => $menu['mlcd_title'],
              'description' => $menu['mlcd_description'],
              'menu_name' => $menu['menu_name'],
              'link__uri' => $menu['link__uri'],
              'link__title' => $menu['link__title'],
              'link__options' => $menu['link__options'],
              'external' => $menu['external'],
              'rediscover' => $menu['rediscover'],
              'weight' => $menu['mlcd_weight'],
              'expanded' => $menu['mlcd_expanded'],
              'enabled' => $menu['mlcd_enabled'],
              'parent' => $menu['mlcd_parent'],
              'changed' => $menu['changed'],
              'default_langcode' => $menu['default_langcode'],
            ])->execute();
            $query->insert('menu_tree')->fields([
              'menu_name' => $menu['menu_name'],
              'id' => $menu['id'],
              'parent' => $menu['parent'],
              'route_name' => $menu['route_name'],
              'route_param_key' => $menu['route_param_key'],
              'route_parameters' => $menu['route_parameters'],
              'url' => $menu['url'],
              'title' => $menu['title'],
              'description' => $menu['description'],
              'class' => $menu['class'],
              'options' => $menu['options'],
              'provider' => $menu['provider'],
              'enabled' => $menu['enabled'],
              'discovered' => $menu['discovered'],
              'expanded' => $menu['expanded'],
              'weight' => $menu['weight'],
              'metadata' => $menu['metadata'],
              'has_children' => $menu['has_children'],
              'depth' => $menu['depth'],
              'p1' => $menu['p1'],
              'p2' => $menu['p2'],
              'p3' => $menu['p3'],
              'p4' => $menu['p4'],
              'p5' => $menu['p5'],
              'p6' => $menu['p6'],
              'p7' => $menu['p7'],
              'p8' => $menu['p8'],
              'p9' => $menu['p9'],
              'form_class' => $menu['form_class'],
            ])->execute();
          }
          else {
            $query->update('menu_link_content')->fields([
              'bundle' => $menu['bundle'],
              'uuid' => $menu['uuid'],
              'langcode' => $menu['langcode'],
            ])->condition('uuid', $menu['uuid'])->execute();
            $connection = Database::getConnection();
            $idQuery = $connection->select('menu_link_content', 'mlc')
              ->fields('mlc', ['id'])->condition('mlc.uuid', $menu['uuid']);
            $id = $idQuery->execute()->fetchField();
            $query->update('menu_link_content_data')->fields([
              'bundle' => $menu['bundle'],
              'langcode' => $menu['langcode'],
              'title' => $menu['mlcd_title'],
              'description' => $menu['mlcd_description'],
              'menu_name' => $menu['menu_name'],
              'link__uri' => $menu['link__uri'],
              'link__title' => $menu['link__title'],
              'link__options' => $menu['link__options'],
              'external' => $menu['external'],
              'rediscover' => $menu['rediscover'],
              'weight' => $menu['mlcd_weight'],
              'expanded' => $menu['mlcd_expanded'],
              'enabled' => $menu['mlcd_enabled'],
              'parent' => $menu['mlcd_parent'],
              'changed' => $menu['changed'],
              'default_langcode' => $menu['default_langcode'],
            ])->condition('id', $id)->execute();
            $query->update('menu_tree')->fields([
              'menu_name' => $menu['menu_name'],
              'parent' => $menu['parent'],
              'route_name' => $menu['route_name'],
              'route_param_key' => $menu['route_param_key'],
              'route_parameters' => $menu['route_parameters'],
              'url' => $menu['url'],
              'title' => $menu['title'],
              'description' => $menu['description'],
              'class' => $menu['class'],
              'options' => $menu['options'],
              'provider' => $menu['provider'],
              'enabled' => $menu['enabled'],
              'discovered' => $menu['discovered'],
              'expanded' => $menu['expanded'],
              'weight' => $menu['weight'],
              'metadata' => $menu['metadata'],
              'has_children' => $menu['has_children'],
              'depth' => $menu['depth'],
              'p1' => $menu['p1'],
              'p2' => $menu['p2'],
              'p3' => $menu['p3'],
              'p4' => $menu['p4'],
              'p5' => $menu['p5'],
              'p6' => $menu['p6'],
              'p7' => $menu['p7'],
              'p8' => $menu['p8'],
              'p9' => $menu['p9'],
              'form_class' => $menu['form_class'],
            ])->condition('id', $menu['id'])->execute();
          }

          StructureSyncHelper::logMessage('Imported "' . $menu['mlcd_title'] . '" into "' . $menu['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message($this->t('Successfully imported menu links'));
        break;

      case 'safe':
        $entities = $this->entityTypeManager->getStorage('menu_link_content')
          ->loadMultiple();

        foreach ($entities as $entity) {
          for ($i = 0; $i < count($menus); $i++) {
            if (
              $entity->menu_name->getValue()[0]['value'] === $menus[$i]['menu_name']
              && $entity->title->getValue()[0]['value'] === $menus[$i]['title']
              && $entity->parent->getValue()[0]['value'] === $menus[$i]['parent']
              && $entity->weight->getValue()[0]['value'] === $menus[$i]['weight']
            ) {
              unset($menus[$i]);
            }
          }
        }

        foreach ($menus as $menuLink) {
          MenuLinkContent::create([
            'title' => $menuLink['title'],
            'link' => [
              'uri' => $menuLink['uri'],
              'title' => $menuLink['link_title'],
            ],
            'menu_name' => $menuLink['menu_name'],
            'expanded' => $menuLink['expanded'] === '1' ? TRUE : FALSE,
            'enabled' => $menuLink['enabled'] === '1' ? TRUE : FALSE,
            'parent' => $menuLink['parent'],
            'description' => $menuLink['description'],
            'weight' => $menuLink['weight'],
            'langcode' => $menuLink['langcode'],
          ])->save();

          StructureSyncHelper::logMessage('Imported "' . $menuLink['title'] . '" into "' . $menuLink['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message($this->t('Successfully imported menu links'));
        break;

      case 'force':
        $entities = $this->entityTypeManager->getStorage('menu_link_content')
          ->loadMultiple();
        $this->entityTypeManager->getStorage('menu_link_content')
          ->delete($entities);

        StructureSyncHelper::logMessage('Deleted all (content) menu links');

        foreach ($menus as $menuLink) {
          MenuLinkContent::create([
            'title' => $menuLink['title'],
            'link' => [
              'uri' => $menuLink['uri'],
              'title' => $menuLink['link_title'],
            ],
            'menu_name' => $menuLink['menu_name'],
            'expanded' => $menuLink['expanded'] === '1' ? TRUE : FALSE,
            'enabled' => $menuLink['enabled'] === '1' ? TRUE : FALSE,
            'parent' => $menuLink['parent'],
            'description' => $menuLink['description'],
            'weight' => $menuLink['weight'],
            'langcode' => $menuLink['langcode'],
          ])->save();

          StructureSyncHelper::logMessage('Imported "' . $menuLink['title'] . '" into "' . $menuLink['menu_name'] . '" menu');
        }

        StructureSyncHelper::logMessage('Flushing all caches');

        drupal_flush_all_caches();

        StructureSyncHelper::logMessage('Succesfully flushed caches');

        StructureSyncHelper::logMessage('Successfully imported menu links');

        drupal_set_message($this->t('Successfully imported menu links'));
        break;

      default:
        StructureSyncHelper::logMessage('Style not recognized', 'error');
        break;
    }
  }

}
