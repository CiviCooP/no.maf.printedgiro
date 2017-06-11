<?php

/**
 * Class for MAF Printed Giro Export
 *
 * @author Erik Hommel (CiviCooP) <erik.hommel@civicoop.org>
 * @date 31 May 2017
 * @license AGPL-3.0
 */
class CRM_Printedgiro_Export {

  private $_headers = array();
  private $_rows = array();
  private $_csvFileName = NULL;
  private $_csvTitle = NULL;
  private $_filters = array();
  private $_query = NULL;
  private $_queryParams = NULL;
  private $_postCodesList = array();
  private $_fieldSeparator = NULL;


  function __construct() {
    $this->getFiltersFromRequest();
    $now = new DateTime();
    $this->_csvFileName = '/printed_giro_'.$now->format('Ymdhis').'.csv';
    $this->_csvTitle = 'Contacts for Printed Giro MAF Norge';
    $this->setPostCodesList();
    $this->processExport();
    $this->_fieldSeparator = ';';
  }

  /**
   * Method to get the post code select list
   *
   */
  private function setPostCodesList() {
    $dao = CRM_Core_DAO::executeQuery('SELECT DISTINCT(postal_code) FROM civicrm_address');
    while ($dao->fetch()) {
      $this->_postCodesList[] = $dao->postal_code;
    }
    asort($this->_postCodesList);
    return;
  }


  /**
   * Method to get the array of possible filters
   *
   * @return array
   */
  private function getPossibleFilters() {
    return array(
      'contact_name',
      'start_date_from',
      'start_date_to',
      'end_date_from',
      'end_date_to',
      'campaign_ids',
      'group_ids',
      'tag_ids',
      'frequency_ids',
      'post_codes',
      'only_active',
    );
  }

  /**
   * Method to get the possible filters from the request (coming from printed giro custom search)
   */
  private function getFiltersFromRequest() {
    $requestValues = CRM_Utils_Request::exportValues();
    $possibleFilters = $this->getPossibleFilters();
    foreach ($possibleFilters as $possibleFilter) {
      if (isset($requestValues[$possibleFilter]) && !empty($requestValues[$possibleFilter])) {
        $this->_filters[$possibleFilter] = $requestValues[$possibleFilter];
      }
    }
  }

  /**
   * Method to process the export
   */
  private function processExport() {
    $this->buildQuery();
    if (!empty($this->_query)) {
      $this->setHeaders();
      $this->_rows = array();
      $dao = CRM_Core_DAO::executeQuery($this->_query, $this->_queryParams);
      while ($dao->fetch()) {
        $this->addRow($dao);
      }
      // write file
      CRM_Core_Report_Excel::writeCSVFile($this->_csvFileName, $this->_headers, $this->_rows);
      CRM_Utils_System::civiExit();
    }
  }

  /**
   * Method to add a row
   *
   * @param object $dao
   */
  private function addRow($dao) {
    try {
      $kid = civicrm_api3('Kid', 'generate', array(
        'campaign_id' => $dao->campaign_id,
        'contact_id' => $dao->contact_id,
      ));
      $kidNumber = $kid['kid_number'];
    } catch (CiviCRM_API3_Exception $ex) {
      $kidNumber = NULL;
    }
    $row = array(
      $dao->contact_id,
      $dao->addressee,
      $dao->nick_name,
      $dao->street_address,
      $dao->postal_code,
      $dao->city,
      $dao->country,
      $dao->county,
      $dao->amount,
      $kidNumber
    );
    $this->_rows[] = $row;
  }

  /**
   * Method to set the headers for the CSV
   */
  private function setHeaders() {
    $this->_headers = array(
      ts('Contact ID'),
      ts('Name'),
      ts('Nick Name'),
      ts('Street Address'),
      ts('Post Code'),
      ts('City'),
      ts('Country'),
      ts('County'),
      ts('Amount'),
      ts('KID'),
    );
  }

  /**
   * Method to build the query statement and the parameter array for the export
   */
  private function buildQuery() {
    $select = $this->setSelect();
    $from = $this->setFrom();
    $where = $this->setWhere();
    if (!empty($select) && !empty($from)) {
      $this->_query = 'SELECT '.$select.' FROM '.$from;
    }
    if (!empty($where)) {
      $this->_query .= ' WHERE '.$where;
    }
  }

  /**
   * Method to set the where part of the query depending on the filters
   */
  private function setWhere() {
    $where = NULL;
    $whereClauses = array('(cc.is_deleted = %1)');
    $this->_queryParams = array(1 => array(0, 'Integer'));
    $whereIndex = 1;
    $whereClauses[] = $this->addContactNameWhereClause($whereIndex);
    $whereClauses[] = $this->addCampaignWhereClauses($whereIndex);
    $whereClauses[] = $this->addFrequencyWhereClauses($whereIndex);
    $whereClauses[] = $this->addGroupWhereClauses($whereIndex);
    $whereClauses[] = $this->addTagWhereClauses($whereIndex);
    $whereClauses[] = $this->addPostCodeWhereClauses($whereIndex);
    $whereClauses[] = $this->addStartDateFromWhereClauses($whereIndex);
    $whereClauses[] = $this->addStartDateToWhereClauses($whereIndex);
    $whereClauses[] = $this->addEndDateFromWhereClauses($whereIndex);
    $whereClauses[] = $this->addEndDateToWhereClauses($whereIndex);
    $whereClauses[] = $this->addOnlyActiveWhereClause();
    // remove empty clauses
    foreach ($whereClauses as $key => $value) {
      if (empty($value)) {
        unset($whereClauses[$key]);
      }
    }
    if (!empty($whereClauses)) {
      $where = implode(' AND ', $whereClauses);
    }
    return $where;
  }

  /**
   * Method to set the clause for only active where clause
   *
   * @return string
   */
  private function addOnlyActiveWhereClause() {
    $result = NULL;
    if (isset($this->_filters['only_active']) && $this->_filters['only_active'] == 1) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDate = $config->getEndDateCustomColumnName();
      $result = '(pg.'.$endDate.' IS NULL OR '.$endDate.' >= CURDATE())';
    }
    return $result;
  }

  /**
   * Method to set the start date from where clause
   *
   * @param int $whereIndex
   * @return string
   */
  private function addStartDateFromWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['start_date_from']) && !empty($this->_filters['start_date_from'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $startDateFrom = new DateTime($this->_filters['start_date_from']);
      $whereIndex++;
      $result = '(pg.'.$config->getStartDateCustomColumName().' >= %'.$whereIndex. ')';
      $this->_queryParams[$whereIndex] = array($startDateFrom->format('Y-m-d h:i:s'), 'String');
    }
    return $result;
  }

  /**
   * Method to set the end date from where clause
   *
   * @param int $whereIndex
   * @return string
   */
  private function addEndDateFromWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['end_date_from']) && !empty($this->_filters['end_date_from'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDateFrom = new DateTime($this->_filters['end_date_from']);
      $whereIndex++;
      $result = '(pg.'.$config->getEndDateCustomColumName().' >= %'.$whereIndex. ')';
      $this->_queryParams[$whereIndex] = array($endDateFrom->format('Y-m-d h:i:s'), 'String');
    }
    return $result;
  }

  /**
   * Method to set the end date to where clause
   *
   * @param int $whereIndex
   * @return string
   */
  private function addEndDateToWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['end_date_to']) && !empty($this->_filters['end_date_to'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDateTo = new DateTime($this->_filters['end_date_to']);
      $whereIndex++;
      $result = '(pg.'.$config->getEndDateCustomColumName().' <= %'.$whereIndex. ')';
      $this->_queryParams[$whereIndex] = array($endDateTo->format('Y-m-d h:i:s'), 'String');
    }
    return $result;
  }

  /**
   * Method to set the start date to where clause
   *
   * @param int $whereIndex
   * @return string
   */
  private function addStartDateToWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['start_date_to']) && !empty($this->_filters['start_date_to'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $startDateTo = new DateTime($this->_filters['start_date_to']);
      $whereIndex++;
      $result = '(pg.'.$config->getStartDateCustomColumName().' <= %'.$whereIndex. ')';
      $this->_queryParams[$whereIndex] = array($startDateTo->format('Y-m-d h:i:s'), 'String');
    }
    return $result;
  }

  /**
   * Method to set the post code where clauses
   *
   * @param int $whereIndex
   * @return string
   */
  private function addPostCodeWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['post_codes']) && !empty($this->_filters['post_codes'])) {
      foreach ($this->_filters['post_codes'] as $postCode) {
        $whereIndex++;
        $postCodes[] = '%'.$whereIndex;
        $this->_queryParams[$whereIndex] = array($this->_postCodesList[$postCode], 'String');
      }
      if (!empty($postCodes)) {
        $result = '(adr.postal_code IN('.implode(', ', $postCodes).'))';
      }
    }
    return $result;
  }


  /**
   * Method to set the tag where clauses
   *
   * @param int $whereIndex
   * @return string
   */
  private function addTagWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['tag_ids']) && !empty($this->_filters['tag_ids'])) {
      foreach ($this->_filters['tag_ids'] as $tagId) {
        $whereIndex++;
        $tagIds[] = '%'.$whereIndex;
        $this->_queryParams[$whereIndex] = array($tagId, 'Integer');
      }
      if (!empty($tagIds)) {
        $result = '(et.tag_id IN('.implode(', ', $tagIds).'))';
      }
    }
    return $result;
  }

  /**
   * Method to set the group where clauses
   *
   * @param int $whereIndex
   * @return string
   */
  private function addGroupWhereClauses(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['group_ids']) && !empty($this->_filters['group_ids'])) {
      foreach ($this->_filters['group_ids'] as $groupId) {
        $whereIndex++;
        $groupIds[] = '%'.$whereIndex;
        $this->_queryParams[$whereIndex] = array($groupId, 'Integer');
      }
      if (!empty($groupIds)) {
        $result = '(gc.group_id IN('.implode(', ', $groupIds).'))';
      }
    }
    return $result;
  }

  /**
   * Method to set the campaign where clauses
   *
   * @param int $whereIndex
   * @return string
   */
  private function addCampaignWhereClauses(&$whereIndex) {
    $result = NULL;
    $config = CRM_Printedgiro_Config::singleton();
    if (isset($this->_filters['campaign_ids']) && !empty($this->_filters['campaign_ids'])) {
      foreach ($this->_filters['campaign_ids'] as $campaignId) {
        $whereIndex++;
        $campaignIds[] = '%'.$whereIndex;
        $this->_queryParams[$whereIndex] = array($campaignId, 'Integer');
      }
      if (!empty($campaignIds)) {
        $result = '(pg.'.$config->getCampaignCustomColumnName().' IN('.implode(', ', $campaignIds).'))';
      }
    }
    return $result;
  }


  /**
   * Method to set the contact name where clause
   *
   * @param int $whereIndex
   * @return string
   */
  private function addContactNameWhereClause(&$whereIndex) {
    $result = NULL;
    if (isset($this->_filters['contact_name']) && !empty($this->_filters['contact_name'])) {
      $whereIndex++;
      $result = '(cc.sort_name LIKE %'.$whereIndex. ')';
      $this->_queryParams[$whereIndex] = array('%'.$this->_filters['contact_name'].'%', 'String');
    }
    return $result;
  }

  /**
   * Method to set the frequency where clauses
   *
   * @param int $whereIndex
   * @return string
   */
  private function addFrequencyWhereClauses(&$whereIndex) {
    $result = NULL;
    $config = CRM_Printedgiro_Config::singleton();
    if (isset($this->_filters['frequency_ids']) && !empty($this->_filters['frequency_ids'])) {
      foreach ($this->_filters['frequency_ids'] as $frequencyId) {
        $whereIndex++;
        $frequencyIds[] = '%'.$whereIndex;
        $this->_queryParams[$whereIndex] = array($frequencyId, 'Integer');
      }
      if (!empty($frequencyIds)) {
        $result = '(pg.'.$config->getFrequencyCustomColumnName().' IN('.implode(', ', $frequencyIds).'))';
      }
    }
    return $result;
  }



  /**
   * Method to set the from part of the query
   */
  private function setFrom() {
    $config = CRM_Printedgiro_Config::singleton();
    $from = $config->getPrintedCustomTableName()." pg
      JOIN civicrm_contact cc ON pg.entity_id = cc.id
      LEFT JOIN civicrm_address adr ON cc.id = adr.contact_id AND adr.is_primary = 1
      LEFT JOIN civicrm_state_province sp ON adr.state_province_id = sp.id
      LEFT JOIN civicrm_country ctr ON adr.country_id = ctr.id";
    // add civicrm_group_contact if required
    if (isset($this->_filters['group_ids']) && !empty($this->_filters['group_ids'])) {
      $from .= " JOIN civicrm_group_contact gc ON cc.id = gc.contact_id";
    }
    // add civicrm_entity_tag if required
    if (isset($this->_filters['tag_ids']) && !empty($this->_filters['tag_ids'])) {
      $from .= " JOIN civicrm_entity_tag et ON cc.id = et.entity_id AND entity_table = 'civicrm_contact'";
    }
    return $from;
  }

  /**
   * Method to set the required columns for select part of the query
   *
   * @return string
   */
  private function setSelect() {
    $config = CRM_Printedgiro_Config::singleton();
    return 'cc.id AS contact_id, cc.addressee_display AS addressee, cc.nick_name, adr.street_address, adr.postal_code, 
      adr.city, sp.name AS county, ctr.name AS country, pg.'.$config->getAmountCustomColumnName()
      .' AS amount, "" AS kid, pg.'.$config->getCampaignCustomColumnName().' AS campaign_id';
  }
}

