#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Test: Extraer embedding del Speaker B y compararlo consigo mismo
"""
import sys
import os
sys.path.insert(0, 'tools')

from identify_speakers import extract_voice_features_from_segment, cosine_similarity

# Extraer embedding del Speaker B (tú hablando)
audio_path = 'storage/app/temp-transcription/7b84a71d-7acb-40b1-adf1-9ba4d6149766.ogg'
os.environ['FFMPEG_BIN'] = 'C:/ProgramData/chocolatey/bin/ffmpeg.exe'

print("Extrayendo características del Speaker B (tu voz)...")
embedding_b = extract_voice_features_from_segment(audio_path, 75.06, 132.42)

if embedding_b:
    print(f"✓ Embedding extraído: {len(embedding_b)} dimensiones")
    print(f"  Primeros 5 valores: {embedding_b[:5]}")
    
    # Comparar consigo mismo (debería ser ~1.0)
    similarity_self = cosine_similarity(embedding_b, embedding_b)
    print(f"\n✓ Similitud consigo mismo: {similarity_self:.4f}")
    
    # Ahora extraer embedding del Speaker A (Luis)
    print("\nExtrayendo características del Speaker A (Luis)...")
    embedding_a = extract_voice_features_from_segment(audio_path, 0.64, 75.06)
    
    if embedding_a:
        print(f"✓ Embedding extraído: {len(embedding_a)} dimensiones")
        print(f"  Primeros 5 valores: {embedding_a[:5]}")
        
        # Comparar A vs B (debería ser bajo, son diferentes personas)
        similarity_ab = cosine_similarity(embedding_a, embedding_b)
        print(f"\n✓ Similitud A vs B: {similarity_ab:.4f}")
        print(f"  (Esperado: bajo, son diferentes voces)")
    else:
        print("✗ No se pudo extraer embedding del Speaker A")
else:
    print("✗ No se pudo extraer embedding del Speaker B")

print("\n" + "="*60)
print("CONCLUSIÓN:")
print("="*60)
print("Si la similitud consigo mismo es cercana a 1.0, el algoritmo")
print("funciona correctamente. Si A vs B es bajo (<0.5), el sistema")
print("distingue correctamente entre las dos voces.")
print("\nPara que el sistema te identifique, debes RE-ENROLLAR tu voz")
print("con el algoritmo actual (ve a tu perfil y graba nueva huella).")
