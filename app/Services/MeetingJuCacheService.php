<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;

class MeetingJuCacheService
{
    private string $disk;

    public function __construct()
    {
        // Use local disk; ensure storage/app/ju-cache exists
        $this->disk = 'local';
    }

    public function getCachedParsed(int $meetingId): ?array
    {
        $path = $this->pathFor($meetingId);
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }
        try {
            $json = Storage::disk($this->disk)->get($path);
            $data = json_decode($json, true);
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setCachedParsed(int $meetingId, array $parsed): bool
    {
        $payload = [
            'cached_at' => now()->toIso8601String(),
            'data' => $parsed,
        ];
        try {
            $path = $this->pathFor($meetingId);
            // Ensure directory exists
            Storage::disk($this->disk)->makeDirectory('ju-cache');
            Storage::disk($this->disk)->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function pathFor(int $meetingId): string
    {
        return 'ju-cache/meeting_' . $meetingId . '.json';
    }
}
