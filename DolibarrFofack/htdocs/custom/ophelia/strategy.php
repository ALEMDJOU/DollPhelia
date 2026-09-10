<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/strategy.php
 * \ingroup ophelia
 * \brief   Manage Ophelia extraction strategies
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/strategy.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');
$id = GETPOSTINT('id');

if ($action == 'add' && $user->hasRight('ophelia', 'document', 'write')) {
	$object = new OpheliaStrategy($db);
	$object->code = GETPOST('code', 'alpha');
	$object->label = GETPOST('label', 'alphanohtml');
	$object->description = GETPOST('description', 'restricthtml');
	$object->priority = GETPOSTINT('priority');
	$object->active = 1;

	if (empty($object->code) || empty($object->label)) {
		setEventMessages($langs->trans("ErrorFieldsRequired"), null, 'errors');
	} else {
		$newid = $object->create($user, 1);
		if ($newid <= 0) {
			setEventMessages(implode(', ', $object->errors), null, 'errors');
		} else {
			setEventMessages($langs->trans("RecordSaved"), null);
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'toggle' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$object = new OpheliaStrategy($db);
	if ($object->fetch($id) > 0) {
		$object->active = $object->active ? 0 : 1;
		$object->update($user, 1);
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

if ($action == 'delete' && $id > 0 && $user->hasRight('ophelia', 'document', 'delete')) {
	$object = new OpheliaStrategy($db);
	if ($object->fetch($id) > 0) {
		$object->delete($user, 1);
	}
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

$listObj = new OpheliaStrategy($db);
$records = $listObj->fetchAll('ASC', 't.priority', 0, 0, '');
if (!is_array($records)) {
	$records = array();
}

$form = new Form($db);

llxHeader('', $langs->trans("OpheliaStrategies"));

print '<div class="ophelia-app">';

print load_fiche_titre($langs->trans("OpheliaStrategies"), '', 'ophelia@ophelia');

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("OpheliaStrategyCode").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("Description").'</td>';
print '<td class="center">'.$langs->trans("OpheliaPriority").'</td>';
print '<td class="right">'.$langs->trans("Status").'</td>';
print '<td></td>';
print '</tr>';

if (empty($records)) {
	print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	foreach ($records as $rec) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($rec->code).'</td>';
		print '<td>'.dol_escape_htmltag($rec->label).'</td>';
		print '<td>'.dol_escape_htmltag($rec->description).'</td>';
		print '<td class="center">'.((int) $rec->priority).'</td>';
		print '<td class="right">';
		if ($user->hasRight('ophelia', 'document', 'write')) {
			print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$rec->id.'&action=toggle&token='.newToken().'">';
		}
		print ($rec->active ? img_picto($langs->trans("Enabled"), 'tick') : $langs->trans("Disabled"));
		if ($user->hasRight('ophelia', 'document', 'write')) {
			print '</a>';
		}
		print '</td>';
		print '<td class="right">';
		if ($user->hasRight('ophelia', 'document', 'delete')) {
			print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$rec->id.'&action=delete&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

if ($user->hasRight('ophelia', 'document', 'write')) {
	print load_fiche_titre($langs->trans("OpheliaNewStrategy"), '', '');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print '<table class="border centpercent">';
	print '<tr>';
	print '<td class="titlefieldcreate fieldrequired">'.$langs->trans("OpheliaStrategyCode").'</td>';
	print '<td><input type="text" name="code" class="minwidth150" required></td>';
	print '<td class="fieldrequired">'.$langs->trans("Label").'</td>';
	print '<td><input type="text" name="label" class="minwidth200" required></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("Description").'</td>';
	print '<td colspan="3"><input type="text" name="description" class="minwidth300"></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("OpheliaPriority").'</td>';
	print '<td><input type="number" name="priority" class="width75" value="0"></td>';
	print '<td colspan="2"></td>';
	print '</tr>';
	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Add").'"></div>';

	print '</form>';
}

print '</div>';

llxFooter();
$db->close();
