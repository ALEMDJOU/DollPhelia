<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    core/triggers/interface_99_modOphelia_OpheliaTriggers.class.php
 * \ingroup ophelia
 * \brief   Trigger file for module Ophelia
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';

/**
 *  Class of triggers for Ophelia module
 */
class InterfaceOpheliaTriggers extends DolibarrTriggers
{
	/**
	 * Constructor
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		parent::__construct($db);
		$this->family = "technic";
		$this->description = "Ophelia triggers.";
		$this->version = self::VERSIONS['dev'];
		$this->picto = 'ophelia@ophelia';
	}

	/**
	 * Function called when a Dolibarr business event is done.
	 *
	 * @param string        $action Event action code
	 * @param CommonObject  $object Object
	 * @param User          $user   Object user
	 * @param Translate     $langs  Object langs
	 * @param Conf          $conf   Object conf
	 * @return int                  Return integer <0 if KO, 0 if no triggered ran, >0 if OK
	 */
	public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
	{
		if (!isModEnabled('ophelia')) {
			return 0;
		}

		switch ($action) {
			case 'OPHELIA_DOCUMENT_VALIDATE':
				dol_syslog("Trigger '".$this->name."' : document Ophelia valide, id=".$object->id, LOG_DEBUG);
				break;

			case 'OPHELIA_EXTRACTION_VALIDATE':
				dol_syslog("Trigger '".$this->name."' : extraction Ophelia validee, id=".$object->id, LOG_DEBUG);
				break;

			default:
				break;
		}

		return 0;
	}
}
