<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/admin/setup.php
 * \ingroup ophelia
 * \brief   Ophelia setup page: configure the ophelia-service microservice URL
 */

require '../../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/admin.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/lib/ophelia.lib.php';
require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/api_ophelia.class.php';

global $db, $langs, $user, $conf;

$langs->loadLangs(array("admin", "ophelia@ophelia"));

if (!$user->admin) {
	accessforbidden();
}

$action = GETPOST('action', 'aZ09');

if ($action == 'update') {
	$apiUrl = GETPOST('OPHELIA_API_URL', 'alpha');
	$threshold = GETPOST('OPHELIA_MATCHING_THRESHOLD', 'alpha');
	$ocrLang = GETPOST('OPHELIA_OCR_LANG', 'alpha');

	dolibarr_set_const($db, 'OPHELIA_API_URL', rtrim($apiUrl, '/'), 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'OPHELIA_MATCHING_THRESHOLD', $threshold, 'chaine', 0, '', $conf->entity);
	dolibarr_set_const($db, 'OPHELIA_OCR_LANG', $ocrLang ? $ocrLang : 'eng', 'chaine', 0, '', $conf->entity);

	setEventMessages($langs->trans("SetupSaved"), null);
	header('Location: '.$_SERVER["PHP_SELF"]);
	exit;
}

$testResult = null;
if ($action == 'test') {
	$api = new ApiOphelia();
	try {
		$testResult = array('ok' => true, 'data' => $api->checkHealth());
	} catch (Exception $e) {
		$testResult = array('ok' => false, 'message' => $e->getMessage());
	}
}

llxHeader('', $langs->trans("OpheliaSetup"));

print '<div class="ophelia-app">';

$linkback = '<a href="'.DOL_URL_ROOT.'/admin/modules.php?restore_lastsearch_values=1">'.$langs->trans("BackToModuleList").'</a>';
print load_fiche_titre($langs->trans("OpheliaSetup"), $linkback, 'ophelia@ophelia');

$head = opheliaAdminPrepareHead();
print dol_get_fiche_head($head, 'settings', $langs->trans("ModuleOpheliaName"), -1, 'ophelia@ophelia');

print '<form method="POST" action="'.$_SERVER["PHP_SELF"].'">';
print '<input type="hidden" name="token" value="'.newToken().'">';
print '<input type="hidden" name="action" value="update">';

print '<table class="noborder centpercent">';
print '<tr class="liste_titre"><td>'.$langs->trans("Parameter").'</td><td>'.$langs->trans("Value").'</td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("OpheliaApiUrl").'</td>';
print '<td><input type="text" name="OPHELIA_API_URL" class="minwidth300" value="'.dol_escape_htmltag(getDolGlobalString('OPHELIA_API_URL', 'http://localhost:8000')).'"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("OpheliaMatchingThreshold").'</td>';
print '<td><input type="text" name="OPHELIA_MATCHING_THRESHOLD" class="width75" value="'.dol_escape_htmltag(getDolGlobalString('OPHELIA_MATCHING_THRESHOLD', '0.7')).'"></td></tr>';

print '<tr class="oddeven"><td>'.$langs->trans("OpheliaOcrLang").'</td>';
print '<td><input type="text" name="OPHELIA_OCR_LANG" class="width75" value="'.dol_escape_htmltag(getDolGlobalString('OPHELIA_OCR_LANG', 'eng')).'"> <span class="opacitymedium">('.$langs->trans("OpheliaOcrLangHelp").')</span></td></tr>';

print '</table>';

print '<div class="center marginTopOnly">';
print '<input type="submit" class="button" value="'.$langs->trans("Save").'">';
print '</div>';

print '</form>';

print '<div class="center marginTopOnly">';
print '<a class="button" href="'.$_SERVER["PHP_SELF"].'?action=test&token='.newToken().'">'.$langs->trans("OpheliaTestConnection").'</a>';
print '</div>';

if ($testResult !== null) {
	print '<div class="marginTopOnly">';
	if ($testResult['ok']) {
		print img_picto('', 'tick').' <span style="color:#1c7c3f">'.$langs->trans("OpheliaConnectionOk").'</span>';
		print '<pre>'.dol_escape_htmltag(json_encode($testResult['data'], JSON_PRETTY_PRINT)).'</pre>';
	} else {
		print img_warning().' <span style="color:#c9312b">'.$langs->trans("OpheliaConnectionFailed").' : '.dol_escape_htmltag($testResult['message']).'</span>';
	}
	print '</div>';
}

print dol_get_fiche_end();

print '</div>';

llxFooter();
$db->close();
