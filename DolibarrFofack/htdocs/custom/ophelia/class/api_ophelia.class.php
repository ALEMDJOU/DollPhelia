<?php
/* Copyright (C) 2026 Ophelia
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 */

/**
 * \file    htdocs/custom/ophelia/class/api_ophelia.class.php
 * \ingroup ophelia
 * \brief   HTTP client for the Ophelia Python microservice (ophelia-service)
 */

/**
 * Class ApiOphelia
 *
 * Thin HTTP client over the ophelia-service FastAPI microservice.
 * Never duplicates extraction logic: PHP only forwards data and stores results.
 */
class ApiOphelia
{
	/** @var string Base URL of the ophelia-service microservice */
	private $baseUrl;

	/** @var string Last error message */
	public $error = '';

	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->baseUrl = getDolGlobalString('OPHELIA_API_URL', 'http://localhost:8000');
	}

	/**
	 * Call the Ophelia microservice API
	 *
	 * @param string $endpoint Endpoint relative to /api/v1/ (e.g. 'ocr/extract')
	 * @param array  $data     Payload to json_encode for POST requests
	 * @param string $method   HTTP method ('GET' or 'POST')
	 * @return array            Decoded JSON response
	 * @throws Exception        On transport error or non-200 HTTP status
	 */
	private function callApi($endpoint, array $data = array(), $method = 'POST')
	{
		$url = rtrim($this->baseUrl, '/').'/api/v1/'.ltrim($endpoint, '/');

		$ch = curl_init($url);
		curl_setopt_array($ch, array(
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_TIMEOUT => 120,
			CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
		));
		if ($method === 'POST') {
			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
		}

		$response = curl_exec($ch);
		$curlErrno = curl_errno($ch);
		$curlError = curl_error($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($curlErrno) {
			$this->error = 'Ophelia API cURL error: '.$curlError;
			throw new Exception($this->error);
		}

		if ($httpCode !== 200) {
			$this->error = 'Ophelia API: HTTP '.$httpCode.' - '.$response;
			throw new Exception($this->error);
		}

		$decoded = json_decode($response, true);
		if (json_last_error() !== JSON_ERROR_NONE) {
			$this->error = 'Ophelia API: invalid JSON response';
			throw new Exception($this->error);
		}

		return $decoded;
	}

	/**
	 * GET /api/v1/health
	 *
	 * @return array Health status
	 */
	public function checkHealth()
	{
		return $this->callApi('health', array(), 'GET');
	}

	/**
	 * POST /api/v1/ocr/extract
	 *
	 * @param string $filepath Absolute path to the file (readable by the Python service)
	 * @param string $lang     Tesseract language code
	 * @return array            OcrResponse
	 */
	public function extractOcr($filepath, $lang = 'eng')
	{
		return $this->callApi('ocr/extract', array('filepath' => $filepath, 'lang' => $lang));
	}

	/**
	 * POST /api/v1/matching/match
	 *
	 * @param array       $elements List of TextElementSchema arrays
	 * @param array       $templates List of TemplateSchema arrays
	 * @param float|null  $threshold Matching threshold override
	 * @param string|null $docType   Expected document type
	 * @return array                  MatchingResponse
	 */
	public function matchTemplate(array $elements, array $templates, $threshold = null, $docType = null)
	{
		$payload = array(
			'doc_type' => $docType,
			'elements' => $elements,
			'templates' => $templates,
		);
		if ($threshold !== null) {
			$payload['threshold'] = $threshold;
		}

		return $this->callApi('matching/match', $payload);
	}

	/**
	 * POST /api/v1/extraction/extract
	 *
	 * @param array  $elements List of TextElementSchema arrays
	 * @param array  $template TemplateSchema array
	 * @param string $strategy 'template_matching' | 'ai_layoutlm'
	 * @return array            ExtractionResponse
	 */
	public function extractFields(array $elements, array $template, $strategy = 'template_matching')
	{
		return $this->callApi('extraction/extract', array(
			'elements' => $elements,
			'template' => $template,
			'strategy' => $strategy,
		));
	}

	/**
	 * POST /api/v1/extraction/process/start
	 *
	 * @param string $filepath  Absolute path to the file
	 * @param array  $templates List of TemplateSchema arrays
	 * @param string $lang      Tesseract language code
	 * @return string             Task id
	 */
	public function startProcessing($filepath, array $templates, $lang = 'eng')
	{
		$r = $this->callApi('extraction/process/start', array(
			'filepath' => $filepath,
			'templates' => $templates,
			'lang' => $lang,
		));

		return $r['task_id'];
	}

	/**
	 * GET /api/v1/extraction/process/status/{taskId}
	 *
	 * @param string $taskId Task id returned by startProcessing()
	 * @return array           ProcessStatusResponse
	 */
	public function getStatus($taskId)
	{
		return $this->callApi('extraction/process/status/'.urlencode($taskId), array(), 'GET');
	}

	/**
	 * POST /api/v1/extraction/process/sync
	 * Fallback used when Redis/Celery is not available.
	 *
	 * @param string $filepath  Absolute path to the file
	 * @param array  $templates List of TemplateSchema arrays
	 * @param string $lang      Tesseract language code
	 * @return array              ExtractionResponse (full pipeline result)
	 */
	public function processSync($filepath, array $templates, $lang = 'eng')
	{
		return $this->callApi('extraction/process/sync', array(
			'filepath' => $filepath,
			'templates' => $templates,
			'lang' => $lang,
		));
	}
}
