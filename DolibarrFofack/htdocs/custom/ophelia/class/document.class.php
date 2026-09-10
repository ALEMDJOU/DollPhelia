<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/document.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaDocument
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Ophelia document
 */
class OpheliaDocument extends CommonObject
{
	/** @var string ID of module */
	public $module = 'ophelia';
	/** @var string ID to identify managed object */
	public $element = 'opheliadocument';
	/** @var string Name of table without prefix */
	public $table_element = 'ophelia_document';
	/** @var string Picto */
	public $picto = 'ophelia@ophelia';
	/** @var int Does object support extrafields ? */
	public $isextrafieldmanaged = 0;
	/** @var int Does this object support multicompany module ? */
	public $ismultientitymanaged = 0;

	const STATUS_UPLOADED = 0;
	const STATUS_PROCESSING = 1;
	const STATUS_PROCESSED = 2;
	const STATUS_VALIDATED = 3;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 0, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'filename' => array('type' => 'varchar(255)', 'label' => 'FileName', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'filepath' => array('type' => 'varchar(512)', 'label' => 'FilePath', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 0),
		'filetype' => array('type' => 'varchar(50)', 'label' => 'FileType', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'filesize' => array('type' => 'integer', 'label' => 'FileSize', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1),
		'doc_type' => array('type' => 'varchar(50)', 'label' => 'DocType', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1, 'index' => 1),
		'status' => array('type' => 'integer', 'label' => 'Status', 'enabled' => 1, 'position' => 80, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'default' => 0, 'arrayofkeyval' => array(0 => 'OpheliaStatusUploaded', 1 => 'OpheliaStatusProcessing', 2 => 'OpheliaStatusProcessed', 3 => 'OpheliaStatusValidated')),
		'fk_user_upload' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserUpload', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => -2),
		'fk_template' => array('type' => 'integer:OpheliaTemplate:custom/ophelia/class/template.class.php', 'label' => 'Template', 'enabled' => 1, 'position' => 100, 'notnull' => -1, 'visible' => 1, 'index' => 1),
		'matching_score' => array('type' => 'double(5,4)', 'label' => 'MatchingScore', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1),
		'strategy_used' => array('type' => 'varchar(50)', 'label' => 'StrategyUsed', 'enabled' => 1, 'position' => 120, 'notnull' => 0, 'visible' => 1),
		'task_id' => array('type' => 'varchar(255)', 'label' => 'TaskId', 'enabled' => 1, 'position' => 130, 'notnull' => 0, 'visible' => 0),
		'date_upload' => array('type' => 'datetime', 'label' => 'DateUpload', 'enabled' => 1, 'position' => 140, 'notnull' => 1, 'visible' => 1),
		'date_processing' => array('type' => 'datetime', 'label' => 'DateProcessing', 'enabled' => 1, 'position' => 150, 'notnull' => 0, 'visible' => -2),
		'date_validation' => array('type' => 'datetime', 'label' => 'DateValidation', 'enabled' => 1, 'position' => 160, 'notnull' => 0, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 500, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $ref;
	public $label;
	public $filename;
	public $filepath;
	public $filetype;
	public $filesize;
	public $doc_type;
	public $status;
	public $fk_user_upload;
	public $fk_template;
	public $matching_score;
	public $strategy_used;
	public $task_id;
	public $date_upload;
	public $date_processing;
	public $date_validation;

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
	 * @param User $user       User that creates
	 * @param int  $notrigger  0=launch triggers after, 1=disable triggers
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
	 * Delete object in database
	 *
	 * @param User $user      User that deletes
	 * @param int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		return $this->deleteCommon($user, $notrigger);
	}

	/**
	 * Return label of status
	 *
	 * @param int $mode 0=long label, 1=short label, 2=Picto + short label, 3=Picto
	 * @return string   Label
	 */
	public function getLibStatut($mode = 0)
	{
		global $langs;

		$labels = array(
			self::STATUS_UPLOADED => $langs->trans('OpheliaStatusUploaded'),
			self::STATUS_PROCESSING => $langs->trans('OpheliaStatusProcessing'),
			self::STATUS_PROCESSED => $langs->trans('OpheliaStatusProcessed'),
			self::STATUS_VALIDATED => $langs->trans('OpheliaStatusValidated'),
		);
		$statusType = 'status'.((int) $this->status);
		if ($this->status == self::STATUS_VALIDATED) {
			$statusType = 'status4';
		}

		return dolGetStatus($labels[$this->status] ?? '', $labels[$this->status] ?? '', '', $statusType, $mode);
	}

	/**
	 * Return a link to the object card
	 *
	 * @param int    $withpicto Include picto in link
	 * @param string $option    Link option
	 * @return string            String with URL
	 */
	public function getNomUrl($withpicto = 0, $option = '')
	{
		$url = dol_buildpath('/ophelia/document_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object($this->ref, $this->picto, 'class="paddingright"');
		}
		$result .= $this->ref;
		$result .= '</a>';

		return $result;
	}
}
