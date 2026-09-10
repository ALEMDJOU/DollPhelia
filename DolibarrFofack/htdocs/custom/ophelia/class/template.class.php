<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/template.class.php
 * \ingroup ophelia
 * \brief   CRUD class for OpheliaTemplate
 */

require_once DOL_DOCUMENT_ROOT.'/core/class/commonobject.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/templatefield.class.php';

/**
 * Class for Ophelia template
 */
class OpheliaTemplate extends CommonObject
{
	public $module = 'ophelia';
	public $element = 'opheliatemplate';
	public $table_element = 'ophelia_template';
	public $picto = 'ophelia@ophelia';
	public $isextrafieldmanaged = 0;
	public $ismultientitymanaged = 0;

	public $fields = array(
		'rowid' => array('type' => 'integer', 'label' => 'TechnicalID', 'enabled' => 1, 'position' => 1, 'notnull' => 1, 'visible' => 0, 'noteditable' => 1, 'index' => 1),
		'ref' => array('type' => 'varchar(128)', 'label' => 'Ref', 'enabled' => 1, 'position' => 10, 'notnull' => 1, 'visible' => 1, 'index' => 1, 'searchall' => 1, 'showoncombobox' => 1),
		'label' => array('type' => 'varchar(255)', 'label' => 'Label', 'enabled' => 1, 'position' => 20, 'notnull' => 1, 'visible' => 1, 'searchall' => 1, 'css' => 'minwidth300'),
		'description' => array('type' => 'text', 'label' => 'Description', 'enabled' => 1, 'position' => 30, 'notnull' => 0, 'visible' => 3),
		'doc_type' => array('type' => 'varchar(50)', 'label' => 'DocType', 'enabled' => 1, 'position' => 40, 'notnull' => 1, 'visible' => 1, 'index' => 1),
		'version' => array('type' => 'integer', 'label' => 'Version', 'enabled' => 1, 'position' => 50, 'notnull' => 1, 'visible' => 1, 'default' => 1),
		'active' => array('type' => 'integer', 'label' => 'Active', 'enabled' => 1, 'position' => 60, 'notnull' => 1, 'visible' => 1, 'default' => 1, 'index' => 1),
		'fk_user_author' => array('type' => 'integer:User:user/class/user.class.php', 'label' => 'UserAuthor', 'enabled' => 1, 'position' => 70, 'notnull' => 0, 'visible' => -2),
		'date_creation' => array('type' => 'datetime', 'label' => 'DateCreation', 'enabled' => 1, 'position' => 500, 'notnull' => 1, 'visible' => -2),
		'tms' => array('type' => 'timestamp', 'label' => 'DateModification', 'enabled' => 1, 'position' => 501, 'notnull' => 0, 'visible' => -2),
	);

	public $rowid;
	public $ref;
	public $label;
	public $description;
	public $doc_type;
	public $version;
	public $active;
	public $fk_user_author;
	public $date_creation;

	/** @var OpheliaTemplateField[] */
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
	 * Load the template fields (spatial anchors) for this template
	 *
	 * @return int Return integer <0 if KO, >0 if OK
	 */
	public function fetchFields()
	{
		$field = new OpheliaTemplateField($this->db);
		$result = $field->fetchAll('ASC', 't.rang', 0, 0, '(fk_template:=:'.((int) $this->id).')');
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
	 * Delete object in database (and its fields)
	 *
	 * @param User $user      User that deletes
	 * @param int  $notrigger 0=launch triggers, 1=disable triggers
	 * @return int             Return integer <0 if KO, >0 if OK
	 */
	public function delete(User $user, $notrigger = 0)
	{
		$this->db->begin();

		$sql = "DELETE FROM ".$this->db->prefix()."ophelia_template_field WHERE fk_template = ".((int) $this->id);
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
	 * Build the TemplateSchema-compatible array expected by ophelia-service
	 *
	 * @return array Template schema
	 */
	public function toApiSchema()
	{
		$fields = array();
		foreach ($this->lines as $line) {
			$fields[] = array(
				'field_name' => $line->field_name,
				'field_label' => $line->field_label,
				'field_type' => $line->field_type,
				'key_text' => $line->key_text,
				'delta_x' => (float) $line->delta_x,
				'delta_y' => (float) $line->delta_y,
				'theta' => (float) $line->theta,
				'distance' => (float) $line->distance,
				'relation_type' => $line->relation_type,
				'extraction_method' => $line->extraction_method,
				'required' => (bool) $line->required,
			);
		}

		return array(
			'id' => (int) $this->id,
			'label' => $this->label,
			'doc_type' => $this->doc_type,
			'fields' => $fields,
			'version' => (int) $this->version,
		);
	}

	/**
	 * Return a link to the object card
	 *
	 * @param int $withpicto Include picto in link
	 * @return string          String with URL
	 */
	public function getNomUrl($withpicto = 0)
	{
		$url = dol_buildpath('/ophelia/template_card.php', 1).'?id='.$this->id;
		$result = '<a href="'.$url.'">';
		if ($withpicto) {
			$result .= img_object($this->ref, $this->picto, 'class="paddingright"');
		}
		$result .= $this->ref;
		$result .= '</a>';

		return $result;
	}
}
