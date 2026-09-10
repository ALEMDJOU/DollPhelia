<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/extractionresult.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaExtractionResult
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionfield.class.php';

/**
 * Class for Ophelia extraction result
 */
class OpheliaExtractionResult extends CommonObject
{
	public $module = 'ophelia';
	public $element = 'opheliaextractionresult';
	public $table_element = 'ophelia_extraction_result';
	public $picto = 'ophelia@ophelia';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	const STATUS_RAW = 0;
	const STATUS_VALIDATED = 1;
	const STATUS_EXPORTED = 2;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'fk_document' => array('type' => 'integer:OpheliaDocument:custom/ophelia/class/document.class.php', 'label' => 'Document', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_template' => array('type' => 'integer:OpheliaTemplate:custom/ophelia/class/template.class.php', 'label' => 'Template', 'enabled' => 1, 'position' => 20, 'notnull' => -1, 'visible' => 1),
		'strategy' => array('type' => 'varchar(50)', 'label' => 'Strategy', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 1),
		'global_confidence' => array('type' => 'double(5,4)', 'label' => 'GlobalConfidence', 'enabled' => 1, 'position' => 40, 'notnull' => 0, 'visible' => 1, 'default' => 0),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 0, 'index' => 1, 'arrayofkeyval' => array(0 => 'OpheliaResultRaw', 1 => 'OpheliaResultValidated', 2 => 'OpheliaResultExported')),
		'fk_user_validator' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserValidator', 'enabled' => 1, 'position' => 60, 'notnull' => -1, 'visible' => -2),
		'date_extraction' => array('type' => 'datetime', 'label' => 'DateExtraction', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 1),
		'date_validation' => array('type' => 'datetime', 'label' => 'DateValidation', 'enabled' => 1, 'position' => 510, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 520, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $fk_document;
	public $fk_template;
	public $strategy;
	public $global_confidence;
	public $status;
	public $fk_user_validator;
	public $date_extraction;
	public $date_validation;

	/** @var OpheliaExtractionField[] */
	public $lines = array();

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
	public function create(User $user, $notrigger = 0)
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
		$result = $this->fetchCommon($id, $ref);
		if ($result > 0) {
			$this->fetchFields();
		}
		return $result;
	}

	/**
	 * Load the extracted field values for this result
	 *
	 * @return int Return integer <0 if KO, >0 if OK
	 */
	public function fetchFields()
	{
		$field = new OpheliaExtractionField($this->db);
		$result = $field->fetchAll('ASC', 't.confidence_total', 0, 0, '(fk_result:=:'.((int) $this->id).')');
		if (is_array($result)) {
			$this->lines = array_values($result);
			return 1;
		}
		return -1;
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
	public function fetchAll($sortorder = 'DESC', $sortfield = 't.rowid', $limit = 1000, $offset = 0, $filter = '')
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
	public function update(User $user, $notrigger = 0)
	{
		return $this->updateCommon($user, $notrigger);
	}

	/**
	 * Delete object in database (and its extracted fields)
	 *
	 * @param User $user      User that deletes
	 * @param int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();

		$sql = "DELETE FROM ".$this->db->prefix()."ophelia_extraction_field WHERE fk_result = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$result = $this->deleteCommon($user, $notrigger);
		if ($result < 0) {
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();
		return 1;
	}

	/**
	 * Validate the extraction result: apply corrections, flag fields validated, set status
	 *
	 * @param User $user User that validates
	 * @return int        Return integer <0 if KO, >0 if OK
	 */
	public function validateResult(User $user)
	{
		$this->db->begin();

		$sql = "UPDATE ".$this->db->prefix()."ophelia_extraction_result";
		$sql .= " SET status = ".self::STATUS_VALIDATED.", fk_user_validator = ".((int) $user->id);
		$sql .= ", date_validation = '".$this->db->idate(dol_now())."'";
		$sql .= " WHERE rowid = ".((int) $this->id);
		$resql = $this->db->query($sql);
		if (!$resql) {
			$this->error = $this->db->lasterror();
			$this->db->rollback();
			return -1;
		}

		$this->db->commit();

		$this->status = self::STATUS_VALIDATED;
		$this->call_trigger('OPHELIA_EXTRACTION_VALIDATE', $user);

		return 1;
	}

	/**
	 * Return a link to the object card
	 *
	 * @param int $withpicto Include picto in link
	 * @return string          String with URL
	 */
	public function getNomUrl($withpicto = 0)
	{
		$url = dol_buildpath('/ophelia/extraction_result.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object('', $this->picto, 'class="paddingright"');
		}
		$result .= '#'.$this->id;
		$result .= '</a>';

		return $result;
	}
}
