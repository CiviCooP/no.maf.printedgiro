<?php

/**
 * Class for MAF Printed Giro Configuration
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 26 May 2017
 * @license AGPL-3.0
 */
class CRM_Printedgiro_Config {
  // property for singleton pattern (caching the config)
  static private $_singleton = NULL;
  // properties for custom group and fields
  private $_printedCustomTableName = NULL;
  private $_campaignCustomColumnName = NULL;
  private $_frequencyCustomColumnName = NULL;
  private $_startDateCustomColumnName = NULL;
  private $_endDateCustomColumnName = NULL;
  private $_amountCustomColumnName = NULL;
  private $_frequencyOptionGroupId = NULL;

  /**
   * CRM_Printedgiro_Config constructor.
   *
   * @throws Exception when error from API
   */
  public function __construct() {
    $customGroupName = 'maf_partners_non_avtale';
    try {
      $this->_printedCustomTableName = civicrm_api3('CustomGroup', 'getvalue', array(
        'name' => $customGroupName,
        'extends' => 'Contact',
        'return' => 'table_name',
      ));
      $customFields = civicrm_api3('CustomField', 'get', array(
        'custom_group_id' => $customGroupName,
        'options' => array('limit' => 0),
      ));
      foreach ($customFields['values'] as $customFieldId => $customField) {
        switch($customField['name']) {
          case 'maf_partners_amount':
            $this->_amountCustomColumnName = $customField['column_name'];
            break;
          case 'maf_partners_campaign':
            $this->_campaignCustomColumnName = $customField['column_name'];
            break;
          case 'maf_partners_end_date':
            $this->_endDateCustomColumnName = $customField['column_name'];
            break;
          case 'maf_partners_frequency':
            $this->_frequencyCustomColumnName = $customField['column_name'];
            $this->_frequencyOptionGroupId = $customField['option_group_id'];
            break;
          case 'maf_partners_start_date':
            $this->_startDateCustomColumnName = $customField['column_name'];
            break;
        }
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
      throw new Exception('Could not find custom group or custom fields for printed giro. 
        Contact your system administrator, error from API: '.$ex->getMessage());
    }
  }

  /**
   * Getter for printed giro frequency option group id
   *
   * @return null
   */
  public function getFrequencyOptionGroupId () {
    return $this->_frequencyOptionGroupId;
  }

  /**
   * Getter for printed giro custom group table name
   *
   * @return null
   */
  public function getPrintedCustomTableName () {
    return $this->_printedCustomTableName;
  }

  /**
   * Getter for printed giro amount custom field column name
   *
   * @return null
   */
  public function getAmountCustomColumnName() {
    return $this->_amountCustomColumnName;
  }

  /**
   * Getter for printed giro campaign custom field column name
   *
   * @return null
   */
  public function getCampaignCustomColumnName() {
    return $this->_campaignCustomColumnName;
  }

  /**
   * Getter for printed giro frequency custom field column name
   *
   * @return null
   */
  public function getFrequencyCustomColumnName() {
    return $this->_frequencyCustomColumnName;
  }

  /**
   * Getter for printed giro start date custom field column name
   *
   * @return null
   */
  public function getStartDateCustomColumName() {
    return $this->_startDateCustomColumnName;
  }

  /**
   * Getter for printed giro end date custom field column name
   *
   * @return null
   */
  public function getEndDateCustomColumnName() {
    return $this->_endDateCustomColumnName;
  }

  /**
   * Function to return singleton object
   *
   * @return object $_singleton
   * @access public
   * @static
   */
  public static function &singleton() {
    if (self::$_singleton === NULL) {
      self::$_singleton = new CRM_Printedgiro_Config();
    }
    return self::$_singleton;
  }
}