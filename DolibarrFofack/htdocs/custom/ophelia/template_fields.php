<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/template_fields.php
 * \ingroup ophelia
 * \brief   Manage spatial anchor fields of an Ophelia template
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/template.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/templatefield.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$fieldid = GETPOSTINT('fieldid');
$action = GETPOST('action', 'aZ09');

$object = new OpheliaTemplate($db);
$res = $object->fetch($id);
if ($res <= 0) {
	accessforbidden('OpheliaTemplateNotFound');
}

if ($action == 'add_field' && $user->hasRight('ophelia', 'document', 'write')) {
	$field = new OpheliaTemplateField($db);
	$field->fk_template = $object->id;
	$field->field_name = GETPOST('field_name', 'alpha');
	$field->field_label = GETPOST('field_label', 'alphanohtml');
	$field->field_type = GETPOST('field_type', 'alpha');
	$field->key_text = GETPOST('key_text', 'alphanohtml');
	$field->delta_x = (float) GETPOST('delta_x', 'alpha');
	$field->delta_y = (float) GETPOST('delta_y', 'alpha');
	$field->theta = (float) GETPOST('theta', 'alpha');
	$field->distance = (float) GETPOST('distance', 'alpha');
	$field->relation_type = GETPOST('relation_type', 'alpha');
	$field->extraction_method = GETPOST('extraction_method', 'alpha');
	$field->required = GETPOST('required', 'alpha') ? 1 : 0;
	$field->rang = count($object->lines) + 1;

	if (empty($field->field_name) || empty($field->field_label) || empty($field->key_text)) {
		setEventMessages($langs->trans("ErrorFieldsRequired"), null, 'errors');
	} else {
		$newid = $field->create($user, 1);
		if ($newid <= 0) {
			setEventMessages(implode(', ', $field->errors), null, 'errors');
		}
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

if ($action == 'delete_field' && $fieldid > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$field = new OpheliaTemplateField($db);
	if ($field->fetch($fieldid) > 0 && $field->fk_template == $object->id) {
		$field->delete($user, 1);
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

$object->fetchFields();

$form = new Form($db);

llxHeader('', $langs->trans("OpheliaTemplateFields"));

print '<div class="ophelia-app">';

$head = opheliaTemplatePrepareHead($object);
print dol_get_fiche_head($head, 'fields', $langs->trans("OpheliaTemplate"), -1, 'ophelia@ophelia');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).' - '.dol_escape_htmltag($object->label).'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

print load_fiche_titre($langs->trans("OpheliaTemplateFields"), '', '');

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("OpheliaFieldName").'</td>';
print '<td>'.$langs->trans("OpheliaFieldLabel").'</td>';
print '<td>'.$langs->trans("OpheliaFieldType").'</td>';
print '<td>'.$langs->trans("OpheliaKeyText").'</td>';
print '<td class="center">'.$langs->trans("OpheliaDeltaX").'</td>';
print '<td class="center">'.$langs->trans("OpheliaDeltaY").'</td>';
print '<td>'.$langs->trans("OpheliaRelationType").'</td>';
print '<td>'.$langs->trans("OpheliaExtractionMethod").'</td>';
print '<td class="center">'.$langs->trans("OpheliaRequired").'</td>';
print '<td></td>';
print '</tr>';

if (empty($object->lines)) {
	print '<tr><td colspan="10" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	foreach ($object->lines as $line) {
		print '<tr class="oddeven">';
		print '<td>'.dol_escape_htmltag($line->field_name).'</td>';
		print '<td>'.dol_escape_htmltag($line->field_label).'</td>';
		print '<td>'.dol_escape_htmltag($line->field_type).'</td>';
		print '<td>'.dol_escape_htmltag($line->key_text).'</td>';
		print '<td class="center">'.$line->delta_x.'</td>';
		print '<td class="center">'.$line->delta_y.'</td>';
		print '<td>'.dol_escape_htmltag($line->relation_type).'</td>';
		print '<td>'.dol_escape_htmltag($line->extraction_method).'</td>';
		print '<td class="center">'.($line->required ? img_picto('', 'tick') : '').'</td>';
		print '<td class="right">';
		if ($user->hasRight('ophelia', 'document', 'write')) {
			print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&fieldid='.$line->id.'&action=delete_field&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">'.img_picto($langs->trans("Delete"), 'delete').'</a>';
		}
		print '</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

if ($user->hasRight('ophelia', 'document', 'write')) {
	print load_fiche_titre($langs->trans("OpheliaAddField"), '', '');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'?id='.$id.'">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add_field">';

	print '<table class="border centpercent">';
	print '<tr>';
	print '<td>'.$langs->trans("OpheliaFieldName").'</td><td><input type="text" name="field_name" class="minwidth100" required></td>';
	print '<td>'.$langs->trans("OpheliaFieldLabel").'</td><td><input type="text" name="field_label" class="minwidth100" required></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("OpheliaFieldType").'</td>';
	print '<td><select name="field_type" class="minwidth100">';
	foreach (array('text', 'date', 'amount', 'number', 'email', 'phone', 'iban') as $ft) {
		print '<option value="'.$ft.'">'.$ft.'</option>';
	}
	print '</select></td>';
	print '<td>'.$langs->trans("OpheliaKeyText").'</td><td><input type="text" name="key_text" class="minwidth150" required placeholder="Total TTC"></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("OpheliaDeltaX").'</td><td><input type="number" step="0.1" name="delta_x" class="width75" value="0"></td>';
	print '<td>'.$langs->trans("OpheliaDeltaY").'</td><td><input type="number" step="0.1" name="delta_y" class="width75" value="0"></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("OpheliaRelationType").'</td>';
	print '<td><select name="relation_type" class="minwidth100">';
	foreach (array('right_of', 'below', 'left_of', 'above') as $rt) {
		print '<option value="'.$rt.'">'.$rt.'</option>';
	}
	print '</select></td>';
	print '<td>'.$langs->trans("OpheliaExtractionMethod").'</td>';
	print '<td><select name="extraction_method" class="minwidth100">';
	foreach (array('spatial', 'ai_layoutlm') as $em) {
		print '<option value="'.$em.'">'.$em.'</option>';
	}
	print '</select></td>';
	print '</tr><tr>';
	print '<td>'.$langs->trans("OpheliaRequired").'</td><td><input type="checkbox" name="required" value="1" checked></td>';
	print '<td colspan="2"></td>';
	print '</tr>';
	print '</table>';

	print '<div class="center"><input type="submit" class="button" value="'.$langs->trans("Add").'"></div>';

	print '</form>';
}

print '</div>';

llxFooter();
$db->close();
