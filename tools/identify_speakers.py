# -*- coding: utf-8 -*-
"""
Script para identificar hablantes comparando embeddings de voz con segmentos de audio
"""
import json
import sys
import os
import subprocess
import tempfile
import warnings

# CRITICAL: Parchear asyncio ANTES de que cualquier módulo lo importe
if sys.platform == 'win32':
    import importlib.util
    import types
    _overlapped_module = types.ModuleType('_overlapped')
    sys.modules['_overlapped'] = _overlapped_module

# Configurar joblib y librosa
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


def extract_voice_features_from_segment(audio_path, start_time, end_time):
    """
    Extrae características de voz de un segmento específico del audio
    
    Args:
        audio_path: Ruta al archivo de audio
        start_time: Tiempo de inicio en segundos
        end_time: Tiempo de fin en segundos
    
    Returns:
        numpy.array: Embedding de 96 dimensiones o None si falla
    """
    warnings.filterwarnings('ignore')
    
    # Convertir audio a WAV usando ffmpeg
    temp_wav = None
    try:
        temp_fd, temp_wav = tempfile.mkstemp(suffix='.wav')
        os.close(temp_fd)
        
        ffmpeg_path = os.environ.get('FFMPEG_BIN', 'ffmpeg')
        if ffmpeg_path.startswith('"') and ffmpeg_path.endswith('"'):
            ffmpeg_path = ffmpeg_path[1:-1]
        
        # Extraer solo el segmento necesario
        result = subprocess.run([
            ffmpeg_path,
            '-i', audio_path,
            '-ss', str(start_time),
            '-t', str(end_time - start_time),
            '-ar', '16000',
            '-ac', '1',
            '-f', 'wav',
            '-y',
            temp_wav
        ], capture_output=True, text=True, timeout=30)
        
        if result.returncode != 0:
            print(f'Error en ffmpeg: {result.stderr}', file=sys.stderr)
            return None
        
        # Cargar el WAV con scipy
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
            
    except Exception as e:
        print(f'Error al procesar audio: {e}', file=sys.stderr)
        return None
    finally:
        if temp_wav and os.path.exists(temp_wav):
            try:
                os.unlink(temp_wav)
            except:
                pass
    
    # Validar duración mínima (al menos 2 segundos)
    duration = len(y) / float(sr)
    if duration < 2:
        print(f'Segmento demasiado corto: {duration:.1f}s', file=sys.stderr)
        return None
    
    # Detectar silencio
    rms = np.sqrt(np.mean(y**2))
    if rms < 0.005:
        print('Segmento en silencio', file=sys.stderr)
        return None
    
    # Extraer características
    try:
        mfccs = librosa.feature.mfcc(y=y, sr=sr, n_mfcc=20)
        mfcc_mean = np.mean(mfccs, axis=1)
        mfcc_std = np.std(mfccs, axis=1)
        
        spectral_centroid = np.mean(librosa.feature.spectral_centroid(y=y, sr=sr))
        spectral_rolloff = np.mean(librosa.feature.spectral_rolloff(y=y, sr=sr))
        spectral_bandwidth = np.mean(librosa.feature.spectral_bandwidth(y=y, sr=sr))
        zcr = np.mean(librosa.feature.zero_crossing_rate(y))
        
        chroma = librosa.feature.chroma_stft(y=y, sr=sr)
        chroma_mean = np.mean(chroma, axis=1)
        
        mel_spec = librosa.feature.melspectrogram(y=y, sr=sr, n_mels=40)
        mel_mean = np.mean(mel_spec, axis=1)
        
    except OSError as e:
        if 'WinError 10106' in str(e):
            print('Error de Windows con asyncio', file=sys.stderr)
        else:
            print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    except Exception as e:
        print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    
    # Combinar características
    embedding = np.concatenate([
        mfcc_mean,
        mfcc_std,
        [spectral_centroid],
        [spectral_rolloff],
        [spectral_bandwidth],
        [zcr],
        chroma_mean,
        mel_mean[:20]
    ])
    
    return embedding.tolist()


def cosine_similarity(vec1, vec2):
    """Calcula similitud coseno entre dos vectores"""
    vec1 = np.array(vec1)
    vec2 = np.array(vec2)
    
    dot_product = np.dot(vec1, vec2)
    magnitude1 = np.linalg.norm(vec1)
    magnitude2 = np.linalg.norm(vec2)
    
    if magnitude1 == 0 or magnitude2 == 0:
        return 0.0
    
    return float(dot_product / (magnitude1 * magnitude2))


def main():
    if len(sys.argv) < 3:
        print('Uso: python identify_speakers.py <audio_path> <segments_json>', file=sys.stderr)
        print('segments_json: {"segments": [{"start": 0, "end": 10, "speaker": "A"}], "user_embeddings": {"1": [...], "2": [...]}}', file=sys.stderr)
        return 1
    
    audio_path = sys.argv[1]
    segments_json = sys.argv[2]
    
    if not os.path.exists(audio_path):
        print(f'El archivo no existe: {audio_path}', file=sys.stderr)
        return 1
    
    try:
        data = json.loads(segments_json)
        segments = data.get('segments', [])
        user_embeddings = data.get('user_embeddings', {})
    except json.JSONDecodeError as e:
        print(f'JSON inválido: {e}', file=sys.stderr)
        return 1
    
    # Procesar cada segmento
    results = []
    for segment in segments:
        start = segment['start']
        end = segment['end']
        speaker_label = segment.get('speaker', 'unknown')
        
        # Extraer embedding del segmento
        segment_embedding = extract_voice_features_from_segment(audio_path, start, end)
        
        if segment_embedding is None:
            results.append({
                'start': start,
                'end': end,
                'speaker': speaker_label,
                'matched_user_id': None,
                'confidence': 0,
                'error': 'Could not extract features'
            })
            continue
        
        # Comparar con embeddings de usuarios
        best_match = None
        best_similarity = 0
        
        for user_id, user_embedding in user_embeddings.items():
            if not isinstance(user_embedding, list) or len(user_embedding) != 96:
                continue
            
            similarity = cosine_similarity(segment_embedding, user_embedding)
            
            if similarity > best_similarity:
                best_similarity = similarity
                best_match = user_id
        
        results.append({
            'start': start,
            'end': end,
            'speaker': speaker_label,
            'matched_user_id': best_match,
            'confidence': best_similarity,
        })
    
    # Imprimir resultados como JSON en UTF-8
    output = json.dumps(results, ensure_ascii=False)
    sys.stdout.buffer.write(output.encode('utf-8'))
    sys.stdout.buffer.write(b'\n')
    sys.stdout.buffer.flush()
    
    return 0


if __name__ == '__main__':
    sys.exit(main())
