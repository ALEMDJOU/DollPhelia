<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/export.php
 * \ingroup ophelia
 * \brief   Export a validated extraction result to JSON or XML (generated locally in PHP)
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionresult.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/exporthistory.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$format = GETPOST('format', 'aZ09');
$preview = GETPOST('preview', 'aZ09'); // 'json' or 'xml' : render inline, no download / no history entry

$object = new OpheliaExtractionResult($db);
$res = $object->fetch($id);
if ($res <= 0) {
	accessforbidden('OpheliaExtractionResultNotFound');
}

if ($object->status < OpheliaExtractionResult::STATUS_VALIDATED) {
	setEventMessages($langs->trans("OpheliaErrorNotValidated"), null, 'errors');
	header('Location: '.dol_buildpath('/ophelia/extraction_validate.php', 1).'?id='.$id);
	exit;
}

$documentObj = new OpheliaDocument($db);
$documentObj->fetch($object->fk_document);

/**
 * Build the plain PHP array representation of the validated result
 *
 * @return array Export payload
 */
function opheliaBuildExportArray($object, $documentObj)
{
	$fields = array();
	foreach ($object->lines as $line) {
		$fields[] = array(
			'field_name' => $line->field_name,
			'value' => $line->getEffectiveValue(),
			'confidence_total' => (float) $line->confidence_total,
			'was_corrected' => ($line->corrected_value !== null && $line->corrected_value !== ''),
		);
	}

	return array(
		'document_ref' => $documentObj->ref,
		'document_label' => $documentObj->label,
		'doc_type' => $documentObj->doc_type,
		'strategy' => $object->strategy,
		'global_confidence' => (float) $object->global_confidence,
		'date_validation' => dol_print_date($object->date_validation, 'dayhourrfc'),
		'fields' => $fields,
	);
}

/**
 * Build a simple XML document (lxml-compatible shape) from the export array
 *
 * @param array $data Export array
 * @return string       XML string
 */
function opheliaBuildExportXml(array $data)
{
	$xml = new DOMDocument('1.0', 'UTF-8');
	$xml->formatOutput = true;

	$root = $xml->createElement('extraction_result');
	$xml->appendChild($root);

	foreach ($data as $key => $value) {
		if ($key === 'fields') {
			continue;
		}
		$el = $xml->createElement($key, dol_escape_htmltag((string) $value));
		$root->appendChild($el);
	}

	$fieldsEl = $xml->createElement('fields');
	$root->appendChild($fieldsEl);

	foreach ($data['fields'] as $f) {
		$fieldEl = $xml->createElement('field');
		$fieldEl->setAttribute('name', $f['field_name']);
		$fieldEl->setAttribute('confidence_total', (string) $f['confidence_total']);
		$fieldEl->setAttribute('was_corrected', $f['was_corrected'] ? '1' : '0');
		$fieldEl->appendChild($xml->createTextNode((string) $f['value']));
		$fieldsEl->appendChild($fieldEl);
	}

	return $xml->saveXML();
}

if ($format === 'json' || $format === 'xml') {
	$data = opheliaBuildExportArray($object, $documentObj);

	$exportDir = $conf->ophelia->dir_output.'/exports';
	dol_mkdir($exportDir);

	$basename = dol_sanitizeFileName($documentObj->ref).'_'.$object->id;

	if ($format === 'json') {
		$content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
		$filename = $basename.'.json';
		$mime = 'application/json';
	} else {
		$content = opheliaBuildExportXml($data);
		$filename = $basename.'.xml';
		$mime = 'application/xml';
	}

	$filepath = $exportDir.'/'.$filename;
	file_put_contents($filepath, $content);

	$history = new OpheliaExportHistory($db);
	$history->fk_result = $object->id;
	$history->fk_user = $user->id;
	$history->export_format = $format;
	$history->filepath = $filepath;
	$history->date_export = dol_now();
	$history->create($user, 1);

	if ($object->status < OpheliaExtractionResult::STATUS_EXPORTED) {
		$sql = "UPDATE ".$db->prefix()."ophelia_extraction_result SET status = ".OpheliaExtractionResult::STATUS_EXPORTED." WHERE rowid = ".((int) $object->id);
		$db->query($sql);
	}

	top_httphead($mime);
	header('Content-Disposition: attachment; filename="'.$filename.'"');
	header('Content-Length: '.strlen($content));
	echo $content;
	exit;
}

// Preview: build the same payload as a real export, but only render it, never write a file or log history
$previewContent = null;
if ($preview === 'json' || $preview === 'xml') {
	$data = opheliaBuildExportArray($object, $documentObj);
	$previewContent = ($preview === 'json')
		? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		: opheliaBuildExportXml($data);
} else {
	$preview = 'json';
	$data = opheliaBuildExportArray($object, $documentObj);
	$previewContent = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Landing page: choose export format
$form = new Form($db);

llxHeader('', $langs->trans("OpheliaExport"));

print '<div class="ophelia-app">';

print load_fiche_titre($langs->trans("OpheliaExport"), '', 'ophelia@ophelia');

print dol_get_fiche_head();

print '<div class="fichecenter">';
print '<table class="border tableforfield centpercent">';
print '<tr><td class="titlefield">'.$langs->trans("OpheliaDocument").'</td><td>'.$documentObj->getNomUrl(1).'</td></tr>';
print '<tr><td>'.$langs->trans("OpheliaGlobalConfidence").'</td><td>'.opheliaConfidenceBadge($object->global_confidence).'</td></tr>';
print '<tr><td>'.$langs->trans("DateValidation").'</td><td>'.dol_print_date($object->date_validation, 'dayhour').'</td></tr>';
print '</table>';
print '</div>';

print dol_get_fiche_end();

print load_fiche_titre($langs->trans("OpheliaPreview"), '', '');

print '<div class="ophelia-preview-tabs">';
print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&preview=json"'.($preview == 'json' ? ' class="active"' : '').'>JSON</a>';
print '<a href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&preview=xml"'.($preview == 'xml' ? ' class="active"' : '').'>XML</a>';
print '</div>';
print '<pre class="ophelia-preview-box">'.htmlspecialchars($previewContent, ENT_QUOTES, 'UTF-8').'</pre>';

print '<div class="center marginTopOnly">';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&format=json">'.$langs->trans("OpheliaExportJson").'</a>';
print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$id.'&format=xml">'.$langs->trans("OpheliaExportXml").'</a>';
print '</div>';

// Export history
$history = new OpheliaExportHistory($db);
$exports = $history->fetchAll('DESC', 't.rowid', 0, 0, "(fk_result:=:".((int) $id).")");
if (is_array($exports) && count($exports) > 0) {
	print load_fiche_titre($langs->trans("OpheliaExportHistory"), '', '');
	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste centpercent">';
	print '<tr class="liste_titre"><td>'.$langs->trans("OpheliaExportFormat").'</td><td>'.$langs->trans("Date").'</td><td>'.$langs->trans("User").'</td></tr>';
	foreach ($exports as $exp) {
		print '<tr class="oddeven">';
		print '<td>'.strtoupper($exp->export_format).'</td>';
		print '<td>'.dol_print_date($exp->date_export, 'dayhour').'</td>';
		print '<td>'.((int) $exp->fk_user).'</td>';
		print '</tr>';
	}
	print '</table>';
	print '</div>';
}

print '</div>';

llxFooter();
$db->close();
