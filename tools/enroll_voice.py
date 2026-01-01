import json
import sys

import librosa
import numpy as np
from resemblyzer import VoiceEncoder, preprocess_wav

MIN_DURATION_SECONDS = 10
SILENCE_RMS_THRESHOLD = 0.005


def main() -> int:
    if len(sys.argv) < 2:
        print('Se requiere la ruta del archivo de audio.', file=sys.stderr)
        return 1

    audio_path = sys.argv[1]

    try:
        wav, sample_rate = librosa.load(audio_path, sr=None, mono=True)
    except Exception as exc:
        print(f'No se pudo leer el audio: {exc}', file=sys.stderr)
        return 1

    duration = librosa.get_duration(y=wav, sr=sample_rate)
    if duration < MIN_DURATION_SECONDS:
        print('El audio es demasiado corto. Graba al menos 10 segundos.', file=sys.stderr)
        return 1

    rms = librosa.feature.rms(y=wav)
    if np.mean(rms) < SILENCE_RMS_THRESHOLD:
        print('El audio está en silencio. Intenta grabar nuevamente.', file=sys.stderr)
        return 1

    processed = preprocess_wav(wav, source_sr=sample_rate)
    processed_duration = len(processed) / 16000
    if processed_duration < MIN_DURATION_SECONDS * 0.6:
        print('El audio válido es demasiado corto. Intenta grabar nuevamente.', file=sys.stderr)
        return 1

    encoder = VoiceEncoder()
    embedding = encoder.embed_utterance(processed)

    print(json.dumps(embedding.tolist()))
    return 0


if __name__ == '__main__':
    raise SystemExit(main())
