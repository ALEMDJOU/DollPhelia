# Ophélia — Microservice Python (FastAPI)

## Contexte

Ce projet est le **microservice d'extraction documentaire** du module Ophélia, un module pour l'ERP Dolibarr. Il fait partie d'un mémoire de master (ENSPY, Yaoundé, Cameroun).

Le microservice reçoit des documents (images, PDF) depuis le module PHP Dolibarr, les traite (OCR, appariement de templates, extraction IA) et retourne les données extraites.

## Environnement

- **OS** : Windows 10/11
- **Python** : 3.11.9
- **RAM** : 8 Go, **pas de GPU** — toute inférence est CPU
- **IDE** : VS Code avec Claude Code
- **Modèle IA** : LayoutLMv3 fine-tuné sur FUNSD (nielsr/layoutlmv3-finetuned-funsd)
  - Les 8 fichiers du modèle sont déjà téléchargés dans `models_cache/_download/`
  - La conversion ONNX reste à faire via `python scripts/convert_to_onnx.py`

## Stack (déjà installée dans le venv)

```
torch==2.3.1 (CPU)
fastapi==0.110.3
uvicorn==0.29.0
pytesseract==0.3.10
Pillow==10.3.0
opencv-python-headless==4.9.0.80
onnxruntime==1.18.1
transformers==4.40.2
optimum[onnxruntime]==1.19.2
celery==5.3.6
redis==5.0.4
pydantic==2.7.1
numpy==1.26.4
lxml==5.2.2
```

## Architecture du projet

```
ophelia-service/
├── app/
│   ├── main.py                    # FastAPI entry point + lifespan (charge le modèle au démarrage)
│   ├── config.py                  # Settings via pydantic-settings + .env
│   ├── api/
│   │   ├── routes_health.py       # GET /api/v1/health
│   │   ├── routes_ocr.py          # POST /api/v1/ocr/extract
│   │   ├── routes_matching.py     # POST /api/v1/matching/match
│   │   ├── routes_extraction.py   # POST /api/v1/extraction/extract, /process/start, /process/status, /process/sync
│   │   └── routes_export.py       # POST /api/v1/export/json, /api/v1/export/xml
│   ├── services/
│   │   ├── preprocessing.py       # Binarisation, redressement, débruitage (OpenCV)
│   │   ├── ocr_service.py         # Tesseract → triplets (texte, bbox, confiance)
│   │   ├── matching_service.py    # Algorithme 1 du mémoire — score F(D,Tj) combinaison convexe
│   │   ├── extraction_service.py  # Algorithme 2 du mémoire — extraction ancrée par vecteur spatial
│   │   ├── ai_extraction_service.py # Repli LayoutLMv3 — appariement QUESTION→ANSWER (FUNSD)
│   │   ├── confidence_service.py  # C_total = C_OCR × P_spatial × C_validation
│   │   ├── orchestrator.py        # Pipeline complet : OCR → matching → stratégie → extraction
│   │   └── export_service.py      # Génération JSON/XML
│   ├── models/
│   │   ├── schemas.py             # Schémas Pydantic (requêtes/réponses API)
│   │   ├── document.py            # Document, Page, TextElement, BoundingBox
│   │   ├── template.py            # Template, TemplateField
│   │   └── spatial.py             # SpatialVector, RelationType
│   ├── ml/
│   │   ├── layoutlm_loader.py     # Chargement ONNX singleton au démarrage
│   │   └── layoutlm_inference.py  # Inférence LayoutLMv3 sur éléments OCR
│   ├── tasks/
│   │   └── celery_tasks.py        # Tâche async : process_document_task
│   └── utils/
│       ├── bbox_utils.py          # Géométrie : centres, distances, recherche de clés, erreur spatiale
│       ├── image_utils.py         # Resize, conversion PIL↔OpenCV
│       └── xml_builder.py         # Construction XML via lxml
├── scripts/
│   └── convert_to_onnx.py        # Conversion locale safetensors → ONNX
├── models_cache/
│   └── _download/                 # 8 fichiers HuggingFace déjà téléchargés
├── tests/
├── requirements.txt
├── .env.example
└── README.md
```

## Formalisme mathématique (Chapitre 4 du mémoire)

Le code implémente directement ces formules. Ne pas les modifier sans raison.

### Score de correspondance F(D, Tj) — Définition 4.6

```
F(D, Tj) = w1·S1 + w2·S2 + w3·S3 + w4·S4    avec Σwi = 1
```

- S1 = indicatrice concordance de type (0 ou 1)
- S2 = proportion des clés du template retrouvées dans le document
- S3 = exp(-erreur_spatiale² / 2σ²)
- S4 = confiance OCR moyenne

Implémenté dans `matching_service.py`.

### Extraction ancrée — Algorithme 2

1. Localiser la clé (ancre textuelle) dans le document
2. Appliquer le vecteur spatial Skv pour calculer la position attendue
3. Chercher les éléments dans la zone de tolérance (3σ)
4. Sélectionner le plus proche
5. Calculer C_total

Implémenté dans `extraction_service.py`.

### Confiance totale — Définition 4.11

```
C_total(f, v) = C_OCR(v) × P_spatial(f, v) × C_validation(f, v)
```

- C_OCR : confiance Tesseract normalisée [0,1]
- P_spatial : exp(-erreur_distance² / 2σ²)
- C_validation : validation regex par type (date, montant, IBAN, etc.)

Implémenté dans `confidence_service.py`.

### Paramètres par défaut (Tableau 4.1)

| Paramètre | Symbole | Valeur |
|-----------|---------|--------|
| Poids type | w1 | 0.25 |
| Poids clés | w2 | 0.25 |
| Poids spatial | w3 | 0.25 |
| Poids OCR | w4 | 0.25 |
| Tolérance spatiale | σ_pos | 10.0 px |
| Pas d'adaptation | β | 0.1 |
| Seuil minimal | θ_min | 0.7 |

Configurés dans `config.py` et surchargeables via `.env`.

## Endpoints API

| Méthode | Route | Rôle |
|---------|-------|------|
| GET | /api/v1/health | Santé du service + statut modèle |
| POST | /api/v1/ocr/extract | OCR → éléments localisés |
| POST | /api/v1/ocr/extract-upload | Idem avec upload direct |
| POST | /api/v1/matching/match | Appariement document ↔ template |
| POST | /api/v1/extraction/extract | Extraction de champs (template ou IA) |
| POST | /api/v1/extraction/process/start | Traitement complet async (Celery) |
| GET | /api/v1/extraction/process/status/{id} | Polling statut tâche |
| POST | /api/v1/extraction/process/sync | Traitement complet synchrone (debug) |
| POST | /api/v1/export/json | Export résultat en JSON |
| POST | /api/v1/export/xml | Export résultat en XML |
| POST | /api/v1/export/preview/json | Aperçu JSON sans téléchargement |
| POST | /api/v1/export/preview/xml | Aperçu XML sans téléchargement |

## Prochaines étapes (par priorité)

1. **Convertir le modèle ONNX** : `python scripts/convert_to_onnx.py` — les fichiers sont dans `models_cache/_download/`
2. **Lancer et tester le service** : `uvicorn app.main:app --port 8000 --reload` puis tester chaque endpoint via `/docs`
3. **Corriger les bugs** éventuels dans le pipeline OCR → matching → extraction
4. **Écrire les tests** dans `tests/` pour chaque service
5. **Tester avec de vrais documents** (factures, CV, KBIS, RIB) pour valider le pipeline complet

## Conventions

- Pas d'emojis dans le code ou les commentaires
- Commentaires en français
- Docstrings en français
- Les références au mémoire (Définition X, Algorithme Y) sont à conserver dans les docstrings
- Préférer les modifications ciblées (fichier par fichier) aux régénérations complètes
- Tester avant de valider toute modification
- Le service doit démarrer et répondre sur /health même si le modèle ONNX n'est pas encore converti

## Fichier .env attendu

```
TESSERACT_CMD=C:\Program Files\Tesseract-OCR\tesseract.exe
TESSERACT_LANG=fra
MODEL_PATH=./models_cache/layoutlmv3
MODEL_DEVICE=cpu
MATCHING_THRESHOLD=0.7
WEIGHT_TYPE=0.25
WEIGHT_KEYS=0.25
WEIGHT_SPATIAL=0.25
WEIGHT_OCR=0.25
SPATIAL_TOLERANCE=10.0
ADAPTATION_STEP=0.1
REDIS_URL=redis://localhost:6379/0
API_HOST=0.0.0.0
API_PORT=8000
DOLIBARR_DOCUMENTS_PATH=C:\wamp64\www\dolibarr\documents
```
