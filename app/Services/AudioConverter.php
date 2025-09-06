<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AudioConverter
{
    /**
     * Convert a WEBM audio file to MP3 if needed.
     *
     * @param  string      $path           Path to the audio file
     * @param  string|null $extensionHint  Optional extension hint (without dot)
     * @return string Path to the converted file or original path on failure/if not WEBM
     */
    public function convertWebmToMp3(string $path, ?string $extensionHint = null): string
    {
        $extension = strtolower($extensionHint ?? pathinfo($path, PATHINFO_EXTENSION));

        $mime = null;
        try {
            if (function_exists('finfo_open')) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                if ($finfo) {
                    $mime = finfo_file($finfo, $path) ?: null;
                    finfo_close($finfo);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to detect mime type for audio', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        if ($extension !== 'webm' && (! $mime || stripos($mime, 'webm') === false)) {
            return $path;
        }

        $target = $path . '.mp3';

        try {
            $cmd = sprintf(
                'ffmpeg -y -i %s -vn -acodec libmp3lame -q:a 2 %s 2>&1',
                escapeshellarg($path),
                escapeshellarg($target)
            );
            exec($cmd, $output, $code);
            if ($code === 0 && file_exists($target)) {
                Log::info('Converted WEBM to MP3', [
                    'source' => $path,
                    'target' => $target,
                ]);
                return $target;
            }

            Log::warning('WEBM to MP3 conversion failed', [
                'source' => $path,
                'exit_code' => $code,
                'output' => $output ?? [],
            ]);
        } catch (\Throwable $e) {
            Log::warning('WEBM to MP3 conversion error', [
                'source' => $path,
                'error' => $e->getMessage(),
            ]);
        }

        return $path;
    }
}

