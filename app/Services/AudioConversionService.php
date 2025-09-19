<?php

namespace App\Services;

use App\Exceptions\FfmpegUnavailableException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class AudioConversionService
{
    private function ffmpegBin(): string
    {
        return (string) config('audio.ffmpeg_bin', 'ffmpeg');
    }

    private function ffprobeBin(): string
    {
        return (string) config('audio.ffprobe_bin', 'ffprobe');
    }

    private function ensureFfmpegAvailable(): void
    {
        // Quick check to see if ffmpeg is callable
        $proc = new Process([$this->ffmpegBin(), '-version']);
        $proc->setTimeout(10);
        $proc->run();
        if (!$proc->isSuccessful()) {
            throw new FfmpegUnavailableException('FFmpeg not available');
        }
        // ffprobe often improves format detection
        $probe = new Process([$this->ffprobeBin(), '-version']);
        $probe->setTimeout(10);
        $probe->run();
        Log::info('ffmpeg/ffprobe detected', [
            'ffmpeg_bin' => $this->ffmpegBin(),
            'ffprobe_bin' => $this->ffprobeBin(),
            'ffmpeg' => trim($proc->getOutput()) ?: trim($proc->getErrorOutput()),
            'ffprobe' => trim($probe->getOutput()) ?: trim($probe->getErrorOutput()),
        ]);
    }

    private function buildCommonDecodeArgs(): array
    {
        // Be more tolerant with odd/variable streams and timestamps
        return [
            '-hide_banner',
            '-loglevel', 'error',
            '-analyzeduration', '200M',
            '-probesize', '200M',
            '-fflags', '+genpts',
            '-fflags', '+discardcorrupt',
            '-err_detect', 'ignore_err',
        ];
    }

    private function convertViaWav(string $filePath, int $timeout): array
    {
        $tmpBase = tempnam(sys_get_temp_dir(), 'aud_wav_');
        if ($tmpBase === false) {
            throw new RuntimeException('Unable to create temporary file for WAV conversion');
        }
        $wavPath = $tmpBase . '.wav';

        $args = array_merge([$this->ffmpegBin(), '-y'], $this->buildCommonDecodeArgs(), [
            '-i', $filePath,
            '-vn',
            '-acodec', 'pcm_s16le',
            '-ac', '1',
            '-ar', '48000',
            $wavPath,
        ]);

        $proc = new Process($args);
        $proc->setTimeout($timeout);
        $proc->run();
        if (!$proc->isSuccessful()) {
            @unlink($wavPath);
            throw new RuntimeException('FFmpeg WAV fallback failed: ' . $proc->getErrorOutput());
        }

        return ['wav' => $wavPath];
    }
    /**
     * Convert the provided audio file into MP3 format using ffmpeg.
     *
     * @param  string      $filePath
     * @param  string|null $mimeType
     * @param  string|null $originalExtension
     * @return array{path:string,mime_type:string,was_converted:bool}
     */
    public function convertToMp3(string $filePath, ?string $mimeType = null, ?string $originalExtension = null): array
    {
        $detectedExtension = strtolower($originalExtension ?? pathinfo($filePath, PATHINFO_EXTENSION));
        $detectedMime      = $mimeType ?? (is_readable($filePath) ? @mime_content_type($filePath) : null);

        $isAlreadyMp3 = ($detectedMime && str_contains($detectedMime, 'mpeg')) || $detectedExtension === 'mp3';

        if ($isAlreadyMp3) {
            Log::info('Audio already in MP3 format, skipping conversion', [
                'mime_type' => $detectedMime,
                'extension' => $detectedExtension,
            ]);

            return [
                'path'          => $filePath,
                'mime_type'     => 'audio/mpeg',
                'was_converted' => false,
            ];
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'converted_audio_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary file for MP3 conversion');
        }
        $targetPath = $tempPath . '.mp3';

        $command = [
            'ffmpeg',
            '-y',
            '-i',
            $filePath,
            '-vn',
            '-acodec',
            'libmp3lame',
            '-f',
            'mp3',
            $targetPath,
        ];

        $process = new Process($command);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($targetPath);

            $exitCode = $process->getExitCode();
            $errorOutput = $process->getErrorOutput();
            $normalizedError = strtolower($errorOutput);

            if ($exitCode === 126 || $exitCode === 127 || str_contains($normalizedError, 'not found')) {
                Log::warning('FFmpeg executable appears to be unavailable', [
                    'exit_code' => $exitCode,
                    'error_output' => $errorOutput,
                ]);

                throw new FfmpegUnavailableException('FFmpeg executable not available for audio conversion.', $exitCode ?? 0);
            }

            throw new RuntimeException('FFmpeg conversion failed: ' . $errorOutput);
        }

        Log::info('Audio converted to MP3 using ffmpeg', [
            'original_mime_type' => $detectedMime,
            'original_extension' => $detectedExtension,
            'target_path'        => $targetPath,
        ]);

        return [
            'path'          => $targetPath,
            'mime_type'     => 'audio/mpeg',
            'was_converted' => true,
        ];
    }

    /**
     * Convert the provided audio file into OGG (Opus) format using ffmpeg.
     * Will skip conversion if already ogg/opus.
     *
     * @param  string      $filePath
     * @param  string|null $mimeType
     * @param  string|null $originalExtension
     * @return array{path:string,mime_type:string,was_converted:bool}
     */
    public function convertToOgg(string $filePath, ?string $mimeType = null, ?string $originalExtension = null): array
    {
        $detectedExtension = strtolower($originalExtension ?? pathinfo($filePath, PATHINFO_EXTENSION));
        $detectedMime      = $mimeType ?? (is_readable($filePath) ? @mime_content_type($filePath) : null);

        $alreadyOgg = false;
        if ($detectedExtension === 'ogg' || $detectedExtension === 'opus') {
            $alreadyOgg = true;
        }
        if ($detectedMime) {
            $lowerMime = strtolower($detectedMime);
            if (str_contains($lowerMime, 'ogg') || str_contains($lowerMime, 'opus')) {
                $alreadyOgg = true;
            }
        }

        if ($alreadyOgg) {
            Log::info('Audio already in OGG/Opus format, skipping conversion', [
                'mime_type' => $detectedMime,
                'extension' => $detectedExtension,
            ]);
            return [
                'path'          => $filePath,
                'mime_type'     => 'audio/ogg',
                'was_converted' => false,
            ];
        }

        // If configured, try Python script first (it will perform its own ffmpeg checks)
        if (config('audio.use_python_script')) {
            try {
                $python = (string) config('audio.python_bin', 'python3');
                $script = base_path('tools/convert_to_ogg.py');
                if (!is_file($script)) {
                    Log::warning('Python converter script not found, falling back to PHP ffmpeg', ['path' => $script]);
                } else {
                    $tmpOutBase = tempnam(sys_get_temp_dir(), 'aud_ogg_');
                    if ($tmpOutBase === false) {
                        throw new RuntimeException('Unable to create temporary file for Python conversion');
                    }
                    $targetOut = $tmpOutBase . '.ogg';
                    // Build command
                    $cmd = [$python, $script, $filePath, '-o', $targetOut, '--print-json'];
                    $timeout = (int) config('audio.conversion_timeout', 1800);
                    // Pass ffmpeg paths to Python via env
                    $env = [
                        'FFMPEG_BIN' => (string) config('audio.ffmpeg_bin', 'ffmpeg'),
                        'FFPROBE_BIN' => (string) config('audio.ffprobe_bin', 'ffprobe'),
                        'AUDIO_CONVERSION_TIMEOUT' => (string) $timeout,
                    ];
                    $proc = new Process($cmd, null, $env);
                    $proc->setTimeout($timeout + 30);
                    $proc->run();
                    if ($proc->isSuccessful()) {
                        $out = trim($proc->getOutput());
                        $data = json_decode($out, true);
                        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
                            $outPath = $data['output'] ?? $targetOut;
                            if (is_file($outPath)) {
                                return [
                                    'path' => $outPath,
                                    'mime_type' => 'audio/ogg',
                                    'was_converted' => true,
                                ];
                            }
                        }
                        // If JSON parse failed or file missing, try to use the target path
                        if (is_file($targetOut)) {
                            return [
                                'path' => $targetOut,
                                'mime_type' => 'audio/ogg',
                                'was_converted' => true,
                            ];
                        }
                        Log::warning('Python conversion succeeded but output not found/invalid JSON', [
                            'stdout' => $out,
                            'stderr' => $proc->getErrorOutput(),
                        ]);
                    } else {
                        Log::warning('Python conversion failed, will try PHP ffmpeg path', [
                            'exit' => $proc->getExitCode(),
                            'stderr' => $proc->getErrorOutput(),
                        ]);
                    }
                }
                // Fallthrough: if we reach here, we will use PHP ffmpeg path
            } catch (\Throwable $e) {
                Log::warning('Python conversion threw, falling back to PHP ffmpeg', ['error' => $e->getMessage()]);
            }
        }

        // PHP ffmpeg path: ensure ffmpeg available
        $this->ensureFfmpegAvailable();

        $tempPath = tempnam(sys_get_temp_dir(), 'converted_audio_');
        if ($tempPath === false) {
            throw new RuntimeException('Unable to create temporary file for OGG conversion');
        }
        $targetPath = $tempPath . '.ogg';

        $bitrate = config('audio.opus_bitrate', '96k');
        $timeout = (int) config('audio.conversion_timeout', 1800);

        $command = array_merge([$this->ffmpegBin(), '-y'], $this->buildCommonDecodeArgs(), [
            '-i', $filePath,
            '-vn',
            '-c:a', 'libopus',
            '-b:a', $bitrate,
            '-application', 'voip',
            // Aseguramos sample rate estándar (48k) para Opus si no viene ya
            '-ar', '48000',
            $targetPath,
        ]);

        $process = new Process($command);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            @unlink($targetPath);
            $exitCode = $process->getExitCode();
            $errorOutput = $process->getErrorOutput();
            $normalizedError = strtolower($errorOutput);

            // Consider ffmpeg unavailable only for typical command-not-found cases
            $cmdNotFound = str_contains($normalizedError, 'is not recognized as an internal or external command')
                || str_contains($normalizedError, 'command not found')
                || str_contains($normalizedError, 'ffmpeg: not found');
            if ($exitCode === 126 || $exitCode === 127 || $cmdNotFound) {
                Log::warning('FFmpeg executable appears to be unavailable for OGG conversion', [
                    'exit_code' => $exitCode,
                    'error_output' => $errorOutput,
                ]);
                throw new FfmpegUnavailableException('FFmpeg executable not available for OGG conversion.', $exitCode ?? 0);
            }

            Log::warning('Direct OGG conversion failed, attempting WAV fallback', [
                'exit_code' => $exitCode,
                'error' => $errorOutput,
            ]);

            // Fallback: decode to WAV then encode to Opus
            try {
                $wav = $this->convertViaWav($filePath, $timeout);
                $second = array_merge([$this->ffmpegBin(), '-y'], $this->buildCommonDecodeArgs(), [
                    '-i', $wav['wav'],
                    '-vn',
                    '-c:a', 'libopus',
                    '-b:a', $bitrate,
                    '-application', 'voip',
                    '-ar', '48000',
                    $targetPath,
                ]);
                $p2 = new Process($second);
                $p2->setTimeout($timeout);
                $p2->run();
                @unlink($wav['wav']);
                if (!$p2->isSuccessful()) {
                    @unlink($targetPath);
                    throw new RuntimeException('FFmpeg OGG conversion failed after WAV fallback: ' . $p2->getErrorOutput());
                }
            } catch (RuntimeException $e) {
                throw $e;
            }
        }

        Log::info('Audio converted to OGG (Opus) using ffmpeg', [
            'original_mime_type' => $detectedMime,
            'original_extension' => $detectedExtension,
            'target_path'        => $targetPath,
            'bitrate'            => $bitrate,
        ]);

        return [
            'path'          => $targetPath,
            'mime_type'     => 'audio/ogg',
            'was_converted' => true,
        ];
    }
}
