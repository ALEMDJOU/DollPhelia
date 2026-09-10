<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/templatefield.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaTemplateField
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';

/**
 * Class for Ophelia template field (spatial anchor)
 */
class OpheliaTemplateField extends CommonObject
{
	public $module = 'ophelia';
	public $element = 'opheliatemplatefield';
	public $table_element = 'ophelia_template_field';
	public $picto = 'ophelia@ophelia';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'fk_template' => array('type' => 'integer:OpheliaTemplate:custom/ophelia/class/template.class.php', 'label' => 'Template', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => -2, 'index' => 1),
		'field_name' => array('type' => 'varchar(128)', 'label' => 'FieldName', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1),
		'field_label' => array('type' => 'varchar(255)', 'label' => 'FieldLabel', 'enabled' => 1, 'position' => 30, 'notnull' => 1, 'visible' => 1),
		'field_type' => array('type' => 'varchar(50)', 'label' => 'FieldType', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1),
		'key_text' => array('type' => 'varchar(255)', 'label' => 'KeyText', 'enabled' => 1, 'position' => 50, 'notnull' => 0, 'visible' => 1),
		'delta_x' => array('type' => 'double(24,8)', 'label' => 'DeltaX', 'enabled' => 1, 'position' => 60, 'notnull' => 0, 'visible' => 1, 'default' => 0),
		'delta_y' => array('type' => 'double(24,8)', 'label' => 'DeltaY', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => 1, 'default' => 0),
		'theta' => array('type' => 'double(24,8)', 'label' => 'Theta', 'enabled' => 1, 'position' => 80, 'notnull' => 0, 'visible' => -2, 'default' => 0),
		'distance' => array('type' => 'double(24,8)', 'label' => 'Distance', 'enabled' => 1, 'position' => 90, 'notnull' => 0, 'visible' => -2, 'default' => 0),
		'relation_type' => array('type' => 'varchar(20)', 'label' => 'RelationType', 'enabled' => 1, 'position' => 100, 'notnull' => 0, 'visible' => 1, 'default' => 'right_of'),
		'extraction_method' => array('type' => 'varchar(50)', 'label' => 'ExtractionMethod', 'enabled' => 1, 'position' => 110, 'notnull' => 0, 'visible' => 1, 'default' => 'spatial'),
		'required' => array('type' => 'integer', 'label' => 'Required', 'enabled' => 1, 'position' => 120, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'rang' => array('type' => 'integer', 'label' => 'Rank', 'enabled' => 1, 'position' => 130, 'notnull' => 1, 'visible' => 1, 'default' => 0),
	);

	public $rowid;
	public $fk_template;
	public $field_name;
	public $field_label;
	public $field_type;
	public $key_text;
	public $delta_x;
	public $delta_y;
	public $theta;
	public $distance;
	public $relation_type;
	public $extraction_method;
	public $required;
	public $rang;

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
	public function fetchAll($sortorder = 'ASC', $sortfield = 't.rang', $limit = 1000, $offset = 0, $filter = '')
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
}
