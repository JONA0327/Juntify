<?php

namespace App\Services;

use App\Exceptions\FfmpegUnavailableException;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Symfony\Component\Process\Process;

class AudioConversionService
{
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
}
