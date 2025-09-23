<?php

namespace App\Services;

use App\Models\AiMeetingJuCache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

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
                $data = $row->data; // decrypted array
                if ($data === null) {
                    Log::warning('MeetingJuCacheService:getCachedParsed data null (maybe decryption failure or empty)', [
                        'meeting_id' => $meetingId,
                        'has_encrypted' => !empty($row->encrypted_data),
                    ]);
                }
                return $data;
            }
        } catch (\Throwable $e) {
            Log::warning('MeetingJuCacheService:getCachedParsed exception, using file fallback', [
                'meeting_id' => $meetingId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_top' => collect(explode("\n", $e->getTraceAsString()))->take(5)->implode(" | "),
            ]);
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

            $rawColumnsPresent = Schema::hasColumn('ai_meeting_ju_caches', 'raw_encrypted_data');
            if ($rawFull !== null && $rawColumnsPresent) {
                try {
                    $row->raw_data = $rawFull; // nuevo mutator
                } catch (\Throwable $eSet) {
                    Log::warning('MeetingJuCacheService:setCachedParsed unable to set raw_data', [
                        'meeting_id' => $meetingId,
                        'error' => $eSet->getMessage(),
                    ]);
                }
            } elseif ($rawFull !== null && !$rawColumnsPresent) {
                Log::info('MeetingJuCacheService:setCachedParsed raw columns missing, skipping raw storage', [
                    'meeting_id' => $meetingId,
                ]);
            }

            $row->save();
            return true;
        } catch (\Throwable $e) {
            Log::warning('MeetingJuCacheService:setCachedParsed DB path failed, using file fallback', [
                'meeting_id' => $meetingId,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace_top' => collect(explode("\n", $e->getTraceAsString()))->take(5)->implode(" | "),
            ]);
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
                Log::error('MeetingJuCacheService:setCachedParsed fallback file write failed', [
                    'meeting_id' => $meetingId,
                    'error' => $e2->getMessage(),
                ]);
                return false;
            }
        }
    }

    private function pathFor(int $meetingId): string
    {
        return 'ju-cache/meeting_' . $meetingId . '.json';
    }
}
