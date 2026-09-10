# Ophélia Service

Microservice Python d'extraction documentaire pour le module Ophélia (Dolibarr ERP).

## Prérequis

- Python 3.11.9
- Tesseract OCR 5.3+ (installé et dans le PATH)
- Redis — via Docker Desktop (recommandé) ou Memurai sous Windows

## Installation (Windows / PowerShell)

```powershell
# Créer l'environnement virtuel
python -m venv venv
.\venv\Scripts\activate

# Installer les dépendances
pip install -r requirements.txt

# Copier et éditer la configuration
copy .env.example .env
# Modifier .env selon votre installation

# Convertir le modèle LayoutLMv3 en ONNX (une seule fois)
python scripts/convert_to_onnx.py
```

## Lancement

```powershell
# Terminal 1 — Redis (conteneur Docker persistant, créé une seule fois)
# Nécessite Docker Desktop démarré.
docker run -d --name ophelia-redis -p 6379:6379 --restart unless-stopped redis:7-alpine
# Les lancements suivants (Docker Desktop déjà ouvert) :
docker start ophelia-redis
# Alternative sans Docker : memurai-server

# Terminal 2 — API FastAPI
.\venv\Scripts\activate
uvicorn app.main:app --host 0.0.0.0 --port 8000 --reload

# Terminal 3 — Worker Celery
.\venv\Scripts\activate
celery -A app.tasks.celery_tasks worker --pool=solo --loglevel=info
```

Note Windows : Celery utilise le pool de processus `prefork` (fork()) par défaut,
indisponible sous Windows — `--pool=solo` est obligatoire.

## Vérification

```
GET http://localhost:8000/api/v1/health
GET http://localhost:8000/docs      (documentation OpenAPI interactive)
```

## Architecture

```
app/
  api/          Routes FastAPI (OCR, matching, extraction)
  services/     Logique métier (OCR, appariement, extraction, confiance)
  models/       Modèles Pydantic et structures de données internes
  ml/           Chargement et inférence LayoutLMv3 (ONNX)
  tasks/        Tâches Celery (traitement asynchrone)
  utils/        Fonctions utilitaires (géométrie, images, XML)
```
