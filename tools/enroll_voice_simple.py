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
        # MFCCs - 13 coeficientes estándar para reconocimiento de voz
        mfccs = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=13, n_fft=2048, hop_length=512)
        mfcc_mean = np.mean(mfccs, axis=1)
        mfcc_std = np.std(mfccs, axis=1)
        
        # Delta y Delta-Delta MFCCs (cambios temporales)
        mfcc_delta = librosa.feature.delta(mfccs)
        mfcc_delta2 = librosa.feature.delta(mfccs, order=2)
        mfcc_delta_mean = np.mean(mfcc_delta, axis=1)
        mfcc_delta2_mean = np.mean(mfcc_delta2, axis=1)
        
        # Características espectrales (timbre)
        spectral_centroid = librosa.feature.spectral_centroid(y=y, sr=sr)
        spectral_rolloff = librosa.feature.spectral_rolloff(y=y, sr=sr)
        spectral_bandwidth = librosa.feature.spectral_bandwidth(y=y, sr=sr)
        spectral_contrast = librosa.feature.spectral_contrast(y=y, sr=sr, n_bands=6)
        
        spectral_centroid_mean = np.mean(spectral_centroid)
        spectral_centroid_std = np.std(spectral_centroid)
        spectral_rolloff_mean = np.mean(spectral_rolloff)
        spectral_bandwidth_mean = np.mean(spectral_bandwidth)
        spectral_contrast_mean = np.mean(spectral_contrast, axis=1)
        
        zcr = librosa.feature.zero_crossing_rate(y)
        zcr_mean = np.mean(zcr)
        zcr_std = np.std(zcr)
        
        # Pitch robusto
        pitches, magnitudes = librosa.piptrack(y=y, sr=sr, fmin=75, fmax=400)
        pitch_values = []
        for t in range(pitches.shape[1]):
            index = magnitudes[:, t].argmax()
            pitch = pitches[index, t]
            if pitch > 0:
                pitch_values.append(pitch)
        
        if len(pitch_values) > 0:
            pitch_mean = np.mean(pitch_values)
            pitch_std = np.std(pitch_values)
            pitch_median = np.median(pitch_values)
        else:
            pitch_mean = 0
            pitch_std = 0
            pitch_median = 0
        
        # Energía RMS
        rms_energy = librosa.feature.rms(y=y)
        rms_mean = np.mean(rms_energy)
        rms_std = np.std(rms_energy)
        
        # Chroma (características tonales) - 6 dimensiones
        chroma = librosa.feature.chroma_stft(y=y, sr=sr)
        chroma_mean = np.mean(chroma, axis=1)[:6]  # Solo primeras 6 de 12
        
    except OSError as e:
        if 'WinError 10106' in str(e):
            print('Error de Windows: Problema con librerías de red. Intenta reiniciar el servidor PHP.', file=sys.stderr)
        else:
            print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    except Exception as e:
        print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    
    # Combinar todas las características (76 dimensiones)
    embedding = np.concatenate([
        mfcc_mean,                      # 13
        mfcc_std,                       # 13
        mfcc_delta_mean,                # 13
        mfcc_delta2_mean,               # 13
        spectral_contrast_mean,         # 7
        chroma_mean,                    # 6
        [spectral_centroid_mean],       # 1
        [spectral_centroid_std],        # 1
        [spectral_rolloff_mean],        # 1
        [spectral_bandwidth_mean],      # 1
        [zcr_mean],                     # 1
        [zcr_std],                      # 1
        [pitch_mean],                   # 1
        [pitch_std],                    # 1
        [pitch_median],                 # 1
        [rms_mean],                     # 1
        [rms_std],                      # 1
    ])
    
    # Normalizar el embedding (media 0, desviación estándar 1)
    mean = np.mean(embedding)
    std = np.std(embedding)
    if std > 0:
        embedding = (embedding - mean) / std
    
    # Total: 76 características que representan la voz única de la persona
    
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
