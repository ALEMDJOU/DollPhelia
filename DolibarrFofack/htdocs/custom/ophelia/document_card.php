<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/document_card.php
 * \ingroup ophelia
 * \brief   Card (view / create / launch processing) of an Ophelia document
 */

require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/template.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/strategy.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/api_ophelia.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/files.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.form.class.php';

global $db, $langs, $user, $conf;

$langs->load("ophelia@ophelia");

if (!isModEnabled('ophelia') || !$user->hasRight('ophelia', 'document', 'read')) {
	accessforbidden();
}

$id = GETPOSTINT('id');
$action = GETPOST('action', 'aZ09');

$object = new OpheliaDocument($db);
if ($id > 0) {
	$res = $object->fetch($id);
	if ($res <= 0) {
		accessforbidden('OpheliaDocumentNotFound');
	}
}

// Ajax polling relay: relays the ophelia-service task status and persists the result once completed
if ($action == 'ajax_status') {
	top_httphead('application/json');

	$taskId = GETPOST('task_id', 'alpha');
	$docId = GETPOSTINT('document_id');

	$api = new ApiOphelia();
	try {
		$status = $api->getStatus($taskId);

		if (!empty($status['status']) && $status['status'] === 'completed' && !empty($status['result']) && $docId > 0) {
			$doc = new OpheliaDocument($db);
			$doc->fetch($docId);
			if ($doc->status < OpheliaDocument::STATUS_PROCESSED) {
				opheliaStoreExtractionResult($db, $doc, $status['result'], $user);
			}
		}

		echo json_encode($status);
	} catch (Exception $e) {
		echo json_encode(array('status' => 'failed', 'progress' => 0, 'message' => $e->getMessage()));
	}
	exit;
}

// Serve the raw uploaded file : action=file&inline=1 for in-page preview,
// action=file (no inline) forces a "Save as" download.
if ($action == 'file' && $id > 0 && $user->hasRight('ophelia', 'document', 'read')) {
	if (empty($object->filepath) || !dol_is_file($object->filepath)) {
		accessforbidden('OpheliaFileNotFound');
	}

	$inline = GETPOSTINT('inline');

	top_httphead(dol_mimetype($object->filename));
	header('Content-Disposition: '.($inline ? 'inline' : 'attachment').'; filename="'.dol_sanitizeFileName($object->filename).'"');
	header('Content-Length: '.filesize($object->filepath));
	readfile($object->filepath);
	exit;
}

// Actions
if ($action == 'add' && $user->hasRight('ophelia', 'document', 'write')) {
	$label = GETPOST('label', 'alphanohtml');
	$doc_type = GETPOST('doc_type', 'alpha');

	// Formats acceptes par ophelia-service : images (OCR), PDF (texte natif
	// ou OCR de repli sur les pages scannees), DOCX et XLSX (texte natif).
	$allowedExtensions = array('png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp', 'webp', 'pdf', 'docx', 'xlsx');

	if (empty($_FILES['userfile']) || empty($_FILES['userfile']['name'])) {
		setEventMessages($langs->trans("OpheliaErrorNoFileSelected"), null, 'errors');
		$action = 'create';
	} elseif (!in_array(strtolower(pathinfo($_FILES['userfile']['name'], PATHINFO_EXTENSION)), $allowedExtensions, true)) {
		setEventMessages($langs->trans("OpheliaErrorFileType", implode(', ', $allowedExtensions)), null, 'errors');
		$action = 'create';
	} else {
		$uploaddir = opheliaGetDocumentDir();
		dol_mkdir($uploaddir);

		$originalName = dol_sanitizeFileName($_FILES['userfile']['name']);
		$ref = 'DOC'.dol_print_date(dol_now(), '%Y%m%d%H%M%S').sprintf('%03d', mt_rand(0, 999));
		$ext = pathinfo($originalName, PATHINFO_EXTENSION);
		$storedName = $ref.($ext ? '.'.$ext : '');
		$destFile = $uploaddir.'/'.$storedName;

		$result = dol_move_uploaded_file($_FILES['userfile']['tmp_name'], $destFile, 0, 0, $_FILES['userfile']['error'], 0, 'userfile');

		if ($result > 0 || (is_string($result) && dol_is_file($destFile))) {
			$object->ref = $ref;
			$object->label = $label ? $label : $originalName;
			$object->filename = $storedName;
			$object->filepath = $destFile;
			$object->filetype = $_FILES['userfile']['type'];
			$object->filesize = @filesize($destFile);
			$object->doc_type = $doc_type;
			$object->status = OpheliaDocument::STATUS_UPLOADED;
			$object->fk_user_upload = $user->id;
			$object->date_upload = dol_now();

			$newid = $object->create($user);
			if ($newid > 0) {
				setEventMessages($langs->trans("RecordSaved"), null);
				header('Location: '.$_SERVER["PHP_SELF"].'?id='.$newid);
				exit;
			} else {
				setEventMessages(implode(', ', $object->errors), null, 'errors');
				$action = 'create';
			}
		} else {
			setEventMessages($langs->trans("OpheliaErrorUpload").' : '.(is_string($result) ? $result : ''), null, 'errors');
			$action = 'create';
		}
	}
}

if ($action == 'launch' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$templateObj = new OpheliaTemplate($db);
	$activeTemplates = $templateObj->fetchAll('ASC', 't.rowid', 0, 0, "(active:=:1)");
	$templatesSchema = array();
	if (is_array($activeTemplates)) {
		foreach ($activeTemplates as $tpl) {
			$tpl->fetchFields();
			$templatesSchema[] = $tpl->toApiSchema();
		}
	}

	// L'utilisateur peut choisir explicitement la strategie, ou la
	// deleguer au systeme ('auto', par defaut). La liste des strategies
	// proposees vient de llx_ophelia_strategy (geree via strategy.php) ;
	// seuls les codes reellement implementes cote ophelia-service
	// (VALID_STRATEGIES, orchestrator.py) y ont un effet. Toute valeur
	// hors de cette liste est ramenee a 'auto' avant meme d'appeler l'API.
	opheliaEnsureDefaultStrategies($db);
	$strategyObj = new OpheliaStrategy($db);
	$activeStrategyRecords = $strategyObj->fetchAll('ASC', 't.priority', 0, 0, '(active:=:1)');
	if (!is_array($activeStrategyRecords)) {
		$activeStrategyRecords = array();
	}
	$allowedStrategies = array();
	foreach ($activeStrategyRecords as $s) {
		$allowedStrategies[] = $s->code;
	}
	if (!in_array('auto', $allowedStrategies, true)) {
		$allowedStrategies[] = 'auto'; // 'auto' must always remain selectable as ultimate fallback
	}
	$strategy = GETPOST('strategy', 'aZ09');
	if (!in_array($strategy, $allowedStrategies, true)) {
		$strategy = 'auto';
	}

	$api = new ApiOphelia();
	$launched = false;
	$ocrLang = getDolGlobalString('OPHELIA_OCR_LANG', 'eng');

	try {
		$taskId = $api->startProcessing($object->filepath, $templatesSchema, $ocrLang, $strategy);
		$object->task_id = $taskId;
		$object->status = OpheliaDocument::STATUS_PROCESSING;
		$object->update($user);
		$launched = true;
	} catch (Exception $e) {
		dol_syslog("Ophelia: async start failed, falling back to sync: ".$e->getMessage(), LOG_WARNING);
	}

	if (!$launched) {
		try {
			$syncResult = $api->processSync($object->filepath, $templatesSchema, $ocrLang, $strategy);
			opheliaStoreExtractionResult($db, $object, $syncResult, $user);
			setEventMessages($langs->trans("OpheliaProcessingDone"), null);
			header('Location: '.dol_buildpath('/ophelia/extraction_validate.php', 1).'?document_id='.$object->id);
			exit;
		} catch (Exception $e2) {
			setEventMessages($langs->trans("OpheliaErrorApi").' : '.$e2->getMessage(), null, 'errors');
		}
	} else {
		header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
		exit;
	}
}

if ($action == 'confirm_delete' && $id > 0 && $user->hasRight('ophelia', 'document', 'delete')) {
	// Delete the DB row(s) first (OpheliaDocument::delete() now cascades to
	// extraction_result/extraction_field/export_history) and only remove the
	// physical file once that succeeds. Deleting the file first meant a failed
	// DB delete (e.g. a stale FK constraint) still left the file gone forever,
	// with the document record surviving and pointing at nothing - breaking
	// OCR/NER on any later retry since there was no file left to read.
	$filepathToRemove = $object->filepath;
	$res = $object->delete($user);
	if ($res > 0) {
		if (!empty($filepathToRemove) && dol_is_file($filepathToRemove)) {
			dol_delete_file($filepathToRemove);
		}
		setEventMessages($langs->trans("RecordDeleted"), null);
		header('Location: '.dol_buildpath('/ophelia/document_list.php', 1));
		exit;
	} else {
		setEventMessages(implode(', ', $object->errors), null, 'errors');
	}
}

// View
$form = new Form($db);

llxHeader('', $langs->trans("OpheliaDocuments"));

print '<div class="ophelia-app">';

if ($action == 'create') {
	print load_fiche_titre($langs->trans("OpheliaNewDocument"), '', 'ophelia@ophelia');

	print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" enctype="multipart/form-data">';
	print '<input type="hidden" name="token" value="'.newToken().'">';
	print '<input type="hidden" name="action" value="add">';

	print dol_get_fiche_head();

	print '<table class="border centpercent">';
	print '<tr><td class="titlefieldcreate fieldrequired">'.$langs->trans("OpheliaFileToUpload").'</td>';
	print '<td><input type="file" name="userfile" accept=".png,.jpg,.jpeg,.tif,.tiff,.bmp,.webp,.pdf,.docx,.xlsx" required>';
	print '<br><span class="opacitymedium small">'.$langs->trans("OpheliaAcceptedFormats").'</span></td></tr>';
	print '<tr><td>'.$langs->trans("Label").'</td>';
	print '<td><input type="text" name="label" class="minwidth300"></td></tr>';
	print '<tr><td>'.$langs->trans("DocType").'</td>';
	print '<td><input type="text" name="doc_type" class="minwidth200" placeholder="facture, cv, kbis, rib..."></td></tr>';
	print '</table>';

	print dol_get_fiche_end();

	print '<div class="center">';
	print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
	print ' <a class="button button-cancel" href="'.dol_buildpath('/ophelia/document_list.php', 1).'">'.$langs->trans("Cancel").'</a>';
	print '</div>';

	print '</form>';
} elseif ($id > 0) {
	$fileExt = strtolower(pathinfo($object->filename, PATHINFO_EXTENSION));
	$isPreviewableImage = in_array($fileExt, array('png', 'jpg', 'jpeg', 'tif', 'tiff', 'bmp', 'webp'), true);
	$isPreviewablePdf = ($fileExt === 'pdf');
	$isPreviewable = ($isPreviewableImage || $isPreviewablePdf) && !empty($object->filepath) && dol_is_file($object->filepath);

	$head = opheliaDocumentPrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("OpheliaDocument"), -1, 'ophelia@ophelia');

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	print '<table class="border tableforfield centpercent">';
	print '<tr><td class="titlefield">'.$langs->trans("Ref").'</td><td>'.dol_escape_htmltag($object->ref).'</td></tr>';
	print '<tr><td>'.$langs->trans("Label").'</td><td>'.dol_escape_htmltag($object->label).'</td></tr>';
	print '<tr><td>'.$langs->trans("OpheliaFileName").'</td><td>'.dol_escape_htmltag($object->filename).'</td></tr>';
	print '<tr><td>'.$langs->trans("OpheliaFileSize").'</td><td>'.opheliaFormatFileSize($object->filesize).'</td></tr>';
	print '<tr><td>'.$langs->trans("DocType").'</td><td>'.dol_escape_htmltag($object->doc_type).'</td></tr>';
	print '<tr><td>'.$langs->trans("DateUpload").'</td><td>'.dol_print_date($object->date_upload, 'dayhour').'</td></tr>';
	if ($object->matching_score !== null) {
		print '<tr><td>'.$langs->trans("OpheliaMatchingScore").'</td><td>'.opheliaConfidenceBadge($object->matching_score).'</td></tr>';
	}
	if ($object->strategy_used) {
		print '<tr><td>'.$langs->trans("OpheliaStrategyUsed").'</td><td>'.dol_escape_htmltag($object->strategy_used).'</td></tr>';
	}
	print '<tr><td>'.$langs->trans("Status").'</td><td>'.$object->getLibStatut(4).'</td></tr>';
	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	// Launch panel: let the user pick a strategy explicitly, or delegate it to the system ('auto').
	// Options come from llx_ophelia_strategy (active=1, managed via strategy.php), not a hardcoded list.
	if ($object->status == OpheliaDocument::STATUS_UPLOADED && $user->hasRight('ophelia', 'document', 'write')) {
		opheliaEnsureDefaultStrategies($db);
		$strategyObj = new OpheliaStrategy($db);
		$activeStrategyRecords = $strategyObj->fetchAll('ASC', 't.priority', 0, 0, '(active:=:1)');
		if (!is_array($activeStrategyRecords)) {
			$activeStrategyRecords = array();
		}
		$hasAutoOption = false;
		foreach ($activeStrategyRecords as $s) {
			if ($s->code === 'auto') {
				$hasAutoOption = true;
				break;
			}
		}

		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="center marginTopOnly">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="launch">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<label for="ophelia-strategy" style="margin-right:8px;">'.$langs->trans("OpheliaStrategyChoice").'</label>';
		print '<select id="ophelia-strategy" name="strategy" class="flat">';
		if (!$hasAutoOption) {
			print '<option value="auto">'.$langs->trans("OpheliaStrategyAuto").'</option>';
		}
		foreach ($activeStrategyRecords as $s) {
			print '<option value="'.dol_escape_htmltag($s->code).'">'.dol_escape_htmltag($s->label).'</option>';
		}
		print '</select> ';
		print '<input type="submit" class="button" value="'.$langs->trans("OpheliaLaunchProcessing").'">';
		print '</form>';
	}

	// Action buttons
	print '<div class="tabsAction">';
	if ($object->status == OpheliaDocument::STATUS_PROCESSING) {
		print '<span class="butActionRefused classfortooltip">'.$langs->trans("OpheliaProcessingInProgress").'</span>';
	}
	if ($object->status >= OpheliaDocument::STATUS_PROCESSED) {
		print '<a class="butAction" href="'.dol_buildpath('/ophelia/extraction_validate.php', 1).'?document_id='.$object->id.'">'.$langs->trans("OpheliaViewExtraction").'</a>';
	}
	if (!empty($object->filepath) && dol_is_file($object->filepath)) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=file">'.$langs->trans("OpheliaDownloadDocument").'</a>';
	}
	if ($isPreviewable) {
		print '<a class="butAction" href="#" onclick="jQuery(\'#ophelia-preview-panel\').toggle(); return false;">'.$langs->trans("OpheliaPreviewDocument").'</a>';
	}
	if ($user->hasRight('ophelia', 'document', 'delete')) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=confirm_delete&token='.newToken().'" onclick="return confirm(\''.dol_escape_js($langs->trans("ConfirmDelete")).'\');">'.$langs->trans("Delete").'</a>';
	}
	print '</div>';

	// Preview panel (image or PDF only), hidden until the user clicks "Previsualiser"
	if ($isPreviewable) {
		print '<div id="ophelia-preview-panel" class="marginTopOnly" style="display:none;">';
		if ($isPreviewableImage) {
			print '<img src="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=file&inline=1" class="ophelia-bbox-preview" alt="'.dol_escape_htmltag($object->label).'">';
		} else {
			print '<iframe src="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=file&inline=1" style="width:100%;height:700px;border:1px solid var(--ophelia-bordeaux, #800020);"></iframe>';
		}
		print '</div>';
	}

	// Polling widget while processing
	if ($object->status == OpheliaDocument::STATUS_PROCESSING && $object->task_id) {
		print '<div class="ophelia-progress-wrapper"><div id="ophelia-progress-bar" class="ophelia-progress-bar" style="width:5%">...</div></div>';
		print '<div id="ophelia-status-message" class="ophelia-status-message">'.$langs->trans("OpheliaWaitingForProcessing").'</div>';

		print '<script>
		jQuery(document).ready(function() {
			opheliaStartPolling(
				'.json_encode($object->task_id).',
				'.json_encode($object->id).',
				'.json_encode($_SERVER["PHP_SELF"].'?action=ajax_status').',
				'.json_encode(dol_buildpath('/ophelia/extraction_validate.php', 1).'?document_id='.$object->id).'
			);
		});
		</script>';
	}
} else {
	header('Location: '.dol_buildpath('/ophelia/document_list.php', 1));
	exit;
}

print '</div>';

llxFooter();
$db->close();
