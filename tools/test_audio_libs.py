# -*- coding: utf-8 -*-
"""
Script de diagnóstico para identificar el problema de bloqueo
"""
import sys
import os

print("=== DIAGNÓSTICO DE BLOQUEO ===", file=sys.stderr)

# Test 1: Importaciones básicas
print("\n1. Probando importaciones básicas...", file=sys.stderr)
try:
    import numpy
    print("   ✓ numpy OK", file=sys.stderr)
except Exception as e:
    print(f"   ✗ numpy FALLO: {e}", file=sys.stderr)
    sys.exit(1)

try:
    import scipy
    print("   ✓ scipy OK", file=sys.stderr)
except Exception as e:
    print(f"   ✗ scipy FALLO: {e}", file=sys.stderr)
    sys.exit(1)

try:
    from scipy.io import wavfile
    print("   ✓ scipy.io.wavfile OK", file=sys.stderr)
except Exception as e:
    print(f"   ✗ scipy.io.wavfile FALLO: {e}", file=sys.stderr)
    sys.exit(1)

# Test 2: Librosa (el más problemático)
print("\n2. Probando librosa...", file=sys.stderr)
try:
    import librosa
    print("   ✓ librosa OK", file=sys.stderr)
except Exception as e:
    print(f"   ✗ librosa FALLO: {e}", file=sys.stderr)
    print(f"\n   ERROR ENCONTRADO: El bloqueo ocurre al importar librosa", file=sys.stderr)
    print(f"   Detalles: {type(e).__name__}: {e}", file=sys.stderr)
    sys.exit(1)

# Test 3: Subprocess con ffmpeg
print("\n3. Probando subprocess con ffmpeg...", file=sys.stderr)
try:
    import subprocess
    import tempfile
    
    # Crear archivo temporal de prueba
    temp_fd, temp_wav = tempfile.mkstemp(suffix='.wav')
    os.close(temp_fd)
    
    # Usar ffmpeg portable del proyecto primero
    project_ffmpeg = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'ffmpeg-portable', 'bin', 'ffmpeg.exe')
    
    if os.path.exists(project_ffmpeg):
        ffmpeg_path = project_ffmpeg
        print(f"   Usando ffmpeg portable: {ffmpeg_path}", file=sys.stderr)
    else:
        ffmpeg_path = os.environ.get('FFMPEG_BIN', 'ffmpeg')
        if ffmpeg_path.startswith('"') and ffmpeg_path.endswith('"'):
            ffmpeg_path = ffmpeg_path[1:-1]
        print(f"   Usando ffmpeg del sistema: {ffmpeg_path}", file=sys.stderr)
    
    # Flags para evitar bloqueo
    creation_flags = 0x08000000 if sys.platform == 'win32' else 0
    
    result = subprocess.run(
        [ffmpeg_path, '-version'],
        capture_output=True,
        text=True,
        timeout=5,
        creationflags=creation_flags
    )
    
    if result.returncode == 0:
        print("   ✓ ffmpeg OK", file=sys.stderr)
        print(f"   Version: {result.stdout.split('\\n')[0]}", file=sys.stderr)
    else:
        print(f"   ✗ ffmpeg retornó código {result.returncode}", file=sys.stderr)
    
    # Limpiar
    if os.path.exists(temp_wav):
        os.unlink(temp_wav)
        
except Exception as e:
    print(f"   ✗ subprocess/ffmpeg FALLO: {e}", file=sys.stderr)
    import traceback
    traceback.print_exc(file=sys.stderr)
    sys.exit(1)

print("\n=== TODOS LOS TESTS PASARON ===", file=sys.stderr)
print("El error debe estar en otra parte del código", file=sys.stderr)
