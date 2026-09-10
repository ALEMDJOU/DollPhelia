<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/template_list.php
 * \ingroup ophelia
 * \brief   List of Ophelia templates
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/template.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_doctype = GETPOST('search_doctype', 'alpha');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
if (empty($sortfield)) {
	$sortfield = 't.date_creation';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}

if (GETPOST('button_removefilter', 'alpha')) {
	$search_ref = $search_label = $search_doctype = '';
}

$object = new OpheliaTemplate($db);

$filter = array();
if ($search_ref !== '') {
	$filter[] = "(ref:like:'%".$db->escape($search_ref)."%')";
}
if ($search_label !== '') {
	$filter[] = "(label:like:'%".$db->escape($search_label)."%')";
}
if ($search_doctype !== '') {
	$filter[] = "(doc_type:like:'%".$db->escape($search_doctype)."%')";
}
$filterstring = implode(' AND ', $filter);

$records = $object->fetchAll($sortorder, $sortfield, 0, 0, $filterstring);
if (!is_array($records)) {
	setEventMessages($db->lasterror(), null, 'errors');
	$records = array();
}

llxHeader('', $langs->trans("OpheliaTemplates"));

print '<div class="ophelia-app">';

$title = $langs->trans("OpheliaTemplates");
print load_fiche_titre($title, '', 'ophelia@ophelia');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print_barre_liste($title, 0, $_SERVER["PHP_SELF"], '', $sortfield, $sortorder, '', count($records), count($records), 'ophelia@ophelia', 0, dolGetButtonTitle($langs->trans('OpheliaNewTemplate'), '', 'fa fa-plus-circle', dol_buildpath('/ophelia/template_card.php?action=create', 1)), '', 0);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Ref").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("DocType").'</td>';
print '<td class="center">'.$langs->trans("OpheliaVersion").'</td>';
print '<td class="center">'.$langs->trans("OpheliaTemplateFields").'</td>';
print '<td class="right">'.$langs->trans("Status").'</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<td><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td><input type="text" class="flat maxwidth150" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_doctype" value="'.dol_escape_htmltag($search_doctype).'"></td>';
print '<td></td><td></td>';
print '<td class="right">';
print '<input type="submit" class="button small" value="'.$langs->trans("Search").'">';
print '<input type="submit" class="button small button-cancel" name="button_removefilter" value="'.$langs->trans("RemoveFilter").'">';
print '</td>';
print '</tr>';

if (empty($records)) {
	print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	foreach ($records as $rec) {
		$rec->fetchFields();
		print '<tr class="oddeven">';
		print '<td>'.$rec->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($rec->label).'</td>';
		print '<td>'.dol_escape_htmltag($rec->doc_type).'</td>';
		print '<td class="center">'.((int) $rec->version).'</td>';
		print '<td class="center">'.count($rec->lines).'</td>';
		print '<td class="right">'.($rec->active ? img_picto($langs->trans("Enabled"), 'tick') : $langs->trans("Disabled")).'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print '</form>';

print '</div>';

llxFooter();
$db->close();
