<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/document_list.php
 * \ingroup ophelia
 * \brief   List of Ophelia documents
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$search_ref = GETPOST('search_ref', 'alpha');
$search_label = GETPOST('search_label', 'alpha');
$search_doctype = GETPOST('search_doctype', 'alpha');
$search_status = GETPOST('search_status', 'int');

$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTINT('page');
if (empty($page) || $page < 0) {
	$page = 0;
}
if (empty($sortfield)) {
	$sortfield = 't.date_upload';
}
if (empty($sortorder)) {
	$sortorder = 'DESC';
}
$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$offset = $limit * $page;

if (GETPOST('button_removefilter', 'alpha')) {
	$search_ref = $search_label = $search_doctype = '';
	$search_status = '';
}

$form = new Form($db);
$object = new OpheliaDocument($db);

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
if ($search_status !== '' && $search_status !== null) {
	$filter[] = "(status:=:".((int) $search_status).")";
}
$filterstring = implode(' AND ', $filter);

$records = $object->fetchAll($sortorder, $sortfield, $limit, $offset, $filterstring);
if (!is_array($records)) {
	setEventMessages($db->lasterror(), null, 'errors');
	$records = array();
}

// Count total for pagination
$objectstatic = new OpheliaDocument($db);
$allrecords = $objectstatic->fetchAll('', '', 0, 0, $filterstring);
$nbtotalofrecords = is_array($allrecords) ? count($allrecords) : 0;

llxHeader('', $langs->trans("OpheliaDocuments"));

print '<div class="ophelia-app">';

$title = $langs->trans("OpheliaDocuments");
print load_fiche_titre($title, '', 'ophelia@ophelia');

$param = '';
if ($search_ref) {
	$param .= '&search_ref='.urlencode($search_ref);
}
if ($search_label) {
	$param .= '&search_label='.urlencode($search_label);
}
if ($search_doctype) {
	$param .= '&search_doctype='.urlencode($search_doctype);
}
if ($search_status !== '' && $search_status !== null) {
	$param .= '&search_status='.urlencode((string) $search_status);
}

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';

print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, '', count($records), $nbtotalofrecords, 'ophelia@ophelia', 0, dolGetButtonTitle($langs->trans('OpheliaNewDocument'), '', 'fa fa-plus-circle', dol_buildpath('/ophelia/document_card.php?action=create', 1)), '', $limit);

print '<div class="div-table-responsive">';
print '<table class="tagtable liste">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("Ref").'</td>';
print '<td>'.$langs->trans("Label").'</td>';
print '<td>'.$langs->trans("DocType").'</td>';
print '<td>'.$langs->trans("OpheliaMatchingScore").'</td>';
print '<td>'.$langs->trans("DateUpload").'</td>';
print '<td class="right">'.$langs->trans("Status").'</td>';
print '</tr>';

print '<tr class="liste_titre">';
print '<td><input type="text" class="flat maxwidth100" name="search_ref" value="'.dol_escape_htmltag($search_ref).'"></td>';
print '<td><input type="text" class="flat maxwidth150" name="search_label" value="'.dol_escape_htmltag($search_label).'"></td>';
print '<td><input type="text" class="flat maxwidth100" name="search_doctype" value="'.dol_escape_htmltag($search_doctype).'"></td>';
print '<td></td>';
print '<td></td>';
print '<td class="right">';
print '<input type="submit" class="button small" value="'.$langs->trans("Search").'">';
print '<input type="submit" class="button small button-cancel" name="button_removefilter" value="'.$langs->trans("RemoveFilter").'">';
print '</td>';
print '</tr>';

if (empty($records)) {
	print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	foreach ($records as $rec) {
		print '<tr class="oddeven">';
		print '<td>'.$rec->getNomUrl(1).'</td>';
		print '<td>'.dol_escape_htmltag($rec->label).'</td>';
		print '<td>'.dol_escape_htmltag($rec->doc_type).'</td>';
		print '<td>'.($rec->matching_score !== null ? round($rec->matching_score * 100).'%' : '').'</td>';
		print '<td>'.dol_print_date($rec->date_upload, 'dayhour').'</td>';
		print '<td class="right">'.$rec->getLibStatut(5).'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print '</form>';

print '</div>';

llxFooter();
$db->close();
