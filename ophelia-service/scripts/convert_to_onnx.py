"""
Conversion locale LayoutLMv3 -> ONNX.
Les 8 fichiers sont deja dans models_cache/_download/.

Usage : python scripts/convert_to_onnx.py
"""

import json
import shutil
from pathlib import Path

LOCAL_DIR = Path("./models_cache/_download")
SAVE_DIR = Path("./models_cache/layoutlmv3")


def main():
    print("Verification des fichiers...")
    if not (LOCAL_DIR / "model.safetensors").exists():
        print(f"ERREUR : model.safetensors introuvable dans {LOCAL_DIR}")
        return
    if not (LOCAL_DIR / "config.json").exists():
        print(f"ERREUR : config.json introuvable dans {LOCAL_DIR}")
        return

    size_mb = (LOCAL_DIR / "model.safetensors").stat().st_size / 1024 / 1024
    print(f"model.safetensors : {size_mb:.1f} Mo — OK")

    from transformers import AutoModelForTokenClassification, AutoProcessor
    from optimum.onnxruntime import ORTModelForTokenClassification

    SAVE_DIR.mkdir(parents=True, exist_ok=True)
    staging = SAVE_DIR / "_staging"
    staging.mkdir(exist_ok=True)

    print("Chargement du modele...")
    pt_model = AutoModelForTokenClassification.from_pretrained(str(LOCAL_DIR))
    pt_model.save_pretrained(str(staging))

    print("Chargement du processeur...")
    processor = AutoProcessor.from_pretrained(str(LOCAL_DIR), apply_ocr=False)
    processor.save_pretrained(str(staging))

    print("Export ONNX (1-2 min sur CPU)...")
    ort_model = ORTModelForTokenClassification.from_pretrained(str(staging), export=True)
    ort_model.save_pretrained(str(SAVE_DIR))
    processor.save_pretrained(str(SAVE_DIR))

    shutil.rmtree(staging, ignore_errors=True)

    onnx_file = SAVE_DIR / "model.onnx"
    if onnx_file.exists():
        size = onnx_file.stat().st_size / 1024 / 1024
        config = json.loads((SAVE_DIR / "config.json").read_text())
        labels = list(config.get("id2label", {}).values())
        print(f"\nmodel.onnx : {size:.1f} Mo")
        print(f"Labels : {labels}")
        print(f"\nTermine. Modele pret dans {SAVE_DIR}")
    else:
        print("\nERREUR : model.onnx non genere.")


if __name__ == "__main__":
    main()