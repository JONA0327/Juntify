#!/usr/bin/env python3
"""
convert_to_ogg.py

Standalone converter to OGG (Vorbis) using ffmpeg with a robust fallback path.
- Tries direct conversion to OGG with tolerant flags
- If it fails, decodes to WAV (PCM) first and then encodes to OGG
- Reads FFMPEG_BIN and FFPROBE_BIN from environment (optional); defaults to 'ffmpeg'/'ffprobe'

Usage:
  python tools/convert_to_ogg.py INPUT_FILE [-o OUTPUT_FILE] [--timeout SEC] [--print-json] [--keep-temp]

Examples:
  python tools/convert_to_ogg.py /path/audio.m4a -o /path/audio.ogg
  FFMPEG_BIN=/usr/bin/ffmpeg FFPROBE_BIN=/usr/bin/ffprobe python tools/convert_to_ogg.py input.webm --print-json
"""

import argparse
import json
import os
import shutil
import subprocess
import sys
import tempfile
from typing import List, Tuple


def env_ffmpeg() -> str:
    return os.environ.get('FFMPEG_BIN', 'ffmpeg')


def env_ffprobe() -> str:
    return os.environ.get('FFPROBE_BIN', 'ffprobe')


def run_cmd(cmd: List[str], timeout: int) -> Tuple[int, str, str]:
    proc = subprocess.Popen(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
    try:
        out, err = proc.communicate(timeout=timeout)
        return proc.returncode, out, err
    except subprocess.TimeoutExpired:
        proc.kill()
        out, err = proc.communicate()
        return 124, out, err


def check_ffmpeg_available() -> None:
    code, out, err = run_cmd([env_ffmpeg(), '-version'], timeout=10)
    if code != 0:
        raise RuntimeError('FFmpeg not available (FFMPEG_BIN). stderr: ' + (err or ''))
    code2, out2, err2 = run_cmd([env_ffprobe(), '-version'], timeout=10)
    # ffprobe no es estrictamente obligatorio, pero ayuda a diagnosticar
    # No fallamos si no está, solo informamos
    sys.stderr.write('Using ffmpeg="%s" ffprobe="%s"\n' % (env_ffmpeg(), env_ffprobe()))


def common_decode_flags() -> List[str]:
    return [
        '-hide_banner', '-loglevel', 'error',
        '-analyzeduration', '200M',
        '-probesize', '200M',
        '-fflags', '+genpts',
        '-fflags', '+discardcorrupt',
        '-err_detect', 'ignore_err',
    ]


def convert_direct_to_ogg(src: str, dst: str, timeout: int) -> None:
    cmd = [env_ffmpeg(), '-y'] + common_decode_flags() + [
        '-i', src,
        '-vn',
        '-c:a', 'libopus',
        '-b:a', '96k',
        '-application', 'voip',
        '-ar', '48000',
        dst,
    ]
    code, out, err = run_cmd(cmd, timeout)
    if code != 0:
        raise RuntimeError(f'Direct OGG conversion failed (code={code}): {err}')


def convert_to_wav(src: str, wav_path: str, timeout: int) -> None:
    cmd = [env_ffmpeg(), '-y'] + common_decode_flags() + [
        '-i', src,
        '-vn',
        '-acodec', 'pcm_s16le',
        '-ac', '1',
        '-ar', '48000',
        wav_path,
    ]
    code, out, err = run_cmd(cmd, timeout)
    if code != 0:
        raise RuntimeError(f'WAV fallback failed (code={code}): {err}')


def convert_wav_to_ogg(wav_path: str, dst: str, timeout: int) -> None:
    cmd = [env_ffmpeg(), '-y'] + common_decode_flags() + [
        '-i', wav_path,
        '-vn',
        '-c:a', 'libopus',
        '-b:a', '96k',
        '-application', 'voip',
        '-ar', '48000',
        dst,
    ]
    code, out, err = run_cmd(cmd, timeout)
    if code != 0:
        raise RuntimeError(f'WAV->OGG conversion failed (code={code}): {err}')


def main() -> int:
    parser = argparse.ArgumentParser(description='Convert any audio to OGG (Vorbis) using ffmpeg with WAV fallback.')
    parser.add_argument('input', help='Input audio file path')
    parser.add_argument('-o', '--output', help='Output .ogg file path (default: temp file)')
    parser.add_argument('--timeout', type=int, default=int(os.environ.get('AUDIO_CONVERSION_TIMEOUT', '1800')),
                        help='Timeout seconds per ffmpeg process (default from env AUDIO_CONVERSION_TIMEOUT or 1800)')
    parser.add_argument('--print-json', action='store_true', help='Print JSON result to stdout')
    parser.add_argument('--keep-temp', action='store_true', help='Keep temporary WAV on disk (debug)')
    args = parser.parse_args()

    src = args.input
    if not os.path.isfile(src):
        sys.stderr.write('Input file not found: %s\n' % src)
        return 1

    try:
        check_ffmpeg_available()
    except Exception as e:
        sys.stderr.write(str(e) + '\n')
        return 2

    # Determine output path
    if args.output:
        dst = args.output
    else:
        base = os.path.splitext(os.path.basename(src))[0]
        dst = os.path.join(tempfile.gettempdir(), base + '.ogg')

    was_converted = False
    tmp_wav = None
    try:
        try:
            convert_direct_to_ogg(src, dst, args.timeout)
            was_converted = True
        except Exception as e1:
            sys.stderr.write('Direct conversion failed, trying WAV fallback...\n')
            # Make WAV path
            tmp_base = tempfile.mkstemp(prefix='aud_wav_')[1]
            tmp_wav = tmp_base + '.wav'
            os.replace(tmp_base, tmp_wav)  # ensure file exists with .wav suffix
            convert_to_wav(src, tmp_wav, args.timeout)
            convert_wav_to_ogg(tmp_wav, dst, args.timeout)
            was_converted = True
    finally:
        if tmp_wav and os.path.isfile(tmp_wav) and not args.keep_temp:
            try:
                os.remove(tmp_wav)
            except Exception:
                pass

    if args.print_json:
        payload = {
            'ok': True,
            'input': os.path.abspath(src),
            'output': os.path.abspath(dst),
            'was_converted': was_converted,
            'output_size': os.path.getsize(dst) if os.path.isfile(dst) else None,
            'ffmpeg_bin': env_ffmpeg(),
            'ffprobe_bin': env_ffprobe(),
        }
        print(json.dumps(payload, ensure_ascii=False))
    else:
        print('Converted to:', dst)

    return 0


if __name__ == '__main__':
    sys.exit(main())
