<?php

namespace Drupal\idp_insights;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Extension\ModuleExtensionList;
use Drupal\system\SystemManager;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;

/**
 * Service description.
 */
class IdpInsightService {

  /**
   * The system.manager service.
   *
   * @var \Drupal\system\SystemManager
   */
  protected $systemManager;

  /**
   * The module extension list.
   *
   * @var \Drupal\Core\Extension\ModuleExtensionList
   */
  protected $extensionListModule;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs an IdpInsightService object.
   *
   * @param \Drupal\system\SystemManager $system_manager
   *   The system.manager service.
   * @param \Drupal\Core\Extension\ModuleExtensionList $extension_list_module
   *   The module extension list.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The config facotory.
   */
  public function __construct(SystemManager $system_manager, ModuleExtensionList $extension_list_module, EntityTypeManagerInterface $entity_type_manager, ModuleHandlerInterface $module_handler, ConfigFactoryInterface $config_factory) {
    $this->systemManager = $system_manager;
    $this->extensionListModule = $extension_list_module;
    $this->entityTypeManager = $entity_type_manager;
    $this->moduleHandler = $module_handler;
    $this->configFactory = $config_factory;
  }

  /**
   * Return all modules.
   */
  public function getModules() {
    $installedModules = $this->moduleHandler->getModuleList();
    $modules = $this->extensionListModule->getList();
    $modules = array_filter($modules, function ($module) {
      return (!$module->isObsolete() && $module->origin != 'core');
    });
    $moduleList = [];
    foreach ($modules as $moduleKey => $module) {
      $moduleList[] = [
        'name' => $moduleKey,
        'title' => $module->info['name'],
        'version' => $module->info['version'] ?? 'NA',
        'installed' => isset($installedModules[$moduleKey]) ? 'Yes' : 'No',
      ];
    }
    return $moduleList;
  }

  /**
   * Get all content types.
   */
  public function getContentTypes() {
    $contentTypes = $this->entityTypeManager->getStorage('node_type')->loadMultiple();
    $results = [];
    foreach ($contentTypes as $contentType) {
      $typeName = $contentType->id();
      $typeLabel = $contentType->label();
      $query = $this->entityTypeManager->getStorage('node')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('type', $typeName);
      $nodeCount = $query->count()->execute();
      $results[] = [
        'name' => $typeName,
        'title' => $typeLabel,
        'node_count' => $nodeCount,
      ];
    }
    return $results;
  }

  /**
   * Get metadata.
   */
  public function getMetadata() {
    $requirements = $this->systemManager->listRequirements();
    $status = [];
    $status['drupal_version'] = $requirements['drupal']['value'];
    $status['cron_run'] = $requirements['cron']['value'];
    $status['php'] = current(explode(' ', $requirements['php']['value']));
    $status['database'] = $requirements['database_system']['value'];
    $status['database_version'] = current(explode('-', $requirements['database_system_version']['value']));
    $status['webserver'] = $requirements['webserver']['value'];
    return $status;
  }

  /**
   * Get Roles and Its Users.
   */
  public function getRolesUsers() {
    $roles = $this->entityTypeManager->getStorage('user_role')->loadMultiple();
    $result = [];
    foreach ($roles as $role) {

      $roleName = $role->label();
      $roleId = $role->id();
      if (in_array($roleId, ['anonymous', 'authenticated'])) {
        continue;
      }

      // Fetch user count.
      $userCount = $this->entityTypeManager->getStorage('user')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('roles', $roleId)
        ->count()
        ->execute();

      $result[] = [
        'role_name' => $roleName,
        'role_id' => $roleId,
        'user_count' => $userCount,
      ];
    }
    return $result;
  }

  /**
   * Get all taxonomies and its term count.
   */
  public function getTaxonomyCount() {
    $result = [];
    $taxonomies = $this->entityTypeManager->getStorage('taxonomy_vocabulary')
      ->loadMultiple();
    foreach ($taxonomies as $taxonomy) {
      /** @var \Drupal\taxonomy\Entity\TermStorageInterface $taxonomy */
      $taxonomyName = $taxonomy->label();
      $taxonomyId = $taxonomy->id();

      // Fetch term count.
      $termCount = $this->entityTypeManager->getStorage('taxonomy_term')
        ->getQuery()
        ->accessCheck(FALSE)
        ->condition('vid', $taxonomyId)
        ->count()
        ->execute();

      $result[] = [
        'title' => $taxonomyName,
        'name' => $taxonomyId,
        'term_count' => $termCount,
      ];
    }
    return $result;
  }

  /**
   * Check if valid request.
   */
  public function checkIfValidRequest($api_key) {
    $config = $this->configFactory->get('idp_insights.settings');
    $configApiKey = $config->get('api_key');
    if (empty($api_key) || empty($configApiKey) || $api_key !== $configApiKey) {
      return FALSE;
    }
    return TRUE;
  }

}
