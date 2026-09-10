<div align="center">

<!-- [Remplace par ton logo ou une image de couverture, ex: docs/img/logo.png] -->
<img src="[LIEN_VERS_TON_LOGO_OU_BANNIERE]" alt="Ophélia" width="480"/>

# Ophélia

**Arrêtez de ressaisir vos factures à la main. Laissez Ophélia les lire pour vous.**

[![GitHub stars](https://img.shields.io/github/stars/[TON_USER]/[TON_REPO]?style=social)](https://github.com/[TON_USER]/[TON_REPO]/stargazers)
[![License](https://img.shields.io/github/license/[TON_USER]/[TON_REPO])](LICENSE)
[![Dolibarr](https://img.shields.io/badge/Dolibarr-22.x-6c2eb5)](https://www.dolibarr.org/)
[![Python](https://img.shields.io/badge/Python-3.11-3776AB?logo=python&logoColor=white)](ophelia-service/)
[![PRs Welcome](https://img.shields.io/badge/PRs-welcome-brightgreen.svg)](#-contribuer)

Module d'extraction automatique de documents pour [Dolibarr ERP/CRM](https://www.dolibarr.org/), propulsé par un microservice Python (OCR + IA).

<!-- [Remplace par un GIF de démo : upload -> traitement -> validation -> export] -->
<img src="[LIEN_VERS_TON_GIF_DE_DEMO]" alt="Démo Ophélia" width="720"/>

[Démo](#-quick-start) · [Fonctionnalités](#-fonctionnalités) · [Installation](#-quick-start) · [Contribuer](#-contribuer) · [Signaler un bug]([LIEN_ISSUES])

</div>

---

## Pourquoi Ophélia ?

Ressaisir à la main les données d'une facture, d'un RIB ou d'un KBIS dans son ERP, c'est lent, répétitif et source d'erreurs. Ophélia règle ce problème directement dans Dolibarr :

- 🎯 **Zéro ressaisie manuelle** — le document est lu, apparié à un template et ses champs sont extraits automatiquement.
- 🧠 **Double stratégie d'extraction** — ancrage spatial déterministe (rapide, explicable) avec repli sur un modèle IA (LayoutLMv3) quand aucun template ne correspond.
- ✅ **L'humain garde la main** — chaque champ extrait affiche son score de confiance ; rien n'est validé sans relecture.
- 🔌 **Nativement intégré à Dolibarr** — pas d'outil externe à jongler, tout se passe dans l'ERP que vos équipes utilisent déjà.
- 🏠 **100% auto-hébergé** — OCR (Tesseract) et modèle IA tournent en local, aucune donnée ne part vers un tiers.

## ✨ Fonctionnalités

- 📤 **Upload de documents** directement depuis Dolibarr (factures, CV, KBIS, RIB, ...)
- 🧩 **Templates configurables** — définissez des ancres textuelles et des vecteurs spatiaux par type de document
- 🔍 **OCR + appariement automatique** contre vos templates actifs, avec score de correspondance
- 🤖 **Extraction spatiale ou IA (LayoutLMv3)** selon ce qui matche le mieux le document
- 📊 **Scores de confiance par champ** (OCR × spatial × validation) pour prioriser la relecture
- ✏️ **Interface de validation/correction** triée par confiance croissante
- 👁️ **Aperçu avant export** en JSON ou XML
- 🗂️ **Historique des exports** et traçabilité complète (qui, quand, quel format)
- ⚙️ **Traitement asynchrone** (Celery/Redis) avec repli automatique en mode synchrone

## 🏗️ Architecture

```
DollPhelia/
├── DolibarrFofack/htdocs/custom/ophelia/   # Module Dolibarr (PHP) — UI, BDD, orchestration
└── ophelia-service/                        # Microservice Python (FastAPI) — OCR, matching, extraction IA
```

Le module PHP ne fait **aucun traitement d'image ou d'OCR lui-même** : il pilote l'upload, la persistance et la validation, et délègue tout le calcul au microservice via une API REST.

## 🚀 Quick Start

```bash
# 1. Microservice Python (OCR + IA)
cd ophelia-service && pip install -r requirements.txt && uvicorn app.main:app --port 8000

# 2. Copiez le module dans votre instance Dolibarr puis activez-le
cp -r DolibarrFofack/htdocs/custom/ophelia [VOTRE_DOLIBARR]/htdocs/custom/
# -> Configuration > Modules > Ophélia > Activer
```

> Prérequis : PHP 8.x + MariaDB (Dolibarr 19+), Python 3.11, [Tesseract OCR](https://github.com/tesseract-ocr/tesseract). Redis/Celery sont optionnels — sans eux, le module bascule automatiquement en traitement synchrone.

## 📖 Utilisation

1. **Créez un template** (`Ophélia > Templates > Nouveau`) : définissez le type de document et ses champs (ex. `total_ttc`, ancré sur le texte `"Total TTC"`).
2. **Téléversez un document** (`Ophélia > Documents > Nouveau document`).
3. Cliquez sur **Lancer le traitement** : Ophélia OCRise le document, l'apparie au meilleur template puis extrait les champs.
4. **Validez ou corrigez** les valeurs extraites, triées par confiance croissante.
5. **Exportez** en JSON ou XML (avec aperçu avant téléchargement).

```php
// Exemple : appeler le microservice depuis votre propre code Dolibarr
$api = new ApiOphelia();
$result = $api->processSync($filepath, $templatesSchema, 'eng');
```

## ⭐ Soutenez le projet

Si Ophélia vous fait gagner du temps ou vous a inspiré, **laissez une étoile ⭐** en haut de cette page !

Ce n'est pas juste un geste sympa : chaque étoile aide le projet à être découvert par d'autres développeurs Dolibarr qui pourraient en avoir besoin, et ça motive énormément la suite du développement. Merci 🙏

## 🤝 Contribuer

Les contributions sont les bienvenues, petites ou grandes !

1. Forkez le repo
2. Créez votre branche (`git checkout -b feature/ma-fonctionnalite`)
3. Commitez vos changements
4. Ouvrez une Pull Request

Consultez [CONTRIBUTING.md]([LIEN_CONTRIBUTING]) pour les détails (à créer si absent).

## 🗺️ Roadmap

- [ ] Génération PDF des résultats validés
- [ ] Tableau de bord des scores de confiance par type de document
- [ ] Apprentissage actif : réutiliser les corrections humaines pour affiner l'appariement
- [ ] Support multi-pages avancé (documents composites)
- [ ] Connecteurs d'export vers des modules Dolibarr tiers (factures fournisseurs, notes de frais...)

*Une idée à ajouter ? [Ouvrez une issue]([LIEN_ISSUES]).*

## 📄 Licence

Distribué sous licence [[TON_CHOIX_DE_LICENCE], ex: GPL-3.0]. Voir [`LICENSE`](LICENSE) pour plus de détails.

---

<div align="center">

Construit avec ❤️ autour de [Dolibarr](https://www.dolibarr.org/) — projet initialement né dans le cadre d'un mémoire de master (ENSPY, Yaoundé, Cameroun).

</div>
