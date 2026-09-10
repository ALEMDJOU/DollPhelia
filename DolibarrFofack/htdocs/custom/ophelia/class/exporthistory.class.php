<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/exporthistory.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaExportHistory
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Ophelia export history entry
 */
class OpheliaExportHistory extends CommonObject
{
	public $module = 'ophelia';
	public $element = 'opheliaexporthistory';
	public $table_element = 'ophelia_export_history';
	public $picto = 'ophelia@ophelia';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'fk_result' => array('type' => 'integer:OpheliaExtractionResult:custom/ophelia/class/extractionresult.class.php', 'label' => 'Result', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'fk_user' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'User', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1),
		'export_format' => array('type' => 'varchar(10)', 'label' => 'ExportFormat', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'filepath' => array('type' => 'varchar(512)', 'label' => 'FilePath', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'date_export' => array('type' => 'datetime', 'label' => 'DateExport', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => 1),
	);

	public $rowid;
	public $fk_result;
	public $fk_user;
	public $export_format;
	public $filepath;
	public $date_export;

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
}
