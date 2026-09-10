<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/extraction_validate.php
 * \ingroup ophelia
 * \brief   Validation workflow: review and correct extracted field values
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionresult.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionfield.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$documentId = GETPOSTINT('document_id');
$action = GETPOST('action', 'aZ09');

$object = new OpheliaExtractionResult($db);

if ($id > 0) {
	$res = $object->fetch($id);
} elseif ($documentId > 0) {
	$latest = $object->fetchAll('DESC', 't.rowid', 1, 0, "(fk_document:=:".((int) $documentId).")");
	if (is_array($latest) && count($latest) > 0) {
		$first = reset($latest);
		$res = $object->fetch($first->id);
	} else {
		$res = 0;
	}
} else {
	$res = 0;
}

if (empty($res) || $res <= 0) {
	llxHeader('', $langs->trans("OpheliaExtractionResults"));
	print '<div class="ophelia-app">';
	print load_fiche_titre($langs->trans("OpheliaExtractionResults"), '', 'ophelia@ophelia');
	print '<div class="opacitymedium">'.$langs->trans("OpheliaNoExtractionYet").'</div>';
	print '</div>';
	llxFooter();
	$db->close();
	exit;
}

$documentObj = new OpheliaDocument($db);
$documentObj->fetch($object->fk_document);

if ($action == 'validate' && $user->hasRight('ophelia', 'extraction', 'validate')) {
	$corrections = GETPOST('corrected', 'array');

	$db->begin();
	$error = 0;

	foreach ($object->lines as $line) {
		if (isset($corrections[$line->id])) {
			$newValue = trim($corrections[$line->id]);
			$line->corrected_value = ($newValue !== '' && $newValue !== $line->extracted_value) ? $newValue : null;
			$line->is_validated = 1;
			$r = $line->update($user, 1);
			if ($r <= 0) {
				$error++;
			}
		} else {
			$line->is_validated = 1;
			$line->update($user, 1);
		}
	}

	if (!$error) {
		$r = $object->validateResult($user);
		if ($r > 0) {
			$documentObj->status = OpheliaDocument::STATUS_VALIDATED;
			$documentObj->date_validation = dol_now();
			$documentObj->update($user, 1);
			$documentObj->call_trigger('OPHELIA_DOCUMENT_VALIDATE', $user);

			$db->commit();
			setEventMessages($langs->trans("OpheliaValidationDone"), null);
			header('Location: '.dol_buildpath('/ophelia/export.php', 1).'?id='.$object->id);
			exit;
		} else {
			$error++;
		}
	}

	if ($error) {
		$db->rollback();
		setEventMessages(implode(', ', $object->errors), null, 'errors');
	}
}

// Re-fetch after potential updates
$object->fetchFields();

// Sort by confidence ascending, so low-confidence fields need attention first
usort($object->lines, function ($a, $b) {
	return $a->confidence_total <=> $b->confidence_total;
});

$form = new Form($db);

llxHeader('', $langs->trans("OpheliaExtractionResults"));

print '<div class="ophelia-app">';

print load_fiche_titre($langs->trans("OpheliaExtractionResults"), '', 'ophelia@ophelia');

print dol_get_fiche_head(array(), '', '', -1, '');

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("OpheliaDocument").'</td><td>'.$documentObj->getNomUrl(1).'</td></tr>';
print '<tr><td>'.$langs->trans("OpheliaStrategyUsed").'</td><td>'.dol_escape_htmltag($object->strategy).'</td></tr>';
print '<tr><td>'.$langs->trans("OpheliaGlobalConfidence").'</td><td>'.opheliaConfidenceBadge($object->global_confidence).'</td></tr>';
print '<tr><td>'.$langs->trans("Status").'</td><td>';
$statusLabels = array(0 => $langs->trans("OpheliaResultRaw"), 1 => $langs->trans("OpheliaResultValidated"), 2 => $langs->trans("OpheliaResultExported"));
print $statusLabels[$object->status] ?? $object->status;
print '</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

$canValidate = ($object->status == OpheliaExtractionResult::STATUS_RAW) && $user->hasRight('ophelia', 'extraction', 'validate');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="validate">';
print '<input type="hidden" name="id" value="'.$object->id.'">';

print '<div class="div-table-responsive">';
print '<table class="tagtable liste centpercent">';
print '<tr class="liste_titre">';
print '<td>'.$langs->trans("OpheliaFieldLabel").'</td>';
print '<td>'.$langs->trans("OpheliaExtractedValue").'</td>';
print '<td>'.$langs->trans("OpheliaCorrectedValue").'</td>';
print '<td class="center">'.$langs->trans("OpheliaConfidenceOcr").'</td>';
print '<td class="center">'.$langs->trans("OpheliaConfidenceSpatial").'</td>';
print '<td class="center">'.$langs->trans("OpheliaConfidenceTotal").'</td>';
print '</tr>';

if (empty($object->lines)) {
	print '<tr><td colspan="6" class="opacitymedium">'.$langs->trans("NoRecordFound").'</td></tr>';
} else {
	foreach ($object->lines as $line) {
		$rowclass = 'oddeven';
		if ($line->confidence_total < 0.6) {
			$rowclass .= ' ophelia-field-row ophelia-field-low';
		} elseif ($line->confidence_total < 0.85) {
			$rowclass .= ' ophelia-field-row ophelia-field-medium';
		}

		print '<tr class="'.$rowclass.'">';
		print '<td>'.dol_escape_htmltag($line->field_name).'</td>';
		print '<td>'.dol_escape_htmltag((string) $line->extracted_value).'</td>';
		print '<td>';
		if ($canValidate) {
			print '<input type="text" name="corrected['.$line->id.']" class="minwidth200" value="'.dol_escape_htmltag($line->corrected_value !== null ? $line->corrected_value : $line->extracted_value).'">';
		} else {
			print dol_escape_htmltag((string) $line->getEffectiveValue());
		}
		print '</td>';
		print '<td class="center">'.opheliaConfidenceBadge($line->confidence_ocr).'</td>';
		print '<td class="center">'.opheliaConfidenceBadge($line->confidence_spatial).'</td>';
		print '<td class="center">'.opheliaConfidenceBadge($line->confidence_total).'</td>';
		print '</tr>';
	}
}

print '</table>';
print '</div>';

if ($canValidate) {
	print '<div class="center marginTopOnly"><input type="submit" class="button" value="'.$langs->trans("OpheliaValidateExtraction").'"></div>';
}

print '</form>';

if ($object->status >= OpheliaExtractionResult::STATUS_VALIDATED) {
	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.dol_buildpath('/ophelia/export.php', 1).'?id='.$object->id.'">'.$langs->trans("OpheliaGoToExport").'</a>';
	print '</div>';
}

print '</div>';

llxFooter();
$db->close();
