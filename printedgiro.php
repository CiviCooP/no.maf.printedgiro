<?php

require_once 'printedgiro.civix.php';

function printedgiro_civicrm_tokens(&$tokens) {
  $tokens['printed_grio']['printed_grio.most_recent_amount'] = ts('Amount of most recent printed giro', array('context' => 'no.maf.printedgiro'));
}


function printedgiro_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  if (_printedgiro_has_token('most_recent_amount', $tokens)) {
    $config = CRM_Printedgiro_Config::singleton();
    $contact_ids = $cids;
    if (!is_array($contact_ids)) {
      $contact_ids = [$contact_ids];
    }
    $tokenValues = [];
    $sql = "
      SELECT `printed_giro`.`{$config->getAmountCustomColumnName()}` AS `amount`, `printed_giro`.`entity_id` AS `contact_id` 
      FROM `{$config->getPrintedCustomTableName()}`  `printed_giro`
      WHERE `printed_giro`.`{$config->getStartDateCustomColumName()}` = 
        (SELECT `most_recent_printed_giro`.`{$config->getStartDateCustomColumName()}`
          FROM  `{$config->getPrintedCustomTableName()}` `most_recent_printed_giro`
          WHERE `printed_giro`.`id` = `most_recent_printed_giro`.`id`
          ORDER BY `most_recent_printed_giro`.`{$config->getStartDateCustomColumName()}` DESC 
        )
      AND  (`{$config->getEndDateCustomColumnName()}` IS NULL OR (`{$config->getEndDateCustomColumnName()}` >= NOW()))
      AND `entity_id` IN (".implode(", ", $contact_ids).")";

    $dao = CRM_Core_DAO::executeQuery($sql);
    while($dao->fetch()) {
      $tokenValues[$dao->contact_id] = \CRM_Utils_Money::format($dao->amount);
    }
    _printedgiro_set_token_values($values, $cids, 'most_recent_amount', $tokenValues);
  }
}

/**
 * Helper function to set the array with the values
 *
 * @param $values
 * @param $cids
 * @param $token
 * @param $tokenValues
 */
function _printedgiro_set_token_values(&$values, $cids, $token, $tokenValues) {
  if (is_array($cids)) {
    foreach ($cids as $cid) {
      $values[$cid]['printed_grio.' . $token] = $tokenValues[$cid];
    }
  }
  else {
    $values['printed_grio.' . $token] = $tokenValues[$cids];
  }
}

/**
 * Chekcs whether the token is present in the current tokens array.
 * @param $token
 * @param $tokens
 *
 * @return bool
 */
function _printedgiro_has_token($token, $tokens) {
  if (in_array($token, $tokens)) {
    return true;
  } elseif (isset($tokens[$token])) {
    return true;
  } elseif (isset($tokens['printed_grio']) && in_array($token, $tokens['printed_grio'])) {
    return true;
  } elseif (isset($tokens['printed_grio'][$token])) {
    return true;
  }
  return FALSE;
}


/**
 * Implements hook_civicrm_searchTasks
 *
 * @author Erik Hommel (CiviCooP)
 * @date 31 May 2017
 * @param $objectName
 * @param $tasks
 * @link https://docs.civicrm.org/dev/en/master/hooks/hook_civicrm_searchTasks/
 */
function printedgiro_civicrm_searchTasks($objectName, &$tasks) {
  if ($objectName == 'contact') {
    $tasks[] = array(
      'title' => 'Export for MAF Printed Giro',
      'class' => 'CRM_Printedgiro_Export', 'CRM_Export_Form_Map',
      );
  }
}

/**
 * Implements hook_civicrm_config().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_config
 */
function printedgiro_civicrm_config(&$config) {
  _printedgiro_civix_civicrm_config($config);
}

/**
 * Implements hook_civicrm_xmlMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_xmlMenu
 */
function printedgiro_civicrm_xmlMenu(&$files) {
  _printedgiro_civix_civicrm_xmlMenu($files);
}

/**
 * Implements hook_civicrm_install().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_install
 */
function printedgiro_civicrm_install() {
  _printedgiro_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_postInstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_postInstall
 */
function printedgiro_civicrm_postInstall() {
  _printedgiro_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_uninstall
 */
function printedgiro_civicrm_uninstall() {
  _printedgiro_civix_civicrm_uninstall();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_enable
 */
function printedgiro_civicrm_enable() {
  _printedgiro_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_disable().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_disable
 */
function printedgiro_civicrm_disable() {
  _printedgiro_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_upgrade
 */
function printedgiro_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _printedgiro_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_managed
 */
function printedgiro_civicrm_managed(&$entities) {
  _printedgiro_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Generate a list of case-types.
 *
 * Note: This hook only runs in CiviCRM 4.4+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_caseTypes
 */
function printedgiro_civicrm_caseTypes(&$caseTypes) {
  _printedgiro_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Generate a list of Angular modules.
 *
 * Note: This hook only runs in CiviCRM 4.5+. It may
 * use features only available in v4.6+.
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_angularModules
 */
function printedgiro_civicrm_angularModules(&$angularModules) {
  _printedgiro_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_alterSettingsFolders
 */
function printedgiro_civicrm_alterSettingsFolders(&$metaDataFolders = NULL) {
  _printedgiro_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

// --- Functions below this ship commented out. Uncomment as required. ---

/**
 * Implements hook_civicrm_preProcess().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_preProcess
 *
function printedgiro_civicrm_preProcess($formName, &$form) {

} // */

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link http://wiki.civicrm.org/confluence/display/CRMDOC/hook_civicrm_navigationMenu
 *
function printedgiro_civicrm_navigationMenu(&$menu) {
  _printedgiro_civix_insert_navigation_menu($menu, NULL, array(
    'label' => ts('The Page', array('domain' => 'no.maf.printedgiro')),
    'name' => 'the_page',
    'url' => 'civicrm/the-page',
    'permission' => 'access CiviReport,access CiviContribute',
    'operator' => 'OR',
    'separator' => 0,
  ));
  _printedgiro_civix_navigationMenu($menu);
} // */
