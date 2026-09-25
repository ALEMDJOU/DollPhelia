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
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/document.class.php';
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
	$object->is_default = 0;
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
	if (!$object->active && $object->is_default) {
		$object->unsetAsDefault($user); // an inactive template cannot remain the default for its doc_type
	} else {
		$object->update($user);
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

if ($action == 'set_default' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$res = $object->setAsDefault($user);
	if ($res > 0) {
		setEventMessages($langs->trans("OpheliaSetDefaultDone"), null);
	} else {
		setEventMessages(implode(', ', $object->errors), null, 'errors');
	}
	header('Location: '.$_SERVER["PHP_SELF"].'?id='.$id);
	exit;
}

if ($action == 'unset_default' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$object->unsetAsDefault($user);
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

// Tester Template : run matching + extraction on a real document, scoped to
// this single template, but never persist an extraction_result nor touch
// the document's status. Lets an administrator validate spatial anchors
// (delta_x/delta_y/theta/key_text) before relying on the template in production.
$testDocumentId = GETPOSTINT('test_document_id');
$testResult = null;
$testError = null;
if ($action == 'run_test' && $id > 0 && $user->hasRight('ophelia', 'document', 'write')) {
	$action = 'test';
	if ($testDocumentId <= 0) {
		setEventMessages($langs->trans("OpheliaErrorNoDocumentSelected"), null, 'errors');
	} else {
		$testDoc = new OpheliaDocument($db);
		if ($testDoc->fetch($testDocumentId) <= 0 || empty($testDoc->filepath) || !dol_is_file($testDoc->filepath)) {
			setEventMessages($langs->trans("OpheliaFileNotFound"), null, 'errors');
		} else {
			$api = new ApiOphelia();
			$ocrLang = getDolGlobalString('OPHELIA_OCR_LANG', 'eng');
			try {
				$testResult = $api->processSync($testDoc->filepath, array($object->toApiSchema()), $ocrLang, 'template_matching');
			} catch (Exception $e) {
				$testError = $e->getMessage();
			}
		}
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
} elseif ($action == 'test' && $id > 0) {
	$head = opheliaTemplatePrepareHead($object);
	print dol_get_fiche_head($head, 'card', $langs->trans("OpheliaTemplate"), -1, 'ophelia@ophelia');

	print load_fiche_titre($langs->trans("OpheliaTestTemplate").' - '.$object->label, '', '');

	$testDocs = new OpheliaDocument($db);
	$testDocsList = $testDocs->fetchAll('DESC', 't.date_upload', 0, 0, '');
	if (!is_array($testDocsList)) {
		$testDocsList = array();
	}

	if (empty($testDocsList)) {
		print '<div class="opacitymedium">'.$langs->trans("OpheliaNoDocumentToTest").'</div>';
	} else {
		print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'" class="marginTopOnly">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="action" value="run_test">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<label for="test_document_id" style="margin-right:8px;">'.$langs->trans("OpheliaSelectDocumentToTest").'</label>';
		print '<select id="test_document_id" name="test_document_id" class="flat minwidth300">';
		foreach ($testDocsList as $td) {
			$selected = ($testDocumentId == $td->id) ? ' selected' : '';
			print '<option value="'.$td->id.'"'.$selected.'>'.dol_escape_htmltag($td->ref.' - '.$td->label).'</option>';
		}
		print '</select> ';
		print '<input type="submit" class="button" value="'.$langs->trans("OpheliaRunTest").'">';
		print '</form>';
	}

	if ($testError !== null) {
		print '<div class="error marginTopOnly">'.dol_escape_htmltag($testError).'</div>';
	} elseif ($testResult !== null) {
		print '<div class="marginTopOnly">';
		print load_fiche_titre($langs->trans("OpheliaTestResult"), '', '');
		print '<table class="border tableforfield centpercent">';
		print '<tr><td class="titlefield">'.$langs->trans("OpheliaMatchingScore").'</td><td>'.(isset($testResult['matching_score']) && $testResult['matching_score'] !== null ? opheliaConfidenceBadge($testResult['matching_score']) : $langs->trans("OpheliaNoMatch")).'</td></tr>';
		print '<tr><td>'.$langs->trans("OpheliaGlobalConfidence").'</td><td>'.opheliaConfidenceBadge(isset($testResult['global_confidence']) ? $testResult['global_confidence'] : 0).'</td></tr>';
		print '</table>';

		if (!empty($testResult['fields']) && is_array($testResult['fields'])) {
			print '<div class="div-table-responsive marginTopOnly">';
			print '<table class="tagtable liste centpercent">';
			print '<tr class="liste_titre">';
			print '<td>'.$langs->trans("OpheliaFieldName").'</td>';
			print '<td>'.$langs->trans("OpheliaExtractedValue").'</td>';
			print '<td class="center">'.$langs->trans("OpheliaConfidenceTotal").'</td>';
			print '</tr>';
			foreach ($testResult['fields'] as $f) {
				print '<tr class="oddeven">';
				print '<td>'.dol_escape_htmltag($f['field_name'] ?? '').'</td>';
				print '<td>'.dol_escape_htmltag($f['extracted_value'] ?? '').'</td>';
				print '<td class="center">'.opheliaConfidenceBadge($f['confidence_total'] ?? 0).'</td>';
				print '</tr>';
			}
			print '</table>';
			print '</div>';
		} else {
			print '<div class="opacitymedium marginTopOnly">'.$langs->trans("OpheliaNoFieldExtracted").'</div>';
		}
		print '</div>';
	}

	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'">'.$langs->trans("Back").'</a>';
	print '</div>';
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
	print '<tr><td>'.$langs->trans("OpheliaDefaultTemplate").'</td><td>'.(!empty($object->is_default) ? img_picto($langs->trans("Yes"), 'star').' '.$langs->trans("Yes") : $langs->trans("No")).'</td></tr>';
	print '</table>';
	print '</div>';

	print dol_get_fiche_end();

	print '<div class="tabsAction">';
	if ($user->hasRight('ophelia', 'document', 'write')) {
		print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=edit">'.$langs->trans("Modify").'</a>';
		print '<a class="butAction" href="'.dol_buildpath('/ophelia/template_fields.php', 1).'?id='.$object->id.'">'.$langs->trans("OpheliaManageFields").'</a>';
		if (!empty($object->lines)) {
			print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=test">'.$langs->trans("OpheliaTestTemplate").'</a>';
		}
		if ($object->active) {
			if (!empty($object->is_default)) {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=unset_default&token='.newToken().'">'.$langs->trans("OpheliaUnsetDefault").'</a>';
			} else {
				print '<a class="butAction" href="'.$_SERVER["PHP_SELF"].'?id='.$object->id.'&action=set_default&token='.newToken().'">'.$langs->trans("OpheliaSetDefault").'</a>';
			}
		}
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
