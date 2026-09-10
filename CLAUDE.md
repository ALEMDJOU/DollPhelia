# DollPhela — Module Ophelia pour Dolibarr

## Structure du workspace

```
DollPhela/
├── DolibarrFofack/          # Module Dolibarr (PHP) — a construire
│   └── htdocs/custom/ophelia/
└── ophelia-service/         # Microservice Python (FastAPI) — TERMINE
    └── (voir ophelia-service/CLAUDE.md pour le detail)
```

## Contexte

Ophelia est un module d'extraction automatique de donnees documentaires pour Dolibarr ERP. Deux parties :

1. **ophelia-service/** — Microservice Python TERMINE. Tourne sur http://localhost:8000. Doc OpenAPI sur http://localhost:8000/docs.
2. **DolibarrFofack/** — Module PHP a construire. Interface utilisateur, persistance BDD, appels REST vers ophelia-service.

## Environnement

- OS : Windows 10/11
- Serveur : WAMP (Apache + PHP 8.x + MariaDB)
- Dolibarr installe dans C:\wamp64\www\dolibarr\
- Le module va dans : C:\wamp64\www\dolibarr\htdocs\custom\ophelia\
- ophelia-service tourne sur : http://localhost:8000
- Tesseract OCR : C:\Program Files\Tesseract-OCR\

## Architecture du module Dolibarr

```
DolibarrFofack/htdocs/custom/ophelia/
├── core/
│   ├── modules/
│   │   └── modOphelia.class.php          # Descripteur (droits, menus, tables)
│   └── triggers/
│       └── interface_99_modOphelia_OpheliaTriggers.class.php
├── class/
│   ├── document.class.php                # DAO documents
│   ├── template.class.php                # DAO templates
│   ├── templatefield.class.php           # DAO champs
│   ├── extractionresult.class.php        # DAO resultats
│   ├── extractionfield.class.php         # DAO valeurs extraites
│   ├── strategy.class.php                # DAO strategies
│   ├── exporthistory.class.php           # DAO historique exports
│   └── api_ophelia.class.php             # Client HTTP vers ophelia-service
├── lib/
│   └── ophelia.lib.php                   # Onglets, breadcrumbs, utilitaires
├── sql/
│   ├── llx_ophelia_document.sql + .key.sql
│   ├── llx_ophelia_template.sql + .key.sql
│   ├── llx_ophelia_template_field.sql + .key.sql
│   ├── llx_ophelia_extraction_result.sql + .key.sql
│   ├── llx_ophelia_extraction_field.sql + .key.sql
│   ├── llx_ophelia_strategy.sql + .key.sql
│   └── llx_ophelia_export_history.sql + .key.sql
├── img/ophelia.png
├── css/ophelia.css
├── js/ophelia.js
├── document_card.php
├── document_list.php
├── template_card.php
├── template_list.php
├── template_fields.php
├── extraction_result.php
├── extraction_validate.php
├── export.php
├── strategy.php
├── admin/setup.php
└── langs/fr_FR/ophelia.lang
```

## Tables (prefixe llx_ophelia_)

### llx_ophelia_document
rowid INT PK, ref VARCHAR(128) UNIQUE, label VARCHAR(255), filename VARCHAR(255), filepath VARCHAR(512), filetype VARCHAR(50), filesize BIGINT, doc_type VARCHAR(50), status SMALLINT (0=televerse 1=en_cours 2=traite 3=valide), fk_user_upload INT, fk_template INT NULL, matching_score DECIMAL(5,4) NULL, strategy_used VARCHAR(50) NULL, task_id VARCHAR(255) NULL, date_upload DATETIME, date_processing DATETIME NULL, date_validation DATETIME NULL, tms TIMESTAMP

### llx_ophelia_template
rowid INT PK, ref VARCHAR(128) UNIQUE, label VARCHAR(255), description TEXT, doc_type VARCHAR(50), version INT DEFAULT 1, active TINYINT DEFAULT 1, fk_user_author INT, date_creation DATETIME, tms TIMESTAMP

### llx_ophelia_template_field
rowid INT PK, fk_template INT, field_name VARCHAR(128), field_label VARCHAR(255), field_type VARCHAR(50), key_text VARCHAR(255), delta_x FLOAT, delta_y FLOAT, theta FLOAT, distance FLOAT, relation_type VARCHAR(20), extraction_method VARCHAR(50), required TINYINT DEFAULT 1, rang INT DEFAULT 0

### llx_ophelia_extraction_result
rowid INT PK, fk_document INT, fk_template INT NULL, strategy VARCHAR(50), global_confidence DECIMAL(5,4), status SMALLINT (0=brut 1=valide 2=exporte), fk_user_validator INT NULL, date_extraction DATETIME, date_validation DATETIME NULL, tms TIMESTAMP

### llx_ophelia_extraction_field
rowid INT PK, fk_result INT, fk_template_field INT NULL, field_name VARCHAR(128), extracted_value TEXT, corrected_value TEXT NULL, confidence_ocr DECIMAL(5,4), confidence_spatial DECIMAL(5,4), confidence_valid DECIMAL(5,4), confidence_total DECIMAL(5,4), bbox_x1 INT, bbox_y1 INT, bbox_x2 INT, bbox_y2 INT, page_num INT, is_validated TINYINT DEFAULT 0

### llx_ophelia_strategy
rowid INT PK, code VARCHAR(50) UNIQUE, label VARCHAR(255), description TEXT, priority INT, active TINYINT DEFAULT 1, params_json TEXT NULL

### llx_ophelia_export_history
rowid INT PK, fk_result INT, fk_user INT, export_format VARCHAR(10), filepath VARCHAR(512), date_export DATETIME

## API ophelia-service (endpoints que PHP appelle)

Base URL configurable dans admin/setup.php, defaut http://localhost:8000

```
GET  /api/v1/health
POST /api/v1/ocr/extract                  { filepath, lang }
POST /api/v1/matching/match               { doc_type, elements, templates, threshold }
POST /api/v1/extraction/extract            { elements, template, strategy }
POST /api/v1/extraction/process/start      { filepath, templates, lang } → { task_id }
GET  /api/v1/extraction/process/status/ID  → { status, progress, message, result }
POST /api/v1/extraction/process/sync       { filepath, templates, lang } → resultat complet
POST /api/v1/export/json                   ExtractionResponse + document_ref → fichier
POST /api/v1/export/xml                    ExtractionResponse + document_ref → fichier
```

## Client HTTP PHP — class/api_ophelia.class.php

```php
class ApiOphelia
{
    private string $baseUrl;

    public function __construct()
    {
        global $conf;
        $this->baseUrl = getDolGlobalString('OPHELIA_API_URL', 'http://localhost:8000');
    }

    private function callApi(string $endpoint, array $data = [], string $method = 'POST'): array
    {
        $url = $this->baseUrl . '/api/v1/' . $endpoint;
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        ]);
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($httpCode !== 200) {
            throw new Exception("Ophelia API: HTTP $httpCode");
        }
        return json_decode($response, true);
    }

    public function checkHealth(): array {
        return $this->callApi('health', [], 'GET');
    }
    public function extractOcr(string $filepath, string $lang = 'fra'): array {
        return $this->callApi('ocr/extract', ['filepath' => $filepath, 'lang' => $lang]);
    }
    public function startProcessing(string $filepath, array $templates): string {
        $r = $this->callApi('extraction/process/start', ['filepath' => $filepath, 'templates' => $templates, 'lang' => 'fra']);
        return $r['task_id'];
    }
    public function getStatus(string $taskId): array {
        return $this->callApi('extraction/process/status/' . $taskId, [], 'GET');
    }
    public function processSync(string $filepath, array $templates): array {
        return $this->callApi('extraction/process/sync', ['filepath' => $filepath, 'templates' => $templates, 'lang' => 'fra']);
    }
}
```

## Flux principal

### Upload (document_card.php)
1. Upload fichier → valider format + taille
2. Enregistrer dans llx_ophelia_document (status=0)
3. Stocker fichier via systeme Dolibarr

### Traitement (document_card.php — bouton Lancer)
1. Charger templates actifs depuis BDD
2. Formater en JSON selon TemplateSchema de ophelia-service
3. Appeler POST /api/v1/extraction/process/start (ou /process/sync si pas de Redis)
4. Stocker task_id, passer status=1
5. JS polling toutes les 3s sur /process/status/{task_id}
6. Quand completed : inserer dans extraction_result + extraction_field, status=2

### Validation (extraction_validate.php)
1. Afficher champs tries par confiance croissante
2. Corrections → corrected_value
3. Validation → extraction_result.status=1, document.status=3

### Export (export.php)
1. Verifier resultat valide
2. Generer JSON/XML cote PHP (pas besoin d'appeler l'API pour ca)
3. Enregistrer dans export_history
4. Telecharger

## Conventions Dolibarr

- Descripteur dans modOphelia.class.php (droits, menus, tables)
- Classes DAO heritent de CommonObject
- Pages commencent par require '../../main.inc.php'
- Droits verifies par restrictedArea()
- Entrees securisees par GETPOST()
- Requetes par $this->db->escape()
- Traductions par $langs->trans('CleOphelia')

## Ordre de construction

Phase 1 : modOphelia.class.php + SQL + ophelia.lib.php + admin/setup.php + ophelia.lang → activer le module
Phase 2 : document.class.php + document_card.php + document_list.php + api_ophelia.class.php
Phase 3 : template.class.php + templatefield.class.php + template_card/list/fields.php
Phase 4 : extractionresult/field.class.php + bouton traitement + ophelia.js polling + extraction_result/validate.php
Phase 5 : export.php + exporthistory.class.php + ophelia.css + tests integration

## Ajustements ophelia-service

Si PHP a besoin d'un format different ou d'un endpoint supplementaire, modifier les fichiers dans ../ophelia-service/app/. Points cles :
- filepath envoye par PHP = chemin Windows absolu accessible par Python
- templates envoyes par PHP doivent respecter TemplateSchema (voir ophelia-service/app/models/schemas.py)
- Si Redis pas lance, utiliser /process/sync comme fallback
- Ne jamais dupliquer la logique d'extraction cote PHP

## Ce qu'il ne faut PAS faire

- Ne pas modifier le noyau Dolibarr (rien hors de htdocs/custom/ophelia/)
- Ne pas creer d'auth propre (utiliser celle de Dolibarr)
- Ne pas hardcoder l'URL du service Python
- Ne pas dupliquer l'extraction cote PHP
