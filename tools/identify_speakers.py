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
        # Usar directorio temporal del proyecto si existe
        temp_dir = os.environ.get('TEMP', tempfile.gettempdir())
        temp_fd, temp_wav = tempfile.mkstemp(suffix='.wav', dir=temp_dir)
        os.close(temp_fd)
        
        # Usar ffmpeg portable del proyecto primero
        project_ffmpeg = os.path.join(os.path.dirname(os.path.dirname(__file__)), 'ffmpeg-portable', 'bin', 'ffmpeg.exe')
        if os.path.exists(project_ffmpeg):
            ffmpeg_path = project_ffmpeg
        else:
            ffmpeg_path = os.environ.get('FFMPEG_BIN', 'ffmpeg')
            if ffmpeg_path.startswith('"') and ffmpeg_path.endswith('"'):
                ffmpeg_path = ffmpeg_path[1:-1]
        
        # Flags para evitar bloqueo de Windows
        creation_flags = 0
        if sys.platform == 'win32':
            creation_flags = 0x08000000  # CREATE_NO_WINDOW
        
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
        ], capture_output=True, text=True, timeout=30, creationflags=creation_flags)
        
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
            print('Error de Windows con asyncio', file=sys.stderr)
        else:
            print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    except Exception as e:
        print(f'Error al extraer características: {e}', file=sys.stderr)
        return None
    
    # Combinar características (76 dimensiones)
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
    
    # Verificar dimensiones
    if len(embedding) != 76:
        print(f'Advertencia: embedding tiene {len(embedding)} dimensiones, esperadas 76', file=sys.stderr)
    
    # Normalizar el embedding
    embedding = normalize_embedding(embedding)
    
    return embedding.tolist()


def normalize_embedding(vec):
    """Normaliza un embedding para que tenga media 0 y desviación estándar 1"""
    vec = np.array(vec)
    mean = np.mean(vec)
    std = np.std(vec)
    if std == 0:
        return vec
    return (vec - mean) / std


def cosine_similarity(vec1, vec2):
    """Calcula similitud coseno entre dos vectores.
    IMPORTANTE: No normalizar aquí porque vec2 (de BD) ya está normalizado.
    Solo vec1 (del audio) debe normalizarse antes de llamar a esta función."""
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
        similarities = {}
        
        for user_id, user_embedding in user_embeddings.items():
            # Aceptar embeddings de 76 o 96 dimensiones
            if not isinstance(user_embedding, list):
                print(f'Usuario {user_id}: embedding no es lista', file=sys.stderr)
                continue
            
            emb_len = len(user_embedding)
            if emb_len not in [76, 96]:
                print(f'Usuario {user_id}: embedding con {emb_len} dimensiones (esperadas 76 o 96)', file=sys.stderr)
                continue
            
            # Normalizar embeddings viejos (detectar si NO están normalizados)
            usr_emb = np.array(user_embedding)
            usr_mean = np.mean(usr_emb)
            usr_std = np.std(usr_emb)
            
            # Si el embedding no está normalizado (mean >> 0 o std >> 1), normalizarlo
            if abs(usr_mean) > 1.0 or abs(usr_std - 1.0) > 0.5:
                print(f'Usuario {user_id}: normalizando embedding viejo (mean={usr_mean:.2f}, std={usr_std:.2f})', file=sys.stderr)
                usr_emb = normalize_embedding(usr_emb)
            
            # Si las dimensiones no coinciden, ajustar
            seg_emb = segment_embedding
            usr_emb = usr_emb.tolist() if isinstance(usr_emb, np.ndarray) else usr_emb
            
            if len(seg_emb) != len(usr_emb):
                min_len = min(len(seg_emb), len(usr_emb))
                seg_emb = seg_emb[:min_len]
                usr_emb = usr_emb[:min_len]
                print(f'Ajustando embeddings a {min_len} dimensiones para comparación', file=sys.stderr)
            
            similarity = cosine_similarity(seg_emb, usr_emb)
            similarities[user_id] = similarity
            
            if similarity > best_similarity:
                best_similarity = similarity
                best_match = user_id
        
        # Log de similitudes para debugging
        print(f'Segmento {start}-{end}s: similitudes = {similarities}, mejor = {best_match} ({best_similarity:.3f})', file=sys.stderr)
        
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
