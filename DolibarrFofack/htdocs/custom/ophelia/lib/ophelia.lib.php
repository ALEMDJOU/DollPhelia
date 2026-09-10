<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/lib/ophelia.lib.php
 * \ingroup ophelia
 * \brief   Library for Ophelia module: tabs, breadcrumbs, utilities
 */

/**
 * Prepare tabs for the Ophelia admin/setup pages
 *
 * @return array Array of tabs
 */
function opheliaAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("ophelia@ophelia");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/ophelia/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;

	return $head;
}

/**
 * Prepare tabs for a document card
 *
 * @param OpheliaDocument $object Document
 * @return array                   Array of tabs
 */
function opheliaDocumentPrepareHead($object)
{
	global $langs;

	$langs->load("ophelia@ophelia");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/ophelia/document_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (!empty($object->id) && $object->status >= OpheliaDocument::STATUS_PROCESSED) {
		$head[$h][0] = dol_buildpath("/ophelia/extraction_validate.php", 1).'?document_id='.$object->id;
		$head[$h][1] = $langs->trans("OpheliaExtractionResults");
		$head[$h][2] = 'extraction';
		$h++;
	}

	return $head;
}

/**
 * Prepare tabs for a template card
 *
 * @param OpheliaTemplate $object Template
 * @return array                    Array of tabs
 */
function opheliaTemplatePrepareHead($object)
{
	global $langs;

	$langs->load("ophelia@ophelia");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/ophelia/template_card.php", 1).'?id='.$object->id;
	$head[$h][1] = $langs->trans("Card");
	$head[$h][2] = 'card';
	$h++;

	if (!empty($object->id)) {
		$head[$h][0] = dol_buildpath("/ophelia/template_fields.php", 1).'?id='.$object->id;
		$nbFields = is_array($object->lines) ? count($object->lines) : 0;
		$head[$h][1] = $langs->trans("OpheliaTemplateFields").($nbFields ? ' <span class="badge">'.$nbFields.'</span>' : '');
		$head[$h][2] = 'fields';
		$h++;
	}

	return $head;
}

/**
 * Format a byte size into a human readable string
 *
 * @param int $bytes Size in bytes
 * @return string      Human readable size
 */
function opheliaFormatFileSize($bytes)
{
	$bytes = (int) $bytes;
	if ($bytes >= 1048576) {
		return round($bytes / 1048576, 2).' Mo';
	}
	if ($bytes >= 1024) {
		return round($bytes / 1024, 2).' Ko';
	}

	return $bytes.' o';
}

/**
 * Return a CSS badge for a confidence score between 0 and 1
 *
 * @param float $score Confidence score (0-1)
 * @return string        HTML span
 */
function opheliaConfidenceBadge($score)
{
	$score = (float) $score;
	$pct = round($score * 100);

	if ($score >= 0.85) {
		$class = 'ophelia-confidence-high';
	} elseif ($score >= 0.6) {
		$class = 'ophelia-confidence-medium';
	} else {
		$class = 'ophelia-confidence-low';
	}

	return '<span class="'.$class.'">'.$pct.'%</span>';
}

/**
 * Build the absolute filesystem path (readable by the Python microservice) for an uploaded Ophelia document
 *
 * @param string $filename Sanitized filename
 * @return string            Absolute path
 */
function opheliaGetDocumentDir()
{
	global $conf;

	return $conf->ophelia->dir_output.'/documents';
}

/**
 * Persist an ExtractionResponse (from ophelia-service) into llx_ophelia_extraction_result
 * and llx_ophelia_extraction_field, and update the parent document status.
 *
 * @param DoliDB          $db        Database handler
 * @param OpheliaDocument $object    Ophelia document (already fetched)
 * @param array           $apiResult ExtractionResponse array decoded from ophelia-service
 * @param User            $user      Acting user
 * @return int                        Id of created extraction result, or <0 if KO
 */
function opheliaStoreExtractionResult($db, $object, array $apiResult, $user)
{
	require_once DOL_DOCUMENT_ROOT.'/custom/ophelia/class/extractionresult.class.php';

	$db->begin();

	$result = new OpheliaExtractionResult($db);
	$result->fk_document = $object->id;
	$result->fk_template = !empty($apiResult['template_id']) ? (int) $apiResult['template_id'] : null;
	$result->strategy = !empty($apiResult['strategy']) ? $apiResult['strategy'] : null;
	$result->global_confidence = !empty($apiResult['global_confidence']) ? $apiResult['global_confidence'] : 0;
	$result->status = OpheliaExtractionResult::STATUS_RAW;
	$result->date_extraction = dol_now();

	$resultId = $result->create($user, 1);
	if ($resultId <= 0) {
		$db->rollback();
		return -1;
	}

	if (!empty($apiResult['fields']) && is_array($apiResult['fields'])) {
		foreach ($apiResult['fields'] as $f) {
			$field = new OpheliaExtractionField($db);
			$field->fk_result = $resultId;
			$field->field_name = $f['field_name'];
			$field->extracted_value = isset($f['extracted_value']) ? $f['extracted_value'] : null;
			$field->confidence_ocr = isset($f['confidence_ocr']) ? $f['confidence_ocr'] : 0;
			$field->confidence_spatial = isset($f['confidence_spatial']) ? $f['confidence_spatial'] : 0;
			$field->confidence_valid = isset($f['confidence_validation']) ? $f['confidence_validation'] : 0;
			$field->confidence_total = isset($f['confidence_total']) ? $f['confidence_total'] : 0;
			if (!empty($f['bbox'])) {
				$field->bbox_x1 = $f['bbox']['x1'];
				$field->bbox_y1 = $f['bbox']['y1'];
				$field->bbox_x2 = $f['bbox']['x2'];
				$field->bbox_y2 = $f['bbox']['y2'];
			}
			$field->page_num = isset($f['page_num']) ? $f['page_num'] : 1;
			$field->is_validated = 0;

			$fieldId = $field->create($user, 1);
			if ($fieldId <= 0) {
				$db->rollback();
				return -1;
			}
		}
	}

	$object->status = OpheliaDocument::STATUS_PROCESSED;
	$object->fk_template = $result->fk_template;
	$object->matching_score = !empty($apiResult['matching_score']) ? $apiResult['matching_score'] : null;
	$object->strategy_used = $result->strategy;
	if (empty($object->date_processing)) {
		$object->date_processing = dol_now();
	}
	$updRes = $object->update($user, 1);
	if ($updRes <= 0) {
		$db->rollback();
		return -1;
	}

	$db->commit();

	return $resultId;
}
