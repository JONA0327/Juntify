#!/usr/bin/env python
# -*- coding: utf-8 -*-
"""
Script de prueba para verificar la identificación de speakers
"""
import subprocess
import json
import os
import sys
import pymysql
from dotenv import load_dotenv

load_dotenv()

# Conectar a la base de datos
conn = pymysql.connect(
    host=os.getenv('DB_HOST', 'localhost'),
    user=os.getenv('DB_USERNAME', 'root'),
    password=os.getenv('DB_PASSWORD', ''),
    database=os.getenv('DB_DATABASE', 'juntify')
)

cursor = conn.cursor()

# Obtener el embedding del usuario
user_id = '5b2161d8-eae9-4fdc-8ab6-992fa7a4bbdc'

# Primero verificar las columnas disponibles
cursor.execute("DESCRIBE users")
columns = cursor.fetchall()
print("Columnas disponibles en la tabla users:")
for col in columns:
    print(f"  - {col[0]}")
print()

# Buscar la columna correcta de nombre
name_column = None
for col in columns:
    if 'nom' in col[0].lower():
        name_column = col[0]
        break

if not name_column:
    name_column = 'email'  # fallback

cursor.execute(f"SELECT {name_column}, voice_embedding FROM users WHERE id = %s", (user_id,))
result = cursor.fetchone()

if not result or not result[1]:
    print(f"No se encontró embedding para el usuario {user_id}")
    sys.exit(1)

user_name = result[0]
embedding = json.loads(result[1])

print(f"Usuario: {user_name}")
print(f"Embedding: {len(embedding)} dimensiones")
print(f"Primeros 5 valores: {embedding[:5]}")
print(f"Mean: {sum(embedding)/len(embedding):.6f}")
print(f"Std: {(sum([(x - sum(embedding)/len(embedding))**2 for x in embedding])/len(embedding))**0.5:.6f}")
print()

cursor.close()
conn.close()

# Preparar los datos como lo hace Laravel
segments = [
    {"speaker": "A", "start": 0.64, "end": 75.06},
    {"speaker": "B", "start": 75.06, "end": 132.42}
]

user_embeddings = {
    user_id: embedding
}

input_data = json.dumps({
    "segments": segments,
    "user_embeddings": user_embeddings
})

# Ejecutar el script
process = subprocess.run(
    [
        'C:/Proyectos/Juntify/.venv/Scripts/python.exe',
        'tools/identify_speakers.py',
        'storage/app/temp-transcription/7b84a71d-7acb-40b1-adf1-9ba4d6149766.ogg',
        input_data
    ],
    capture_output=True,
    text=True,
    cwd=os.getcwd(),
    env={
        **os.environ,
        'FFMPEG_BIN': 'C:/ProgramData/chocolatey/bin/ffmpeg.exe',
        'PYTHONIOENCODING': 'utf-8',
        'LIBROSA_CACHE_DIR': '',
        'LIBROSA_CACHE_LEVEL': '0',
        'JOBLIB_START_METHOD': 'loky'
    }
)

print("=" * 60)
print("RESULTADOS DE IDENTIFICACIÓN")
print("=" * 60)

if process.stderr:
    print("\nDebug info (stderr):")
    print(process.stderr)

if process.returncode == 0 and process.stdout:
    try:
        results = json.loads(process.stdout.strip())
        print("\nResultados parseados:")
        for result in results:
            print(f"\n  Speaker {result['speaker']} ({result['start']:.2f}s - {result['end']:.2f}s):")
            if result.get('error'):
                print(f"    ERROR: {result['error']}")
            elif result['matched_user_id']:
                print(f"    ✓ IDENTIFICADO como: {user_name}")
                print(f"    Confianza: {result['confidence']:.4f} ({result['confidence']*100:.1f}%)")
            else:
                print(f"    × NO identificado")
                print(f"    Mejor similitud: {result['confidence']:.4f} (umbral: 0.80)")
    except Exception as e:
        print(f"\nError al parsear resultados: {e}")
        print(f"Output: {process.stdout}")
else:
    print(f"\nError en el script (exit code: {process.returncode})")
    print(process.stdout)
