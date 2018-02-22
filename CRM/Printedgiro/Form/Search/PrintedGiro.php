<?php
/*
 +--------------------------------------------------------------------+
 | CiviCRM version 4.7                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2016                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
 */

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2016
 */
class CRM_Printedgiro_Form_Search_PrintedGiro extends CRM_Contact_Form_Search_Custom_Base implements CRM_Contact_Form_Search_Interface {

  private $_postCodesList = array();
  private $_whereClauses = array();
  private $_whereParams = array();
  private $_whereIndex = 0;

  /**
   * Class constructor.
   *
   * @param array $formValues
   */
  public function __construct(&$formValues) {
    $this->setPostCodesList();
    parent::__construct($formValues);

    $this->_columns = array(
      // If possible, don't use aliases for the columns you select.
      // You can prefix columns with table aliases, if needed.
      //
      // If you don't do this, selecting individual records from the
      // custom search result won't work if your results are sorted on the
      // aliased colums.
      // (This is why we map Contact ID on contact_a.id, and not on contact_id).
      ts('Donor ID') => 'contact_a.id',
      ts('Donor Name') => 'contact_name',
      ts('Donor Type') => 'contact_type',
      ts('Campaign') => 'campaign',
      ts('Amount') => 'amount',
      ts('Frequency') => 'frequency',
      ts('Start Date') => 'start_date',
      ts('End Date') => 'end_date',
      ts('Birth Date') => 'birth_date',
      ts('Gender') => 'gender',
      ts('Postal Code') => 'postal_code'
    );
  }

  /**
   * @param CRM_Core_Form $form
   */
  public function buildForm(&$form) {
    CRM_Utils_System::setTitle(ts('Contacts with Printed Giro'));

    $form->add('text', 'contact_name', ts('Donor Name contains'), TRUE);
    $form->add('select', 'campaign_ids', ts('Campaign(s)'), $this->setCampaignList(), FALSE,
      array('id' => 'campaign_ids', 'multiple' => 'multiple', 'title' => ts('- select -'), 'class' => 'crm-select2'));
    $form->add('select', 'frequency_ids', ts('Frequency(s)'), $this->setFrequencyList(), FALSE,
      array('id' => 'frequency_ids', 'multiple' => 'multiple', 'title' => ts('- select -'), 'class' => 'crm-select2'));
    $form->add('select', 'group_ids', ts('Group(s)'), $this->setGroupList(), FALSE,
      array('id' => 'group_ids', 'multiple' => 'multiple', 'title' => ts('- select -'), 'class' => 'crm-select2'));
    $form->add('select', 'tag_ids', ts('Tag(s)'), $this->setTagList(), FALSE,
      array('id' => 'tag_ids', 'multiple' => 'multiple', 'title' => ts('- select -'), 'class' => 'crm-select2'));
    $form->add('select', 'post_codes', ts('Post Code(s)'), $this->_postCodesList, FALSE,
      array('id' => 'post_codes', 'multiple' => 'multiple', 'title' => ts('- select -'), 'class' => 'crm-select2'));
    $form->addDate('start_date_from', ts('Start Date from'), FALSE);
    $form->addDate('start_date_to', ts('... to'), FALSE);
    $form->addDate('end_date_from', ts('End Date from'), FALSE);
    $form->addDate('end_date_to', ts('... to'), FALSE);
    $onlyActives = array(
      '1' => ts('Only active printed giros'),
      '0' => ts('All printed giros'),
    );
    $form->addRadio('only_active', ts('Only active?'), $onlyActives, NULL, '<br />', TRUE);

    // Optionally define default search values
    $form->setDefaults(array(
      'contact_name' => '',
      'only_active' => '1',
    ));

    /**
     * if you are using the standard template, this array tells the template what elements
     * are part of the search criteria
     */
    $form->assign('elements', array(
      'contact_name',
      'campaign_ids',
      'frequency_ids',
      'group_ids',
      'tag_ids',
      'post_codes',
      'start_date_from',
      'start_date_to',
      'end_date_from',
      'end_date_to',
      'only_active',));  }

  /**
   * @return array
   */
  public function summary() {
    $summary = array();
    return $summary;
  }

  /**
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $returnSQL
   *
   * @return string
   */
  public function contactIDs($offset = 0, $rowcount = 0, $sort = NULL, $returnSQL = FALSE) {
    return $this->all($offset, $rowcount, $sort, FALSE, TRUE);
  }

  /**
   * @param int $offset
   * @param int $rowcount
   * @param null $sort
   * @param bool $includeContactIDs
   * @param bool $justIDs
   *
   * @return string
   */
  public function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {
    if ($justIDs) {
      $selectClause = "contact_a.id as contact_id";
      // Don't change sort order when $justIDs is TRUE, see CRM-14920.
    }
    else {
      // We select contact_a.id twice. Once as contact_a.id,
      // because it is used to fill the prevnext_cache. And once
      // as contact_a.id, for the patch of CRM-16587 to work when
      // the results are sorted on contact ID.
      $selectClause = "
      contact_a.id AS contact_id ,
      contact_a.id AS id ,
      contact_a.contact_type,
      contact_a.display_name AS contact_name,
      contact_a.birth_date,
      ovg.label AS gender,
      av.maf_printed_giro_campaign AS campaign_id,
      cp.title AS campaign,
      av.maf_printed_giro_frequency AS frequency_id,
      ov.label AS frequency,
      av.maf_printed_giro_start_date AS start_date,
      av.maf_printed_giro_end_date AS end_date,
      av.maf_printed_giro_amount AS amount,
      adr.postal_code
";
    }
    return $this->sql($selectClause,
      $offset, $rowcount, $sort,
      $includeContactIDs, NULL
    );
  }

  /**
   * Construct a SQL FROM clause
   *
   * @return string, sql fragment with FROM and JOIN clauses
   */
  function from() {
    try {
      $genderOptionGroupId = civicrm_api3('OptionGroup', 'getvalue', array(
        'name' => 'gender',
        'return' => 'id',
      ));
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    $config = CRM_Printedgiro_Config::singleton();
    $from = "
      FROM ".$config->getPrintedCustomTableName()." av
      JOIN civicrm_contact contact_a ON av.entity_id = contact_a.id
      LEFT JOIN civicrm_campaign cp ON av.".$config->getCampaignCustomColumnName()." = cp.id
      LEFT JOIN civicrm_address adr ON contact_a.id = adr.contact_id AND adr.is_primary = 1
      LEFT JOIN civicrm_option_value ov ON av.".$config->getFrequencyCustomColumnName()." = ov.value AND ov.option_group_id = "
      .$config->getFrequencyOptionGroupId()."
      LEFT JOIN civicrm_option_value ovg ON contact_a.gender_id = ovg.value AND ovg.option_group_id = "
      .$genderOptionGroupId;
    // add civicrm_group_contact if required
    if (isset($this->_formValues['group_ids']) && !empty($this->_formValues['group_ids'])) {
      $from .= " JOIN civicrm_group_contact gc ON contact_a.id = gc.contact_id";
    }
    // add civicrm_entity_tag if required
    if (isset($this->_formValues['tag_ids']) && !empty($this->_formValues['tag_ids'])) {
      $from .= " JOIN civicrm_entity_tag et ON contact_a.id = et.entity_id AND entity_table = 'civicrm_contact'";
    }
    return $from;
  }

  /**
   * Construct a SQL WHERE clause
   *
   * @param bool $includeContactIDs
   * @return string, sql fragment with conditional expressions
   */
  function where($includeContactIDs = FALSE) {
    $this->_whereClauses = array('(contact_a.is_deleted = %1)');
    $this->_whereParams = array(1 => array(0, 'Integer'));
    $this->_whereIndex = 1;
    $this->addContactNameWhereClause();
    $this->addCampaignWhereClauses();
    $this->addFrequencyWhereClauses();
    $this->addGroupWhereClauses();
    $this->addTagWhereClauses();
    $this->addPostCodeWhereClauses();
    $this->addStartDateWhereClauses();
    $this->addEndDateWhereClauses();
    $this->addOnlyActiveWhereClause();
    if (!empty($this->_whereClauses)) {
      $where = implode(' AND ', $this->_whereClauses);
    }
    return $this->whereClause($where, $this->_whereParams);
  }

  /**
   * Method to set the tag clauses
   */
  private function addTagWhereClauses() {
    if (isset($this->_formValues['tag_ids']) && !empty($this->_formValues['tag_ids'])) {
      foreach ($this->_formValues['tag_ids'] as $tagId) {
        $this->_whereIndex++;
        $tagIds[] = '%'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($tagId, 'Integer');
      }
      if (!empty($tagIds)) {
        $this->_whereClauses[] = '(et.tag_id IN('.implode(', ', $tagIds).'))';
      }
    }
  }

  /**
   * Method to set the group clauses
   */
  private function addGroupWhereClauses() {
    if (isset($this->_formValues['group_ids']) && !empty($this->_formValues['group_ids'])) {
      foreach ($this->_formValues['group_ids'] as $groupId) {
        $this->_whereIndex++;
        $groupIds[] = '%'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($groupId, 'Integer');
      }
      if (!empty($groupIds)) {
        $this->_whereClauses[] = '(gc.group_id IN('.implode(', ', $groupIds).'))';
      }
    }
  }

  /**
   * Method to set the clause for only active if set
   */
  private function addOnlyActiveWhereClause() {
    if (isset($this->_formValues['only_active']) && $this->_formValues['only_active'] == 1) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDate = $config->getEndDateCustomColumnName();
      $this->_whereClauses[] = '(av.'.$endDate.' IS NULL OR '.$endDate.' >= CURDATE())';
    }
  }

  /**
   * Method to set the start date clause
   */
  private function addStartDateWhereClauses() {
    if (isset($this->_formValues['start_date_from']) && !empty($this->_formValues['start_date_from'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $startDateFrom = new DateTime($this->_formValues['start_date_from']);
      $this->_whereIndex++;
      $this->_whereClauses[] = '(av.'.$config->getStartDateCustomColumName().' >= %'.$this->_whereIndex. ')';
      $this->_whereParams[$this->_whereIndex] = array($startDateFrom->format('Y-m-d h:i:s'), 'String');
    }
    if (isset($this->_formValues['start_date_to']) && !empty($this->_formValues['start_date_to'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $startDateTo = new DateTime($this->_formValues['start_date_to']);
      $this->_whereIndex++;
      $this->_whereClauses[] = '(av.'.$config->getStartDateCustomColumName().' <= %'.$this->_whereIndex. ')';
      $this->_whereParams[$this->_whereIndex] = array($startDateTo->format('Y-m-d h:i:s'), 'String');
    }
  }

  /**
   * Method to set the end date clause
   */
  private function addEndDateWhereClauses() {
    if (isset($this->_formValues['end_date_from']) && !empty($this->_formValues['end_date_from'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDateFrom = new DateTime($this->_formValues['end_date_from']);
      $this->_whereIndex++;
      $this->_whereClauses[] = '(av.'.$config->getEndDateCustomColumName().' >= %'.$this->_whereIndex. ')';
      $this->_whereParams[$this->_whereIndex] = array($endDateFrom->format('Y-m-d h:i:s'), 'String');
    }
    if (isset($this->_formValues['end_date_to']) && !empty($this->_formValues['end_date_to'])) {
      $config = CRM_Printedgiro_Config::singleton();
      $endDateTo = new DateTime($this->_formValues['end_date_to']);
      $this->_whereIndex++;
      $this->_whereClauses[] = '(av.'.$config->getEndDateCustomColumName().' <= %'.$this->_whereIndex. ')';
      $this->_whereParams[$this->_whereIndex] = array($endDateTo->format('Y-m-d h:i:s'), 'String');
    }
  }

  /**
   * Method to set the post code where clauses
   */
  private function addPostCodeWhereClauses() {
    if (isset($this->_formValues['post_codes']) && !empty($this->_formValues['post_codes'])) {
      foreach ($this->_formValues['post_codes'] as $postCode) {
        $this->_whereIndex++;
        $postCodes[] = '%'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($this->_postCodesList[$postCode], 'String');
      }
      if (!empty($postCodes)) {
        $this->_whereClauses[] = '(adr.postal_code IN('.implode(', ', $postCodes).'))';
      }
    }
  }

  /**
   * Method to set the frequency where clauses
   */
  private function addFrequencyWhereClauses() {
    $config = CRM_Printedgiro_Config::singleton();
    if (isset($this->_formValues['frequency_ids']) && !empty($this->_formValues['frequency_ids'])) {
      foreach ($this->_formValues['frequency_ids'] as $frequencyId) {
        $this->_whereIndex++;
        $frequencyIds[] = '%'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($frequencyId, 'Integer');
      }
      if (!empty($frequencyIds)) {
        $this->_whereClauses[] = '(av.'.$config->getFrequencyCustomColumnName().' IN('.implode(', ', $frequencyIds).'))';
      }
    }
  }

  /**
   * Method to set the campaign where clauses
   */
  private function addCampaignWhereClauses() {
    $config = CRM_Printedgiro_Config::singleton();
    if (isset($this->_formValues['campaign_ids']) && !empty($this->_formValues['campaign_ids'])) {
      foreach ($this->_formValues['campaign_ids'] as $campaignId) {
        $this->_whereIndex++;
        $campaignIds[] = '%'.$this->_whereIndex;
        $this->_whereParams[$this->_whereIndex] = array($campaignId, 'Integer');
      }
      if (!empty($campaignIds)) {
        $this->_whereClauses[] = '(av.'.$config->getCampaignCustomColumnName().' IN('.implode(', ', $campaignIds).'))';
      }
    }
  }

  /**
   * Method to set the contact name where clause
   */
  private function addContactNameWhereClause() {
    if (isset($this->_formValues['contact_name']) && !empty($this->_formValues['contact_name'])) {
      $this->_whereIndex++;
      $this->_whereClauses[] = '(contact_a.sort_name LIKE %'.$this->_whereIndex. ')';
      $this->_whereParams[$this->_whereIndex] = array('%'.$this->_formValues['contact_name'].'%', 'String');
    }
  }

  /**
   * @return string
   */
  public function templateFile() {
    return 'CRM/Contact/Form/Search/Custom.tpl';
  }

  /**
   * Method to get the group select list
   *
   * @return array
   */
  private function setGroupList() {
    $result = array();
    try {
      $groups = civicrm_api3('Group', 'get', array(
        'is_active' => 1,
        'options' => array('limit' => 0,),
      ));
      foreach ($groups['values'] as $group) {
        $result[$group['id']] = $group['title'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    asort($result);
    return $result;
  }

  /**
   * Method to get the tag select list
   *
   * @return array
   */
  private function setTagList() {
    $result = array();
    try {
      $tags = civicrm_api3('Tag', 'get', array(
        'options' => array('limit' => 0,),
      ));
      foreach ($tags['values'] as $tag) {
        if (strpos($tag['used_for'], 'civicrm_contact') !== FALSE) {
          $result[$tag['id']] = $tag['name'];
        }
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    asort($result);
    return $result;
  }

  /**
   * Method to get the campaign select list
   *
   * @return array
   */
  private function setCampaignList() {
    $result = array();
    try {
      $campaigns = civicrm_api3('Campaign', 'get', array(
        'is_active' => 1,
        'options' => array('limit' => 0,),
      ));
      foreach ($campaigns['values'] as $campaign) {
        $result[$campaign['id']] = $campaign['title'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    asort($result);
    return $result;
  }

  /**
   * Method to get the post code select list
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
   * Method to get the frequency select list
   *
   * @return array
   */
  private function setFrequencyList() {
    $result = array();
    try {
      $optionValues = civicrm_api3('OptionValue', 'get', array(
        'option_group_id' => 'maf_printed_giro_frequency',
        'is_active' => 1,
        'options' => array('limit' => 0)
      ));
      foreach ($optionValues['values'] as $optionValue) {
        $result[$optionValue['value']] = $optionValue['label'];
      }
    }
    catch (CiviCRM_API3_Exception $ex) {
    }
    asort($result);
    return $result;
  }

  /**
   * Modify the content of each row
   *
   * @param array $row modifiable SQL result row
   * @return void
   */
  function alterRow(&$row) {
    $dateFields = array('start_date', 'end_date', 'birth_date');
    foreach ($dateFields as $dateField) {
      if (isset($row[$dateField])) {
        $formatDate = new DateTime($row[$dateField]);
        $row[$dateField] = $formatDate->format('d-m-Y');
      }
    }
    if (isset($row['amount'])) {
      $row['amount'] = CRM_Utils_Money::format($row['amount'], 'NOK');
    }
  }
}
