<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \defgroup   ophelia     Module Ophelia
 *  \brief      Ophelia module descriptor.
 *
 *  \file       htdocs/custom/ophelia/core/modules/modOphelia.class.php
 *  \ingroup    ophelia
 *  \brief      Description and activation file for module Ophelia
 */
include_once DOL_DOCUMENT_ROOT.'/core/modules/DolibarrModules.class.php';

/**
 *  Description and activation class for module Ophelia
 */
class modOphelia extends DolibarrModules
{
	/**
	 * Constructor. Define names, constants, directories, boxes, permissions
	 *
	 * @param DoliDB $db Database handler
	 */
	public function __construct($db)
	{
		global $langs, $conf;

		$this->db = $db;

		// Id for module (must be unique, reserved in the project for Ophelia)
		$this->numero = 500011;

		// Key text used to identify module (for permissions, menus, etc...)
		$this->rights_class = 'ophelia';

		$this->family = "technic";
		$this->module_position = '90';

		$this->name = preg_replace('/^mod/i', '', get_class($this));

		$this->description = "Extraction automatique de donnees documentaires";
		$this->descriptionlong = "Module d'extraction automatique de donnees documentaires (OCR, appariement de templates, extraction spatiale et IA) pour Dolibarr, appuye sur le microservice Ophelia.";

		$this->editor_name = 'Ophelia';
		$this->editor_url = '';

		$this->version = '1.0';

		$this->const_name = 'MAIN_MODULE_'.strtoupper($this->name);

		$this->picto = 'ophelia@ophelia';

		$this->module_parts = array(
			'triggers' => 1,
			'login' => 0,
			'substitutions' => 0,
			'menus' => 0,
			'tpl' => 0,
			'barcode' => 0,
			'models' => 0,
			'printing' => 0,
			'theme' => 0,
			'css' => array(
				'/ophelia/css/ophelia.css',
			),
			'js' => array(
				'/ophelia/js/ophelia.js',
			),
			'hooks' => array(),
			'moduleforexternal' => 0,
		);

		// Data directories to create when module is enabled.
		$this->dirs = array("/ophelia/temp");

		// Config pages.
		$this->config_page_url = array("setup.php@ophelia");

		$this->hidden = false;
		$this->depends = array();
		$this->requiredby = array();
		$this->conflictwith = array();

		$this->langfiles = array("ophelia@ophelia");

		$this->phpmin = array(7, 4);
		$this->need_dolibarr_version = array(19, 0);
		$this->need_javascript_ajax = 1;

		$this->warnings_activation = array();
		$this->warnings_activation_ext = array();

		// Constants added when module is enabled
		$this->const = array(
			1 => array('OPHELIA_API_URL', 'chaine', 'http://localhost:8000', 'URL de base du microservice Ophelia', 0),
			2 => array('OPHELIA_MATCHING_THRESHOLD', 'chaine', '0.7', 'Seuil minimal de correspondance de template', 0),
			3 => array('OPHELIA_OCR_LANG', 'chaine', 'eng', 'Langue Tesseract par defaut (documents traites en priorite en anglais)', 0),
		);

		if (!isModEnabled("ophelia")) {
			$conf->ophelia = new stdClass();
			$conf->ophelia->enabled = 0;
		}

		$this->tabs = array();

		$this->dictionaries = array();

		$this->boxes = array();

		$this->cronjobs = array();

		// Permissions provided by this module
		$this->rights = array();
		$r = 0;

		$this->rights[$r][0] = $this->numero.sprintf("%02d", 1);
		$this->rights[$r][1] = 'Consulter les documents et templates Ophelia';
		$this->rights[$r][4] = 'document';
		$this->rights[$r][5] = 'read';
		$r++;

		$this->rights[$r][0] = $this->numero.sprintf("%02d", 2);
		$this->rights[$r][1] = 'Creer/modifier les documents et templates Ophelia';
		$this->rights[$r][4] = 'document';
		$this->rights[$r][5] = 'write';
		$r++;

		$this->rights[$r][0] = $this->numero.sprintf("%02d", 3);
		$this->rights[$r][1] = 'Supprimer les documents et templates Ophelia';
		$this->rights[$r][4] = 'document';
		$this->rights[$r][5] = 'delete';
		$r++;

		$this->rights[$r][0] = $this->numero.sprintf("%02d", 4);
		$this->rights[$r][1] = 'Valider les extractions Ophelia';
		$this->rights[$r][4] = 'extraction';
		$this->rights[$r][5] = 'validate';
		$r++;

		// Main menu entries to add
		$this->menu = array();
		$r = 0;

		$this->menu[$r++] = array(
			'fk_menu' => '',
			'type' => 'top',
			'titre' => 'ModuleOpheliaName',
			'prefix' => img_picto('', 'ophelia@ophelia', 'class="pictofixedwidth valignmiddle"'),
			'mainmenu' => 'ophelia',
			'leftmenu' => '',
			'url' => '/ophelia/document_list.php',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '1',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ophelia',
			'type' => 'left',
			'titre' => 'OpheliaDocuments',
			'mainmenu' => 'ophelia',
			'leftmenu' => 'ophelia_document',
			'url' => '/ophelia/document_list.php',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '$user->hasRight("ophelia", "document", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ophelia,fk_leftmenu=ophelia_document',
			'type' => 'left',
			'titre' => 'OpheliaNewDocument',
			'mainmenu' => 'ophelia',
			'leftmenu' => 'ophelia_document_new',
			'url' => '/ophelia/document_card.php?action=create',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '$user->hasRight("ophelia", "document", "write")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ophelia',
			'type' => 'left',
			'titre' => 'OpheliaTemplates',
			'mainmenu' => 'ophelia',
			'leftmenu' => 'ophelia_template',
			'url' => '/ophelia/template_list.php',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '$user->hasRight("ophelia", "document", "read")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ophelia,fk_leftmenu=ophelia_template',
			'type' => 'left',
			'titre' => 'OpheliaNewTemplate',
			'mainmenu' => 'ophelia',
			'leftmenu' => 'ophelia_template_new',
			'url' => '/ophelia/template_card.php?action=create',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '$user->hasRight("ophelia", "document", "write")',
			'target' => '',
			'user' => 2,
		);

		$this->menu[$r++] = array(
			'fk_menu' => 'fk_mainmenu=ophelia',
			'type' => 'left',
			'titre' => 'OpheliaStrategies',
			'mainmenu' => 'ophelia',
			'leftmenu' => 'ophelia_strategy',
			'url' => '/ophelia/strategy.php',
			'langs' => 'ophelia@ophelia',
			'position' => 1000 + $r,
			'enabled' => 'isModEnabled("ophelia")',
			'perms' => '$user->hasRight("ophelia", "document", "read")',
			'target' => '',
			'user' => 2,
		);
	}

	/**
	 *  Function called when module is enabled.
	 *
	 *  @param      string  $options    Options when enabling module ('', 'noboxes')
	 *  @return     int                 1 if OK, <=0 if KO
	 */
	public function init($options = '')
	{
		$result = $this->_load_tables('/ophelia/sql/');
		if ($result < 0) {
			return -1;
		}

		$this->remove($options);

		$sql = array();

		return $this->_init($sql, $options);
	}

	/**
	 *  Function called when module is disabled.
	 *
	 *  @param  string      $options    Options when enabling module ('', 'noboxes')
	 *  @return int                     1 if OK, <=0 if KO
	 */
	public function remove($options = '')
	{
		$sql = array();
		return $this->_remove($sql, $options);
	}
}
