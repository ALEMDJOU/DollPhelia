<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/extractionfield.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaExtractionField
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Ophelia extracted field value
 */
class OpheliaExtractionField extends CommonObject
{
	public $module = 'ophelia';
	public $element = 'opheliaextractionfield';
	public $table_element = 'ophelia_extraction_field';
	public $picto = 'ophelia@ophelia';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'fk_result' => array('type' => 'integer:OpheliaExtractionResult:custom/ophelia/class/extractionresult.class.php', 'label' => 'Result', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => -2, 'index' => 1),
		'fk_template_field' => array('type' => 'integer:OpheliaTemplateField:custom/ophelia/class/templatefield.class.php', 'label' => 'TemplateField', 'enabled' => 1, 'position' => 20, 'notnull' => -1, 'visible' => -2),
		'field_name' => array('type' => 'varchar(128)', 'label' => 'FieldName', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'extracted_value' => array('type' => 'text', 'label' => 'ExtractedValue', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1),
		'corrected_value' => array('type' => 'text', 'label' => 'CorrectedValue', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'confidence_ocr' => array('type' => 'double(5,4)', 'label' => 'ConfidenceOcr', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => -2, 'default' => 0),
		'confidence_spatial' => array('type' => 'double(5,4)', 'label' => 'ConfidenceSpatial', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => -2, 'default' => 0),
		'confidence_valid' => array('type' => 'double(5,4)', 'label' => 'ConfidenceValid', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -2, 'default' => 0),
		'confidence_total' => array('type' => 'double(5,4)', 'label' => 'ConfidenceTotal', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => 1, 'default' => 0),
		'bbox_x1' => array('type' => 'integer', 'label' => 'BboxX1', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 0),
		'bbox_y1' => array('type' => 'integer', 'label' => 'BboxY1', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 0),
		'bbox_x2' => array('type' => 'integer', 'label' => 'BboxX2', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 0),
		'bbox_y2' => array('type' => 'integer', 'label' => 'BboxY2', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 0),
		'page_num' => array('type' => 'integer', 'label' => 'PageNum', 'enabled' => 1, 'position' => 140, 'notnull' => 0, 'visible' => 0, 'default' => 1),
		'is_validated' => array('type' => 'integer', 'label' => 'IsValidated', 'enabled' => 1, 'position' => 150, 'notnull' => 1, 'visible' => 1, 'default' => 0),
	);

	public $rowid;
	public $fk_result;
	public $fk_template_field;
	public $field_name;
	public $extracted_value;
	public $corrected_value;
	public $confidence_ocr;
	public $confidence_spatial;
	public $confidence_valid;
	public $confidence_total;
	public $bbox_x1;
	public $bbox_y1;
	public $bbox_x2;
	public $bbox_y2;
	public $page_num;
	public $is_validated;

	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct(DoliDB $db)
	{
		$this->db = $db;
	}

	/**
	 * Create object into database
	 *
	 * @param User $user      User that creates
	 * @param int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, Id of created object if OK
	 */
	public function create(User $user, $notrigger = 1)
	{
		return $this->createCommon($user, $notrigger);
	}

	/**
	 * Load object in memory from the database
	 *
	 * @param int    $id  Id object
	 * @param string $ref Ref
	 * @return int         Return integer <0 if KO, 0 if not found, >0 if OK
	 */
	public function fetch($id, $ref = null)
	{
		return $this->fetchCommon($id, $ref);
	}

	/**
	 * Load list of objects in memory from the database.
	 *
	 * @param string $sortorder Sort Order
	 * @param string $sortfield Sort field
	 * @param int    $limit     Limit
	 * @param int    $offset    Offset
	 * @param string $filter    Universal search filter
	 * @return array|int        <0 if KO, array of objects if OK
	 */
	public function fetchAll($sortorder = 'ASC', $sortfield = 't.confidence_total', $limit = 1000, $offset = 0, $filter = '')
	{
		$records = array();

		$sql = "SELECT ".$this->getFieldList('t');
		$sql .= " FROM ".$this->db->prefix().$this->table_element." as t";
		$sql .= " WHERE 1 = 1";

		$errormessage = '';
		$sql .= forgeSQLFromUniversalSearchCriteria($filter, $errormessage);
		if ($errormessage) {
			$this->errors[] = $errormessage;
			return -1;
		}

		if (!empty($sortfield)) {
			$sql .= $this->db->order($sortfield, $sortorder);
		}
		if (!empty($limit)) {
			$sql .= $this->db->plimit($limit, $offset);
		}

		$resql = $this->db->query($sql);
		if ($resql) {
			$num = $this->db->num_rows($resql);
			$i = 0;
			while ($i < ($limit ? min($limit, $num) : $num)) {
				$obj = $this->db->fetch_object($resql);
				$record = new self($this->db);
				$record->setVarsFromFetchObj($obj);
				$records[$record->id] = $record;
				$i++;
			}
			$this->db->free($resql);
			return $records;
		}

		$this->errors[] = 'Error '.$this->db->lasterror();
		return -1;
	}

	/**
	 * Update object into database
	 *
	 * @param User $user      User that modifies
	 * @param int  $notrigger 0=launch triggers after, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function update(User $user, $notrigger = 1)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database
	 *
	 * @param User $user      User that deletes
	 * @param int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 1)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Return the effective value: corrected value if set, else the raw extracted value
	 *
	 * @return string|null Effective value
	 */
	public function getEffectiveValue()
	{
		return ($this->corrected_value !== null && $this->corrected_value !== '') ? $this->corrected_value : $this->extracted_value;
	}
}
