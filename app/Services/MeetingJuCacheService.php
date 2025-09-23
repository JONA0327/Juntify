<?php

namespace App\Services;

use App\Models\AiMeetingJuCache;
use Illuminate\Support\Facades\Storage;

class MeetingJuCacheService
{
    private string $disk;

    public function __construct()
    {
        $this->disk = 'local';
    }

    public function getCachedParsed(int $meetingId): ?array
    {
        try {
            $row = AiMeetingJuCache::where('meeting_id', $meetingId)->first();
            if ($row) {
                return $row->data; // decrypted array
            }
        } catch (\Throwable $e) {
            // fall through to file cache
        }

        // Fallback simple file cache (legacy)
        $path = $this->pathFor($meetingId);
        if (!Storage::disk($this->disk)->exists($path)) {
            return null;
        }
        try {
            $json = Storage::disk($this->disk)->get($path);
            $payload = json_decode($json, true);
            $data = is_array($payload) ? ($payload['data'] ?? null) : null;
            return is_array($data) ? $data : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public function setCachedParsed(int $meetingId, array $parsed, ?string $transcriptDriveId = null, ?array $rawFull = null): bool
    {
        try {
            $row = AiMeetingJuCache::firstOrNew(['meeting_id' => $meetingId]);
            if ($transcriptDriveId !== null) {
                $row->transcript_drive_id = $transcriptDriveId;
            }
            $row->data = $parsed; // mutator encrypts and sets checksum/size
            if ($rawFull !== null) {
                $row->raw_data = $rawFull; // nuevo mutator
            }
            $row->save();
            return true;
        } catch (\Throwable $e) {
            // fallback to file cache to avoid losing data
            try {
                $payload = [
                    'cached_at' => now()->toIso8601String(),
                    'data' => $parsed,
                    'raw' => $rawFull,
                ];
                $path = $this->pathFor($meetingId);
                Storage::disk($this->disk)->makeDirectory('ju-cache');
                Storage::disk($this->disk)->put($path, json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    private function pathFor(int $meetingId): string
    {
        return 'ju-cache/meeting_' . $meetingId . '.json';
    }
}
