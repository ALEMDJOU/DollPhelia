<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/template_card.php
 * \ingroup ophelia
 * \brief   Card (view / create / edit) of an Ophelia template
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/template.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new OpheliaTemplate($db);
if ($id > 0) {
	$res = $object->fetch($id);
	if ($res <= 0) {
		accessforbidden('OpheliaTemplateNotFound');
	}
}

if ($action == 'add' && $user->hasRight('ophelia', 'document', 'write')) {
	$object->ref = GETPOST('ref', 'alphanohtml');
	$object->label = GETPOST('label', 'alphanohtml');
	$object->description = GETPOST('description', 'restricthtml');
	$object->doc_type = GETPOST('doc_type', 'alpha');
	$object->version = 1;
	$object->active = 1;
	$object->fk_user_author = $user->id;
	$object->date_creation = dol_now();

	if (empty($object->ref) || empty($object->label) || empty($object->doc_type)) {
		setEventMessages($langs->trans("ErrorFieldsRequired"), null, 'errors');
		$action = 'create';
	} else {
		$newid = $object->create($user);
		if ($newid > 0) {
			setEventMessages($langs->trans("RecordSaved"), null);
			header('Location: '.dol_buildpath('/ophelia/template_fields.php', 1).'?id='.$newid);
			exit;
		} else {
			setEventMessages(implode(', ', $object->errors), null, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'update' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$object->label = GETPOST('label', 'alphanohtml');
	$object->description = GETPOST('description', 'restricthtml');
	$object->doc_type = GETPOST('doc_type', 'alpha');

	$res = $object->update($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordSaved"), null);
	} else {
		setEventMessages(implode(', ', $object->errors), null, 'errors');
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

if ($action == 'toggle_active' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$object->active = $object->active ? 0 : 1;
	$object->update($user);
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

if ($action == 'confirm_delete' && $id > 0 && $user->hasRight('ophelia', 'document', 'delete')) {
	$res = $object->delete($user);
	if ($res > 0) {
		setEventMessages($langs->trans("RecordDeleted"), null);
		header('Location: '.dol_buildpath('/ophelia/template_list.php', 1));
		exit;
	} else {
		setEventMessages(implode(', ', $object->errors), null, 'errors');
	}
}

$form = new Form($db);

llxHeader('', $langs->trans("OpheliaTemplates"));

print '<div class="ophelia-app">';

if ($action == 'create') {
	print load_fiche_titre($langs->trans("OpheliaNewTemplate"), '', 'ophelia@ophelia');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();

	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("Ref").'</td>';
	print '<td><input type="text" name="ref" class="minwidth200" required></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td>';
	print '<td><input type="text" name="label" class="minwidth300" required></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("DocType").'</td>';
	print '<td><input type="text" name="doc_type" class="minwidth200" placeholder="facture, cv, kbis, rib..." required></td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td>';
	print '<td><textarea name="description" class="minwidth300" rows="3"></textarea></td></tr>';
	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
	print ' <a class="button button-cancel" href="'.dol_buildpath('/ophelia/template_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';
} elseif ($action == 'edit' && $id > 0) {
	print load_fiche_titre($langs->trans("OpheliaEditTemplate"), '', 'ophelia@ophelia');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="update">';
	print '<input type="hidden" name="id" value="'.$object->id.'">';

	print dol_get_fiche_head();

	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("Label").'</td>';
	print '<td><input type="text" name="label" class="minwidth300" value="'.dol_escape_htmltag($object->label).'" required></td></tr>';
	print '<tr><td class="fieldrequired">'.$langs->trans("DocType").'</td>';
	print '<td><input type="text" name="doc_type" class="minwidth200" value="'.dol_escape_htmltag($object->doc_type).'" required></td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td>';
	print '<td><textarea name="description" class="minwidth300" rows="3">'.dol_escape_htmltag($object->description).'</textarea></td></tr>';
	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
	print ' <a class="button button-cancel" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';
} elseif ($id > 0) {
	$head = opheliaTemplatePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("OpheliaTemplate"), -1, 'ophelia@ophelia');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border tableforfield centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans("Label").'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans("DocType").'</td><td>'.dol_escape_htmltag($object->doc_type).'</td></tr>';
	print '<tr><td>'.$langs->trans("Description").'</td><td>'.dol_htmlentitiesbr($object->description).'</td></tr>';
	print '<tr><td>'.$langs->trans("OpheliaVersion").'</td><td>'.((int) $object->version).'</td></tr>';
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.($object->active ? img_picto($langs->trans("Enabled"), 'tick').' '.$langs->trans("Enabled") : $langs->trans("Disabled")).'</td></tr>';
	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($user->hasRight('ophelia', 'document', 'write')) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit">'.$langs->trans("Modify").'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/ophelia/template_fields.php', 1).'?id='.$object->id.'">'.$langs->trans("OpheliaManageFields").'</a>';
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=toggle_active&token='.newToken().'">'.($object->active ? $langs->trans("Disable") : $langs->trans("Enable")).'</a>';
	}
	if ($user->hasRight('ophelia', 'document', 'delete')) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_delete&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">'.$langs->trans("Delete").'</a>';
	}
	print '</div>';
} else {
	header('Location: '.dol_buildpath('/ophelia/template_list.php', 1));
	exit;
}

print '</div>';

llxFooter();
$db->close();
