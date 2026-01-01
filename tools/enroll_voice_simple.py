# -*- coding: utf-8 -*-
"""
Script simplificado para registro de voz usando spectral features
No requiere compilación de C++ ni librerías pesadas
"""
import json
import sys
import os
import subprocess
import tempfile
import warnings

# CRITICAL: Parchear asyncio ANTES de que cualquier módulo lo importe
# Fix para WinError 10106 en Python de Microsoft Store
if sys.platform == 'win32':
    # Monkey patch para _overlapped antes de que asyncio lo cargue
    import importlib.util
    import types
    
    # Crear un módulo dummy para _overlapped
    _overlapped_module = types.ModuleType('_overlapped')
    sys.modules['_overlapped'] = _overlapped_module
    
# Configurar joblib para usar loky y deshabilitar caché
os.environ['JOBLIB_START_METHOD'] = 'loky'
os.environ['LIBROSA_CACHE_DIR'] = ''
os.environ['LIBROSA_CACHE_LEVEL'] = '0'

try:
    import librosa
    import numpy as np
    from scipy.io import wavfile
except ImportError as e:
    print(f"Error: Instala las dependencias con: pip install librosa numpy scipy loky ({e})", file=sys.stderr)
    sys.exit(1)

MIN_DURATION_SECONDS = 10
SILENCE_RMS_THRESHOLD = 0.005


def extract_voice_features(audio_path):
    """
    Extrae características espectrales del audio para crear un embedding simple
    """
    warnings.filterwarnings('ignore')
    
    # Convertir audio a WAV usando ffmpeg primero
    temp_wav = None
    try:
        # Crear archivo temporal WAV
        temp_fd, temp_wav = tempfile.mkstemp(suffix='.wav')
        os.close(temp_fd)
        
        # Obtener ruta de ffmpeg
        ffmpeg_path = os.environ.get('FFMPEG_BIN', 'ffmpeg')
        if ffmpeg_path.startswith('"') and ffmpeg_path.endswith('"'):
            ffmpeg_path = ffmpeg_path[1:-1]
        
        # Convertir con ffmpeg
        result = subprocess.run([
            ffmpeg_path,
            '-i', audio_path,
            '-ar', '16000',
            '-ac', '1',
            '-f', 'wav',
            '-y',
            temp_wav
        ], capture_output=True, text=True, timeout=30)
        
        if result.returncode != 0:
            print(f'Error en ffmpeg: {result.stderr}', file=sys.stderr)
            return None
        
        # Cargar el WAV convertido con scipy (más robusto en Windows)
        sr, y = wavfile.read(temp_wav)
        
        # Convertir a float y normalizar
        if y.dtype == np.int16:
            y = y.astype(np.float32) / 32768.0
        elif y.dtype == np.int32:
            y = y.astype(np.float32) / 2147483648.0
        else:
            y = y.astype(np.float32)
        
        # Asegurar que es mono
        if len(y.shape) > 1:
            y = np.mean(y, axis=1)
        
    except subprocess.TimeoutExpired:
        print('Timeout al convertir audio con ffmpeg', file=sys.stderr)
        return None
    except FileNotFoundError:
        print(f'No se encontró ffmpeg en: {os.environ.get("FFMPEG_BIN", "PATH")}', file=sys.stderr)
        return None
    except Exception as e:
        print(f'Error al procesar audio: {e}', file=sys.stderr)
        return None
    finally:
        # Limpiar archivo temporal
        if temp_wav and os.path.exists(temp_wav):
            try:
                os.unlink(temp_wav)
            except:
                pass

    # Validar duración (calcular manualmente sin librosa.get_duration)
    duration = len(y) / float(sr)
    if duration < MIN_DURATION_SECONDS:
        print(f'El audio es demasiado corto ({duration:.1f}s). Graba al menos {MIN_DURATION_SECONDS} segundos.', file=sys.stderr)
        return None

    # Detectar silencio (calcular RMS manualmente)
    rms = np.sqrt(np.mean(y**2))
    if rms < SILENCE_RMS_THRESHOLD:
        print('El audio está en silencio o el volumen es demasiado bajo.', file=sys.stderr)
        return None

    # Extraer características espectrales usando solo numpy y funciones básicas
    # Estas características capturan el "timbre" único de la voz
    
    try:
        # 1. MFCCs (Mel-frequency cepstral coefficients) - 20 coeficientes
        mfccs = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=20)
        mfcc_mean = np.mean(mfccs, axis=1)
        mfcc_std = np.std(mfccs, axis=1)
        
        # 2. Espectro de frecuencias
        spectral_centroid = np.mean(librosa.feature.spectral_centroid(y=y, sr=sr))
        spectral_rolloff = np.mean(librosa.feature.spectral_rolloff(y=y, sr=sr))
        spectral_bandwidth = np.mean(librosa.feature.spectral_bandwidth(y=y, sr=sr))
        
        # 3. Zero Crossing Rate (características del timbre)
        zcr = np.mean(librosa.feature.zero_crossing_rate(y))
        
        # 4. Chroma features (contenido tonal)
        chroma = librosa.feature.chroma_stft(y=y, sr=sr)
        chroma_mean = np.mean(chroma, axis=1)
        
        # 5. Mel spectrogram
        mel_spec = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=40)
        mel_mean = np.mean(mel_spec, axis=1)
        
    except OSError as e:
        if 'WinError 10106' in str(e):
            print('Error de Windows: Problema con librerías de red. Intenta reiniciar el servidor PHP.', file=sys.stderr)
        else:
            print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    except Exception as e:
        print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    
    # Combinar todas las características en un vector de embedding
    embedding = np.concatenate([
        mfcc_mean,           # 20 valores
        mfcc_std,            # 20 valores
        [spectral_centroid], # 1 valor
        [spectral_rolloff],  # 1 valor
        [spectral_bandwidth],# 1 valor
        [zcr],              # 1 valor
        chroma_mean,        # 12 valores
        mel_mean[:20]       # 20 valores (primeros 20 mels)
    ])
    
    # Total: 96 características que representan la voz única de la persona
    
    return embedding.tolist()


def main():
    if len(sys.argv) < 2:
        print('Uso: python enroll_voice_simple.py <ruta_audio>', file=sys.stderr)
        return 1

    audio_path = sys.argv[1]
    
    if not os.path.exists(audio_path):
        print(f'El archivo no existe: {audio_path}', file=sys.stderr)
        return 1

    embedding = extract_voice_features(audio_path)
    
    if embedding is None:
        return 1

    # Imprimir el embedding como JSON en UTF-8
    output = json.dumps(embedding, ensure_ascii=False)
    # Forzar salida en UTF-8
    sys.stdout.buffer.write(output.encode('utf-8'))
    sys.stdout.buffer.write(b'\n')
    sys.stdout.buffer.flush()
    return 0


if __name__ == '__main__':
    sys.exit(main())
