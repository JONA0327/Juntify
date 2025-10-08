<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskLaravel;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use App\Services\GoogleServiceAccount;
use App\Services\MeetingJuCacheService;
use App\Traits\MeetingContentParsing;
use Google\Service\Drive\DriveFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class IntegrationDataController extends Controller
{
    use MeetingContentParsing;

    public function __construct(private MeetingJuCacheService $juCache)
    {
    }

    public function meetings(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = TranscriptionLaravel::query()
            ->where('username', $user->username)
            ->latest('created_at')
            ->limit(25)
            ->get(['id', 'meeting_name', 'created_at']);

        return response()->json([
            'data' => $meetings->map(fn ($meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->meeting_name,
                'created_at' => $meeting->created_at?->toIso8601String(),
                'created_at_readable' => $meeting->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function meetingDetails(Request $request, string $meetingId): JsonResponse
    {
        $user = $request->user();

        $meeting = TranscriptionLaravel::query()
            ->with('user:id,email,username')
            ->where('username', $user->username)
            ->where('id', $meetingId)
            ->first();

        if (!$meeting) {
            return response()->json([
                'message' => 'Reunión no encontrada o sin permisos para consultarla.',
            ], 404);
        }

        $juPayload = $this->buildMeetingJuPayload($meeting);
        $audioMeta = $this->buildMeetingAudioMetadata($meeting, $request);

        return response()->json([
            'data' => [
                'id' => $meeting->id,
                'title' => $meeting->meeting_name,
                'created_at' => $meeting->created_at?->toIso8601String(),
                'ju' => $juPayload,
                'audio' => $audioMeta,
            ],
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $meetingId = $request->query('meeting_id');

        $tasksQuery = TaskLaravel::query()
            ->with(['meeting:id,meeting_name,created_at'])
            ->where(function ($query) use ($user) {
                $query->where('username', $user->username)
                    ->orWhere('assigned_user_id', $user->id);
            });

        if ($meetingId) {
            $tasksQuery->where('meeting_id', $meetingId);
        }

        $tasks = $tasksQuery
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->tarea,
                'status' => $task->assignment_status ?? 'pendiente',
                'progress' => $task->progreso,
                'starts_at' => $task->fecha_inicio?->toDateString(),
                'due_date' => $task->fecha_limite?->toDateString(),
                'due_time' => $task->hora_limite,
                'assigned_to' => $task->asignado,
                'meeting' => $task->meeting ? [
                    'id' => $task->meeting->id,
                    'title' => $task->meeting->meeting_name,
                    'date' => $task->meeting->created_at?->toIso8601String(),
                ] : null,
            ])->values(),
        ]);
    }

    public function meetingAudio(Request $request, string $meetingId)
    {
        $user = $request->user();

        $meeting = TranscriptionLaravel::query()
            ->with('user:id,email,username')
            ->where('username', $user->username)
            ->where('id', $meetingId)
            ->first();

        if (!$meeting) {
            return response()->json([
                'message' => 'Reunión no encontrada o sin permisos para consultarla.',
            ], 404);
        }

        $download = $this->downloadMeetingAudio($meeting);

        if ($download === null) {
            return response()->json([
                'message' => 'No se pudo obtener el audio de la reunión solicitada.',
            ], 404);
        }

        $response = response($download['content']);
        $response->header('Content-Type', $download['mime_type'] ?? 'application/octet-stream');
        if (!empty($download['filename'])) {
            $response->header('Content-Disposition', 'inline; filename="' . addslashes($download['filename']) . '"');
        }
        if (!empty($download['size'])) {
            $response->header('Content-Length', (string) $download['size']);
        }

        return $response;
    }

    public function meetingTasks(Request $request, string $meetingId): JsonResponse
    {
        $user = $request->user();

        $meeting = TranscriptionLaravel::query()
            ->where('username', $user->username)
            ->where('id', $meetingId)
            ->first();

        if (!$meeting) {
            return response()->json([
                'message' => 'Reunión no encontrada o sin permisos para consultarla.',
            ], 404);
        }

        $tasks = TaskLaravel::query()
            ->where('meeting_id', $meeting->id)
            ->get();

        return response()->json([
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->meeting_name,
                'created_at' => $meeting->created_at?->toIso8601String(),
            ],
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->tarea,
                'status' => $task->assignment_status ?? 'pendiente',
                'progress' => $task->progreso,
                'due_date' => $task->fecha_limite?->toDateString(),
                'due_time' => $task->hora_limite,
            ])->values(),
        ]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $term = $validated['query'];

        $users = User::query()
            ->where(function ($query) use ($term) {
                $likeTerm = '%' . $term . '%';

                $query->where('full_name', 'like', $likeTerm)
                    ->orWhere('email', 'like', $likeTerm)
                    ->orWhere('username', 'like', $likeTerm);
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'email', 'username', 'roles']);

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->roles,
            ])->values(),
        ]);
    }

    private function buildMeetingJuPayload(TranscriptionLaravel $meeting): array
    {
        $meetingId = (int) $meeting->id;
        $cached = $this->juCache->getCachedParsed($meetingId);
        $rawData = $cached !== null ? $this->juCache->getCachedRaw($meetingId) : null;
        $source = 'cache';
        $needsEncryption = false;

        if ($cached === null) {
            $source = 'fresh';
            $content = $this->downloadMeetingJuContent($meeting);

            if ($content !== null) {
                $parsed = $this->decryptJuFile($content);
                $needsEncryption = (bool) ($parsed['needs_encryption'] ?? false);
                $normalized = $this->processTranscriptData($parsed['data'] ?? []);
                $rawData = is_array($parsed['raw'] ?? null) ? $parsed['raw'] : null;

                if (!empty($normalized)) {
                    if ($needsEncryption) {
                        $normalized['needs_encryption'] = true;
                    }

                    $this->juCache->setCachedParsed(
                        $meetingId,
                        $normalized,
                        $meeting->transcript_drive_id ? (string) $meeting->transcript_drive_id : null,
                        $rawData
                    );
                }
                $cached = $normalized;
            } else {
                $cached = $this->processTranscriptData($this->getDefaultMeetingData());
                $source = 'missing';
            }
        }

        if (is_array($cached) && array_key_exists('needs_encryption', $cached)) {
            $needsEncryption = (bool) $cached['needs_encryption'];
            unset($cached['needs_encryption']);
        }

        if ($rawData === null) {
            $rawData = $this->juCache->getCachedRaw($meetingId);
        }

        return [
            'available' => $cached !== null && ($meeting->transcript_drive_id || $meeting->transcript_download_url),
            'source' => $source,
            'needs_encryption' => $needsEncryption,
            'summary' => $cached['summary'] ?? null,
            'key_points' => $cached['key_points'] ?? [],
            'tasks' => $cached['tasks'] ?? [],
            'transcription' => $cached['transcription'] ?? '',
            'speakers' => $cached['speakers'] ?? [],
            'segments' => $cached['segments'] ?? [],
            'raw' => $rawData,
        ];
    }

    private function buildMeetingAudioMetadata(TranscriptionLaravel $meeting, Request $request): array
    {
        $metadata = [
            'available' => false,
            'source' => null,
            'filename' => null,
            'mime_type' => null,
            'size_bytes' => null,
            'stream_url' => null,
        ];

        $streamRoute = route('api.integrations.meetings.audio', ['meeting' => $meeting->id]);
        $plainToken = $this->resolvePlainToken($request);
        if ($plainToken) {
            $streamRoute .= (str_contains($streamRoute, '?') ? '&' : '?') . 'api_token=' . urlencode($plainToken);
        }
        $metadata['stream_url'] = $streamRoute;

        $fileId = $meeting->audio_drive_id;
        $ownerEmail = $meeting->user?->email;

        if ($fileId) {
            $info = $this->fetchDriveFileInfo($fileId, $ownerEmail);
            if ($info instanceof DriveFile && $info->getMimeType() !== 'application/vnd.google-apps.folder') {
                $metadata['available'] = true;
                $metadata['source'] = 'drive_id';
                $metadata['filename'] = $info->getName() ?: ('meeting_' . $meeting->id . '_audio');
                $metadata['mime_type'] = $info->getMimeType() ?: null;
                if ($info->getSize() !== null) {
                    $metadata['size_bytes'] = (int) $info->getSize();
                }
                return $metadata;
            }
        }

        if (!empty($meeting->audio_download_url)) {
            $metadata['available'] = true;
            $metadata['source'] = 'direct_url';
        }

        return $metadata;
    }

    private function resolvePlainToken(Request $request): ?string
    {
        if ($token = $request->bearerToken()) {
            return $token;
        }

        if ($headerToken = $request->header('X-API-Token')) {
            return trim($headerToken);
        }

        $queryToken = $request->query('api_token');

        return $queryToken ? trim($queryToken) : null;
    }

    private function downloadMeetingJuContent(TranscriptionLaravel $meeting): ?string
    {
        $ownerEmail = $meeting->user?->email;

        if (!empty($meeting->transcript_drive_id)) {
            $content = $this->downloadFileWithServiceAccount((string) $meeting->transcript_drive_id, $ownerEmail);
            if (is_string($content) && $content !== '') {
                return $content;
            }
        }

        if (!empty($meeting->transcript_download_url)) {
            $httpContent = $this->downloadViaHttp($meeting->transcript_download_url);
            if (is_string($httpContent)) {
                return $httpContent;
            }
        }

        return null;
    }

    private function downloadMeetingAudio(TranscriptionLaravel $meeting): ?array
    {
        $ownerEmail = $meeting->user?->email;
        $fileId = $meeting->audio_drive_id ? (string) $meeting->audio_drive_id : null;

        if ($fileId) {
            $info = $this->fetchDriveFileInfo($fileId, $ownerEmail);
            if ($info instanceof DriveFile && $info->getMimeType() !== 'application/vnd.google-apps.folder') {
                $content = $this->downloadFileWithServiceAccount($fileId, $ownerEmail);
                if (is_string($content) && $content !== '') {
                    $size = $info->getSize();
                    return [
                        'content' => $content,
                        'mime_type' => $info->getMimeType() ?: 'application/octet-stream',
                        'filename' => $info->getName() ?: ('meeting_' . $meeting->id . '_audio'),
                        'size' => $size !== null ? (int) $size : strlen($content),
                        'source' => 'drive_id',
                    ];
                }
            }
        }

        if (!empty($meeting->audio_download_url)) {
            $download = $this->downloadViaHttpWithMetadata($meeting->audio_download_url);
            if ($download !== null) {
                return [
                    'content' => $download['body'],
                    'mime_type' => $download['content_type'] ?? 'application/octet-stream',
                    'filename' => $download['filename'] ?? ('meeting_' . $meeting->id . '_audio'),
                    'size' => strlen($download['body']),
                    'source' => 'direct_url',
                ];
            }
        }

        return null;
    }

    private function fetchDriveFileInfo(string $fileId, ?string $ownerEmail = null): ?DriveFile
    {
        $serviceAccount = $this->makeServiceAccount($ownerEmail);
        if (!$serviceAccount) {
            return null;
        }

        try {
            return $serviceAccount->getFileInfo($fileId);
        } catch (Throwable $e) {
            Log::warning('IntegrationDataController: error fetching Drive file info', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($ownerEmail) {
            $serviceAccount = $this->makeServiceAccount();
            if ($serviceAccount) {
                try {
                    return $serviceAccount->getFileInfo($fileId);
                } catch (Throwable $e) {
                    Log::warning('IntegrationDataController: Drive file info without impersonation failed', [
                        'file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return null;
    }

    private function downloadFileWithServiceAccount(string $fileId, ?string $ownerEmail = null): ?string
    {
        $serviceAccount = $this->makeServiceAccount($ownerEmail);
        if (!$serviceAccount) {
            return null;
        }

        try {
            return $serviceAccount->downloadFile($fileId);
        } catch (Throwable $e) {
            Log::warning('IntegrationDataController: error downloading Drive file', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
        }

        if ($ownerEmail) {
            $serviceAccount = $this->makeServiceAccount();
            if ($serviceAccount) {
                try {
                    return $serviceAccount->downloadFile($fileId);
                } catch (Throwable $e) {
                    Log::warning('IntegrationDataController: Drive download without impersonation failed', [
                        'file_id' => $fileId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }

        return null;
    }

    private function downloadViaHttp(string $url): ?string
    {
        $result = $this->downloadViaHttpWithMetadata($url);
        return $result['body'] ?? null;
    }

    private function downloadViaHttpWithMetadata(string $url): ?array
    {
        try {
            $response = Http::timeout(20)->withHeaders([
                'User-Agent' => 'Juntify-Integration-Client',
            ])->get($url);

            if (!$response->successful()) {
                return null;
            }

            $contentDisposition = $response->header('Content-Disposition');

            return [
                'body' => $response->body(),
                'content_type' => $response->header('Content-Type'),
                'filename' => $this->extractFilenameFromDisposition($contentDisposition),
            ];
        } catch (Throwable $e) {
            Log::warning('IntegrationDataController: HTTP download failed', [
                'url' => substr($url, 0, 200),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    private function extractFilenameFromDisposition(?string $header): ?string
    {
        if (!$header) {
            return null;
        }

        if (preg_match("/filename\\*=UTF-8''([^;]+)/", $header, $matches)) {
            return urldecode($matches[1]);
        }

        if (preg_match('/filename="?([^";]+)"?/i', $header, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }

    private function makeServiceAccount(?string $impersonateEmail = null): ?GoogleServiceAccount
    {
        try {
            $serviceAccount = new GoogleServiceAccount();
        } catch (Throwable $e) {
            Log::warning('IntegrationDataController: service account unavailable', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }

        if ($impersonateEmail) {
            try {
                $serviceAccount->impersonate($impersonateEmail);
            } catch (Throwable $e) {
                Log::warning('IntegrationDataController: impersonation failed', [
                    'email' => $impersonateEmail,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $serviceAccount;
    }
}
