<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/extraction_result.php
 * \ingroup ophelia
 * \brief   List of extraction results (read-only overview, links to the validation workflow)
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionresult.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');

if ($id > 0) {
	header('Location: '.dol_buildpath('/ophelia/extraction_validate.php', 1).'?id='.$id);
	exit;
}

$object = new OpheliaExtractionResult($db);
$records = $object->fetchAll('DESC', 't.rowid', 0, 0, '');
if (!is_array($records)) {
	setEventMessages($db->lasterror(), null, 'errors');
	$records = array();
}

$form = new Form($db);

llxHeader('', $langs->trans("OpheliaExtractionResults"));

print '<div class="ophelia-app">';

print load_fiche_titre($langs->trans("OpheliaExtractionResults"), '', 'ophelia@ophelia');

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("OpheliaDocument").'</td>';
print '<td>'.$langs->trans("OpheliaStrategyUsed").'</td>';
print '<td class="center">'.$langs->trans("OpheliaGlobalConfidence").'</td>';
print '<td>'.$langs->trans("DateExtraction").'</td>';
print '<td class="right">'.$langs->trans("Status").'</td>';
print '</tr>';

if (empty($records)) {
	print '<tr><td colspan="5" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	$statusLabels = array(0 => $langs->trans("OpheliaResultRaw"), 1 => $langs->trans("OpheliaResultValidated"), 2 => $langs->trans("OpheliaResultExported"));
	$docObj = new OpheliaDocument($db);

	foreach ($records as $rec) {
		print '<tr class="oddeven">';
		print '<td>';
		if ($docObj->fetch($rec->fk_document) > 0) {
			print $docObj->getNomUrl(1);
		} else {
			print '#'.$rec->fk_document;
		}
		print '</td>';
		print '<td>'.dol_escape_htmltag($rec->strategy).'</td>';
		print '<td class="center">'.opheliaConfidenceBadge($rec->global_confidence).'</td>';
		print '<td>'.dol_print_date($rec->date_extraction, 'dayhour').'</td>';
		print '<td class="right"><a href="'.dol_buildpath('/ophelia/extraction_validate.php', 1).'?id='.$rec->id.'">'.($statusLabels[$rec->status] ?? $rec->status).'</a></td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

print '</div>';

llxFooter();
$db->close();
