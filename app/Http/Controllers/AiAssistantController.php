<?php

namespace App\Http\Controllers;

use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiDocument;
use App\Models\AiMeetingDocument;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\TranscriptionLaravel;
use App\Models\SharedMeeting;
use App\Models\User;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Contact;
use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use App\Services\AiChatService;
use App\Services\GoogleDriveService;
use App\Services\GoogleTokenRefreshService;
use App\Jobs\ProcessAiDocumentJob;
use App\Services\EmbeddingSearch;
use App\Services\GoogleServiceAccount;
use App\Services\MeetingJuCacheService;
use App\Models\AiMeetingJuCache;
use App\Models\TaskLaravel;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;
use App\Support\OpenAiConfig;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\Storage;

class AiAssistantController extends Controller
{
    use MeetingContentParsing;
    private const DOCUMENTS_FOLDER_NAME = 'Documentos';

    private GoogleDriveService $googleDriveService;
    private GoogleTokenRefreshService $googleTokenRefreshService;

    public function __construct(
        GoogleDriveService $googleDriveService,
        GoogleTokenRefreshService $googleTokenRefreshService
    ) {
        $this->googleDriveService = $googleDriveService;
        $this->googleTokenRefreshService = $googleTokenRefreshService;
    }
    public function index()
    {
        return view('ai-assistant.index');
    }

    /**
     * Pre-cargar datos de .ju para todas las reuniones de un contenedor
     */
    public function preloadContainer(Request $request, int $containerId): JsonResponse
    {
        $user = Auth::user();

        // Permitir contenedores personales y organizacionales (si el usuario es miembro/owner)
        $container = MeetingContentContainer::with(['group', 'group.organization', 'meetings' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('id', $containerId)
            ->where('is_active', true)
            ->first();

        if (! $container) {
            return response()->json([
                'success' => false,
                'message' => 'Contenedor no encontrado',
            ], 404);
        }

        $isCreator = $container->username === $user->username;
        $isMember = $container->group_id
            ? DB::table('group_user')
                ->where('id_grupo', $container->group_id)
                ->where('user_id', $user->id)
                ->exists()
            : false;
        $isOrgOwner = $container->group && $container->group->organization
            ? $container->group->organization->admin_id === $user->id
            : false;

        if (!($isCreator || $isMember || $isOrgOwner)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para precargar este contenedor',
            ], 403);
        }

        $preloaded = [];
        $errors = [];

        /** @var MeetingJuCacheService $cache */
        $cache = app(MeetingJuCacheService::class);

        foreach ($container->meetings as $meeting) {
            try {
                // Descargar + parsear y cachear permanentemente el .ju
                $content = $this->tryDownloadJuContent($meeting);
                // Si falló la descarga, intentar localizar el .ju por nombre/ID en Drive incluso si transcript_drive_id ya estaba seteado
                if (!is_string($content) || $content === '') {
                    $found = $this->locateJuForMeeting($meeting, $container);
                    if ($found && $found !== $meeting->transcript_drive_id) {
                        $meeting->transcript_drive_id = $found;
                        $meeting->save();
                        $content = $this->tryDownloadJuContent($meeting);
                    }
                }
                if (is_string($content) && $content !== '') {
                    $parsed = $this->decryptJuFile($content);
                    $normalized = $this->processTranscriptData($parsed['data'] ?? []);
                    $cache->setCachedParsed((int)$meeting->id, $normalized, (string)$meeting->transcript_drive_id, $parsed['raw'] ?? null);
                } else {
                    // Intentar al menos construir fragmentos (por si hay otra fuente)
                    $this->buildFragmentsFromJu($meeting, '');
                }
                $preloaded[] = (int) $meeting->id;
            } catch (\Throwable $e) {
                $errors[] = [
                    'meeting_id' => (int) $meeting->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'container_id' => (int) $container->id,
            'meetings_preloaded' => $preloaded,
            'errors' => $errors,
        ]);
    }

    /**
     * Pre-cargar .ju y además importar tareas a BD para todas las reuniones de un contenedor
     */
    public function importContainerTasks(Request $request, int $containerId): JsonResponse
    {
        $user = Auth::user();

        $container = MeetingContentContainer::with(['group', 'group.organization', 'meetings' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('id', $containerId)
            ->where('is_active', true)
            ->firstOrFail();

        $isCreator = $container->username === $user->username;
        $isMember = $container->group_id
            ? DB::table('group_user')
                ->where('id_grupo', $container->group_id)
                ->where('user_id', $user->id)
                ->exists()
            : false;
        $isOrgOwner = $container->group && $container->group->organization
            ? $container->group->organization->admin_id === $user->id
            : false;

        if (!($isCreator || $isMember || $isOrgOwner)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para importar tareas en este contenedor',
            ], 403);
        }

        $results = [];
        $errors = [];

        /** @var MeetingJuCacheService $cache */
        $cache = app(MeetingJuCacheService::class);

        foreach ($container->meetings as $meeting) {
            $meetingInfo = [
                'id' => (int)$meeting->id,
                'name' => (string)$meeting->meeting_name,
                'has_transcript' => !empty($meeting->transcript_drive_id),
                'summary' => null,
                'key_points' => [],
                'tasks_imported' => ['created' => 0, 'updated' => 0],
            ];

            try {
                // Garantizar .ju disponible y cacheado
                $content = $this->tryDownloadJuContent($meeting);
                if (!is_string($content) || $content === '') {
                    $found = $this->locateJuForMeeting($meeting, $container);
                    if ($found && $found !== $meeting->transcript_drive_id) {
                        $meeting->transcript_drive_id = $found;
                        $meeting->save();
                        $content = $this->tryDownloadJuContent($meeting);
                    }
                }

                $data = null;
                if (is_string($content) && $content !== '') {
                    $parsed = $this->decryptJuFile($content);
                    $data = $this->processTranscriptData($parsed['data'] ?? []);
                    $cache->setCachedParsed((int)$meeting->id, $data, (string)$meeting->transcript_drive_id, $parsed['raw'] ?? null);
                } else {
                    // Última oportunidad: intentar leer cache previa
                    $cached = $cache->getCachedParsed((int)$meeting->id);
                    if (is_array($cached)) { $data = $cached; }
                }

                if ($data) {
                    $meetingInfo['summary'] = $data['summary'] ?? null;
                    $meetingInfo['key_points'] = $data['key_points'] ?? [];

                    // Importar tareas
                    $rawTasks = $data['tasks'] ?? [];
                    $tuple = $this->upsertTasksForMeeting($user->username, $meeting, $rawTasks);
                    $meetingInfo['tasks_imported'] = $tuple;
                }

                $results[] = $meetingInfo;
            } catch (\Throwable $e) {
                $errors[] = [
                    'meeting_id' => (int) $meeting->id,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return response()->json([
            'success' => true,
            'container_id' => (int)$container->id,
            'meetings' => $results,
            'errors' => $errors,
        ]);
    }

    /**
     * Diagnóstico detallado de acceso a .ju y cobertura por reunión en un contenedor
     * - Intenta descargar el .ju con distintos credenciales
     * - Si falla, intenta re-localizar el archivo en Drive
     * - Reporta si hay summary/puntos clave/segmentos, y cuantifica tareas existentes
     * Nota: No realiza upsert de tareas (solo lectura), para evitar efectos colaterales.
     */
    public function containerDiagnostics(Request $request, int $containerId): JsonResponse
    {
        $user = Auth::user();

        $container = MeetingContentContainer::with(['group', 'group.organization.googleToken', 'meetings' => function ($q) {
                $q->orderByDesc('created_at');
            }])
            ->where('id', $containerId)
            ->where('is_active', true)
            ->firstOrFail();

        $isCreator = $container->username === $user->username;
        $isMember = $container->group_id
            ? DB::table('group_user')
                ->where('id_grupo', $container->group_id)
                ->where('user_id', $user->id)
                ->exists()
            : false;
        $isOrgOwner = $container->group && $container->group->organization
            ? $container->group->organization->admin_id === $user->id
            : false;

        if (!($isCreator || $isMember || $isOrgOwner)) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para diagnosticar este contenedor',
            ], 403);
        }

        $report = [];

        /** @var MeetingJuCacheService $cache */
        $cache = app(MeetingJuCacheService::class);

        foreach ($container->meetings as $meeting) {
            $entry = [
                'id' => (int) $meeting->id,
                'name' => (string) $meeting->meeting_name,
                'date' => optional($meeting->created_at)->toDateString(),
                'original_file_id' => $meeting->transcript_drive_id,
                'relocated' => false,
                'new_file_id' => null,
                'access_path' => null,
                'summary' => false,
                'key_points' => 0,
                'segments' => 0,
                'tasks_count' => 0,
                'error' => null,
            ];

            try {
                $fileId = $meeting->transcript_drive_id;
                $content = null;

                // 1) Organización token (si existe)
                try {
                    $orgTokenModel = $container->group?->organization?->googleToken;
                    if ($orgTokenModel && $fileId) {
                        $this->googleDriveService->setAccessToken($this->normalizeOrganizationToken($orgTokenModel));
                        $content = $this->googleDriveService->downloadFileContent($fileId);
                        if (is_string($content) && $content !== '') { $entry['access_path'] = 'org_token'; }
                    }
                } catch (\Throwable $e) { /* continue */ }

                // 2) Service Account con impersonate
                if (!is_string($content) || $content === '') {
                    try {
                        /** @var GoogleServiceAccount $sa */
                        $sa = app(GoogleServiceAccount::class);
                        $owner = $meeting->user()->first();
                        if ($owner && !empty($owner->email)) {
                            try { $sa->impersonate($owner->email); } catch (\Throwable $eImp) {}
                        }
                        if ($fileId) {
                            $content = $sa->downloadFile($fileId);
                            if (is_string($content) && $content !== '') { $entry['access_path'] = 'sa_impersonate'; }
                        }
                    } catch (\Throwable $e) { /* continue */ }
                }

                // 3) Service Account sin impersonate
                if (!is_string($content) || $content === '') {
                    try {
                        /** @var GoogleServiceAccount $saNo */
                        $saNo = app(GoogleServiceAccount::class);
                        if ($fileId) {
                            $content = $saNo->downloadFile($fileId);
                            if (is_string($content) && $content !== '') { $entry['access_path'] = 'sa_direct'; }
                        }
                    } catch (\Throwable $e) { /* continue */ }
                }

                // 4) Token del dueño
                if (!is_string($content) || $content === '') {
                    try {
                        $owner = $meeting->user()->first();
                        $ownerToken = $owner?->googleToken;
                        if ($ownerToken && $fileId && method_exists($ownerToken, 'getTokenArray')) {
                            $this->googleDriveService->setAccessToken($ownerToken->getTokenArray());
                            $content = $this->googleDriveService->downloadFileContent($fileId);
                            if (is_string($content) && $content !== '') { $entry['access_path'] = 'owner_token'; }
                        }
                    } catch (\Throwable $e) { /* continue */ }
                }

                // 5) Token del usuario actual
                if (!is_string($content) || $content === '') {
                    try {
                        $userToken = $user?->googleToken;
                        if ($userToken && $fileId && method_exists($userToken, 'getTokenArray')) {
                            $this->googleDriveService->setAccessToken($userToken->getTokenArray());
                            $content = $this->googleDriveService->downloadFileContent($fileId);
                            if (is_string($content) && $content !== '') { $entry['access_path'] = 'user_token'; }
                        }
                    } catch (\Throwable $e) { /* continue */ }
                }

                // Si no logramos descargar y/o no hay fileId, intentar re-localizar
                if (!is_string($content) || $content === '' || !$fileId) {
                    try {
                        $found = $this->locateJuForMeeting($meeting, $container);
                        if ($found && $found !== $fileId) {
                            $entry['relocated'] = true;
                            $entry['new_file_id'] = $found;
                            $meeting->transcript_drive_id = $found;
                            $meeting->save();
                            // Intentar con Service Account directa como fallback
                            /** @var GoogleServiceAccount $saNo */
                            $saNo = app(GoogleServiceAccount::class);
                            $content = $saNo->downloadFile($found);
                            if (is_string($content) && $content !== '' && !$entry['access_path']) {
                                $entry['access_path'] = 'sa_direct (after relocate)';
                            }
                        }
                    } catch (\Throwable $e) { /* continue */ }
                }

                if (is_string($content) && $content !== '') {
                    $parsed = $this->decryptJuFile($content);
                    $data = $this->processTranscriptData($parsed['data'] ?? []);
                    $cache->setCachedParsed((int)$meeting->id, $data, (string)$meeting->transcript_drive_id, $parsed['raw'] ?? null);
                    $entry['summary'] = !empty($data['summary']);
                    $entry['key_points'] = is_array($data['key_points'] ?? null) ? count($data['key_points']) : 0;
                    $entry['segments'] = is_array($data['segments'] ?? null) ? min( (int) count($data['segments']), 5) : 0;
                } else {
                    $entry['error'] = $entry['error'] ?: 'no_access_or_not_found';
                }
                // Contar tareas existentes en BD (sin modificar)
                try {
                    $entry['tasks_count'] = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
                        ->where('username', $user->username)
                        ->count();
                } catch (\Throwable $e) { /* ignore */ }
            } catch (\Throwable $e) {
                $entry['error'] = $e->getMessage();
            }

            $report[] = $entry;
        }

        return response()->json([
            'success' => true,
            'container_id' => (int) $container->id,
            'container_name' => (string) $container->name,
            'meetings' => $report,
        ]);
    }

    /**
     * Pre-cargar .ju para una reunión específica (cachear contenido procesado)
     */
    public function preloadMeeting(Request $request, int $meetingId): JsonResponse
    {
        $user = Auth::user();

        // Cargar reunión; preferimos por username del solicitante, pero el flujo de tryDownloadJuContent
        // intentará usar distintos credenciales si aplica (org token, service account, owner token, etc.).
        $meeting = TranscriptionLaravel::where('id', $meetingId)->first();
        if (! $meeting) {
            return response()->json([
                'success' => false,
                'message' => 'Reunión no encontrada',
            ], 404);
        }

        $preloaded = false;
        $error = null;

        try {
            // Descargar + parsear y cachear permanentemente el .ju
            $content = $this->tryDownloadJuContent($meeting);
            if ((!$content || $content === '') && empty($meeting->transcript_drive_id)) {
                // Intentar localizar .ju por contenedores relacionados
                $containers = $meeting->containers()->with(['group', 'group.organization'])->get();
                foreach ($containers as $container) {
                    $found = $this->locateJuForMeeting($meeting, $container);
                    if ($found) {
                        $meeting->transcript_drive_id = $found;
                        $meeting->save();
                        $content = $this->tryDownloadJuContent($meeting);
                        break;
                    }
                }
            }

            if (is_string($content) && $content !== '') {
                $parsed = $this->decryptJuFile($content);
                $normalized = $this->processTranscriptData($parsed['data'] ?? []);

                /** @var MeetingJuCacheService $cache */
                $cache = app(MeetingJuCacheService::class);
                $cache->setCachedParsed((int)$meeting->id, $normalized, (string)$meeting->transcript_drive_id, $parsed['raw'] ?? null);
                $preloaded = true;
            } else {
                // Construir al menos fragmentos (puede ser vacío si no hay fuentes)
                $this->buildFragmentsFromJu($meeting, '');
            }
        } catch (\Throwable $e) {
            $error = $e->getMessage();
        }

        return response()->json([
            'success' => $preloaded,
            'meeting_id' => (int) $meeting->id,
            'message' => $preloaded ? 'Reunión precargada correctamente.' : ($error ? ('No se pudo precargar: ' . $error) : 'No se pudo precargar el .ju.'),
        ], $preloaded ? 200 : 500);
    }

    private function upsertTasksForMeeting(string $username, TranscriptionLaravel $meeting, $rawTasks): array
    {
        $created = 0; $updated = 0;
        $items = is_array($rawTasks) ? $rawTasks : ($rawTasks ? [$rawTasks] : []);
        foreach ($items as $item) {
            $parsed = $this->parseRawTaskForDb($item);

            $prioridad = null; $hora = null;
            if (is_array($item)) {
                $isAssoc = array_keys($item) !== range(0, count($item) - 1);
                if ($isAssoc) {
                    $prioridad = $item['prioridad'] ?? $item['priority'] ?? null;
                    $rawTime = $item['hora'] ?? $item['hora_limite'] ?? $item['time'] ?? $item['due_time'] ?? null;
                    if (is_string($rawTime) && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($rawTime), $tm)) {
                        $h = str_pad($tm[1], 2, '0', STR_PAD_LEFT); $m = $tm[2];
                        $hora = $h . ':' . $m;
                    }
                    foreach (['end','due','due_date','fecha_fin','start','start_date','fecha_inicio'] as $k) {
                        if (!empty($item[$k]) && is_string($item[$k]) && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})(?::\d{2})?$/', $item[$k], $dm)) {
                            $parsedDate = $dm[1]; $parsedTime = $dm[2];
                            if (empty($parsed['fecha_limite']) && in_array($k, ['end','due','due_date','fecha_fin'])) { $parsed['fecha_limite'] = $parsedDate; }
                            if (empty($parsed['fecha_inicio']) && in_array($k, ['start','start_date','fecha_inicio'])) { $parsed['fecha_inicio'] = $parsedDate; }
                            if ($hora === null) { $hora = strlen($parsedTime) === 4 ? '0'.$parsedTime : $parsedTime; }
                        }
                    }
                } else {
                    foreach ($item as $tok) {
                        if (is_string($tok) && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($tok), $tm)) {
                            $h = str_pad($tm[1], 2, '0', STR_PAD_LEFT); $m = $tm[2];
                            $hora = $h . ':' . $m; break;
                        }
                    }
                }
            } elseif (is_string($item)) {
                if (preg_match('/(\d{1,2}:\d{2})(?::\d{2})?/', $item, $tm)) {
                    $parts = explode(':', $tm[1]);
                    $hora = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . $parts[1];
                }
            }

            $payload = [
                'username' => $username,
                'meeting_id' => $meeting->id,
                'tarea' => substr((string)($parsed['tarea'] ?? 'Sin nombre'), 0, 255),
                'prioridad' => $prioridad ? substr((string)$prioridad, 0, 20) : null,
                'fecha_inicio' => $parsed['fecha_inicio'] ?: null,
                'fecha_limite' => $parsed['fecha_limite'] ?: null,
                'hora_limite' => $hora,
                'asignado' => $item['assignee'] ?? $item['assigned'] ?? $item['responsable'] ?? null,
                'progreso' => $parsed['progreso'] ?? 0,
            ];

            $existing = TaskLaravel::where('meeting_id', $payload['meeting_id'])
                ->where('tarea', $payload['tarea'])
                ->where('username', $username)
                ->first();
            if ($existing) { $existing->update($payload); $updated++; }
            else { TaskLaravel::create($payload); $created++; }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * Intenta localizar el archivo .ju de una reunión en Drive cuando no está definido en DB
     * Estrategia: buscar por nombre de reunión o ID dentro de la carpeta padre del audio si existe, o en la raíz conocida
     */
    private function locateJuForMeeting(TranscriptionLaravel $meeting, MeetingContentContainer $container): ?string
    {
        $keywords = [];
        if (!empty($meeting->meeting_name)) { $keywords[] = $meeting->meeting_name; }
        $keywords[] = (string) $meeting->id;

        $parentsToSearch = [];
        // Si hay audio_drive_id, intentar su carpeta padre primero
        if (!empty($meeting->audio_drive_id)) {
            try {
                $audioFile = $this->googleDriveService->getFileInfo($meeting->audio_drive_id);
                $parents = $audioFile->getParents();
                if (is_array($parents) && !empty($parents)) {
                    $parentsToSearch = array_merge($parentsToSearch, $parents);
                }
            } catch (\Throwable $e) {}
        }

        // Si el contenedor pertenece a una organización con root configurado, incluir esa raíz
        $orgRoot = $container->group?->organization?->folder?->google_id;
        if (!empty($orgRoot)) {
            $parentsToSearch[] = $orgRoot;
        }

        $parentsToSearch = array_values(array_unique(array_filter($parentsToSearch)));

        // Preparar token de organización si existe, o dejar que tryDownloadJuContent maneje tokens
        try {
            $orgTokenModel = $container->group?->organization?->googleToken;
            if ($orgTokenModel) {
                $this->googleDriveService->setAccessToken($this->normalizeOrganizationToken($orgTokenModel));
            }
        } catch (\Throwable $e) {}

        foreach ($parentsToSearch as $parentId) {
            foreach ($keywords as $kw) {
                try {
                    $files = $this->googleDriveService->searchFiles($kw, $parentId);
                    foreach ($files as $file) {
                        // Preferir archivos .ju por nombre o mime
                        $name = method_exists($file, 'getName') ? $file->getName() : ($file['name'] ?? '');
                        $mime = method_exists($file, 'getMimeType') ? $file->getMimeType() : ($file['mimeType'] ?? '');
                        $isJu = str_ends_with(strtolower($name), '.ju') || $mime === 'application/json';
                        if ($isJu) {
                            $id = method_exists($file, 'getId') ? $file->getId() : ($file['id'] ?? null);
                            if ($id) { return $id; }
                        }
                    }
                } catch (\Throwable $e) { /* seguir */ }
            }
        }

        // Último intento: búsqueda global por nombre/ID (puede ser costosa)
        foreach ($keywords as $kw) {
            try {
                $files = $this->googleDriveService->searchFiles($kw, null);
                foreach ($files as $file) {
                    $name = method_exists($file, 'getName') ? $file->getName() : ($file['name'] ?? '');
                    $mime = method_exists($file, 'getMimeType') ? $file->getMimeType() : ($file['mimeType'] ?? '');
                    $isJu = str_ends_with(strtolower($name), '.ju') || $mime === 'application/json';
                    if ($isJu) {
                        $id = method_exists($file, 'getId') ? $file->getId() : ($file['id'] ?? null);
                        if ($id) { return $id; }
                    }
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        return null;
    }

    /**
     * Obtener todas las sesiones de chat del usuario
     */
    public function getSessions(): JsonResponse
    {
        $user = Auth::user();

        $sessions = AiChatSession::byUser($user->username)
            ->active()
            ->orderBy('last_activity', 'desc')
            ->with(['messages' => function($query) {
                $query->visible()->latest()->limit(1);
            }])
            ->get()
            ->map(function($session) {
                $lastMessage = $session->messages->first();
                return [
                    'id' => $session->id,
                    'title' => $session->title,
                    'context_type' => $session->context_type,
                    'context_id' => $session->context_id,
                    'context_data' => $session->context_data,
                    'last_activity' => $session->last_activity,
                    'last_message' => $lastMessage ? [
                        'content' => Str::limit($lastMessage->content, 100),
                        'role' => $lastMessage->role,
                        'created_at' => $lastMessage->created_at
                    ] : null
                ];
            });

        return response()->json([
            'success' => true,
            'sessions' => $sessions
        ]);
    }

    /**
     * Actualiza el contexto de una sesión existente sin crear una nueva
     */
    public function updateSession(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'context_type' => 'required|in:general,container,meeting,contact_chat,documents,mixed',
            'context_id' => 'nullable',
            'context_data' => 'nullable|array',
        ]);

        $session = AiChatSession::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        // Mantener doc_ids previamente cargados si no vienen en el payload
        $newData = $validated['context_data'] ?? [];
        $currentData = is_array($session->context_data) ? $session->context_data : [];
        if (!array_key_exists('doc_ids', $newData) && array_key_exists('doc_ids', $currentData)) {
            $newData['doc_ids'] = $currentData['doc_ids'];
        }

        // Normalizar context_id a string
        $contextId = $validated['context_id'] ?? null;
        if (is_array($contextId) || is_object($contextId)) {
            $contextId = json_encode($contextId);
        } elseif ($contextId !== null) {
            $contextId = (string) $contextId;
        }

        $session->update([
            'context_type' => $validated['context_type'],
            'context_id' => $contextId,
            'context_data' => $newData,
        ]);

        $session->refresh();
        $session->updateActivity();

        return response()->json([
            'success' => true,
            'session' => [
                'id' => $session->id,
                'context_type' => $session->context_type,
                'context_id' => $session->context_id,
                'context_data' => $session->context_data,
                'title' => $session->title,
                'last_activity' => $session->last_activity,
            ],
        ]);
    }

    /**
     * Crear nueva sesión de chat
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'context_type' => 'required|in:general,container,meeting,contact_chat,documents,mixed',
            // Aceptar números o strings para evitar 422 cuando el front envía un número
            'context_id' => 'nullable',
            'context_data' => 'nullable|array'
        ]);

        // Normalizar context_id a string si viene definido (la columna es string)
        $contextId = $request->input('context_id');
        if ($contextId !== null && $contextId !== '') {
            $contextId = (string) $contextId;
        } else {
            $contextId = null;
        }

        $session = AiChatSession::create([
            'username' => $user->username,
            'title' => $request->title ?? 'Nueva conversación',
            'context_type' => $request->context_type,
            'context_id' => $contextId,
            'context_data' => $request->context_data ?? [],
            'last_activity' => now()
        ]);

        // Mensaje inicial del sistema con contexto
        $systemMessage = $this->generateSystemMessage($session);
        if ($systemMessage) {
            AiChatMessage::create([
                'session_id' => $session->id,
                'role' => 'system',
                'content' => $systemMessage,
                'is_hidden' => true
            ]);
        }

        return response()->json([
            'success' => true,
            'session' => $session
        ]);
    }

    /**
     * Eliminar o desactivar una sesión de chat
     */
    public function deleteSession(Request $request, int $sessionId): JsonResponse
    {
        $user = Auth::user();

        $session = AiChatSession::byUser($user->username)
            ->findOrFail($sessionId);

        // Por defecto, borra definitivamente a menos que se indique lo contrario
        $forceDelete = $request->has('force_delete')
            ? $request->boolean('force_delete')
            : true;

        if ($forceDelete) {
            // 1) Reunir documentos asociados a esta sesión (context_data, items, y metadatos creados por esta sesión)
            $docIdsFromContext = $this->collectSessionDocumentIds($session);
            $docIdsFromMetadata = [];
            try {
                // Documentos que fueron creados explícitamente dentro de esta sesión (metadato: created_in_session)
                $metaDocs = AiDocument::byUser($user->username)
                    ->where('document_metadata->created_in_session', (string) $session->id)
                    ->pluck('id')
                    ->all();
                $docIdsFromMetadata = array_map('intval', $metaDocs);
            } catch (\Throwable $e) {
                // Si la BD no soporta whereJson, ignorar silenciosamente
            }
            $sessionDocIds = array_values(array_unique(array_filter(array_merge($docIdsFromContext, $docIdsFromMetadata), fn($v) => is_numeric($v))));

            // 2) Verificar si esos documentos están referenciados por OTRAS sesiones activas del mismo usuario
            $referencedElsewhere = [];
            if (!empty($sessionDocIds)) {
                $otherSessions = AiChatSession::byUser($user->username)
                    ->where('id', '!=', $session->id)
                    ->active()
                    ->get();
                foreach ($otherSessions as $other) {
                    $ids = $this->collectSessionDocumentIds($other);
                    if (!empty($ids)) {
                        $referencedElsewhere = array_merge($referencedElsewhere, $ids);
                    }
                    // También intentar capturar docs creados dentro de esa otra sesión
                    try {
                        $metaDocsOther = AiDocument::byUser($user->username)
                            ->where('document_metadata->created_in_session', (string) $other->id)
                            ->pluck('id')
                            ->all();
                        $referencedElsewhere = array_merge($referencedElsewhere, array_map('intval', $metaDocsOther));
                    } catch (\Throwable $e) {}
                }
                $referencedElsewhere = array_values(array_unique(array_filter($referencedElsewhere, fn($v) => is_numeric($v))));
            }

            $docIdsToDelete = empty($sessionDocIds)
                ? []
                : array_values(array_diff($sessionDocIds, $referencedElsewhere));

            // 3) Eliminar embeddings y documentos de esta sesión que no estén usados por otras sesiones
            if (!empty($docIdsToDelete)) {
                foreach ($docIdsToDelete as $docId) {
                    try {
                        // Embeddings asociados a estos documentos
                        \App\Models\AiContextEmbedding::where('username', $user->username)
                            ->where('content_type', 'document_text')
                            ->where('content_id', (string) $docId)
                            ->delete();

                        // Borrado del documento (cascade eliminará asignaciones como ai_meeting_documents)
                        AiDocument::where('id', (int) $docId)
                            ->where('username', $user->username)
                            ->delete();
                    } catch (\Throwable $e) {
                        Log::warning('No se pudo eliminar completamente un documento al borrar la sesión', [
                            'session_id' => $session->id,
                            'document_id' => $docId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // 3.b) Eliminar cache de .ju cifrado (solo para reuniones vinculadas a esta sesión)
            try {
                // Reuniones asociadas a esta sesión
                $sessionMeetingIds = $this->collectSessionMeetingIds($session);
                if (!empty($sessionMeetingIds)) {
                    // Recolectar reuniones referenciadas por otras sesiones activas
                    $referencedMeetingsElsewhere = [];
                    $otherSessions = AiChatSession::byUser($user->username)
                        ->where('id', '!=', $session->id)
                        ->active()
                        ->get();
                    foreach ($otherSessions as $other) {
                        $ids = $this->collectSessionMeetingIds($other);
                        if (!empty($ids)) {
                            $referencedMeetingsElsewhere = array_merge($referencedMeetingsElsewhere, $ids);
                        }
                    }
                    $referencedMeetingsElsewhere = array_values(array_unique(array_filter($referencedMeetingsElsewhere, fn($v) => is_numeric($v))));

                    $meetingIdsToDelete = array_values(array_diff($sessionMeetingIds, $referencedMeetingsElsewhere));
                    if (!empty($meetingIdsToDelete)) {
                        AiMeetingJuCache::whereIn('meeting_id', $meetingIdsToDelete)->delete();
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('No se pudo eliminar cache de .ju al borrar la sesión', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            $docIdsToDelete = [];
        }

        // 4) Intentar eliminar embeddings de mensajes del chat asociados a la sesión (si existieran)
        try {
            \App\Models\AiContextEmbedding::where('username', $user->username)
                ->where('content_type', 'chat_message')
                ->where('metadata->session_id', (string) $session->id)
                ->delete();
        } catch (\Throwable $e) {
            // Ignorar si la BD no soporta el filtro JSON o si no existen
        }

        // 5) Siempre eliminar los mensajes (incluye mensajes del sistema ocultos)
        $session->messages()->delete();

        if ($forceDelete) {
            // Borrado definitivo de la sesión
            $session->delete();
        } else {
            // Borrado "suave": dejar la sesión inactiva y completamente limpia de contexto
            $session->fill([
                'is_active' => false,
                'context_type' => 'general',
                'context_id' => null,
                'context_data' => [],
                'title' => 'Nueva conversación',
            ])->save();
        }

        return response()->json([
            'success' => true,
            'session_id' => $sessionId,
            'deleted' => $forceDelete,
            'wiped' => ! $forceDelete,
            'documents_deleted' => $docIdsToDelete,
        ]);
    }

    /**
     * Recolecta IDs de documentos vinculados a una sesión a partir de su context_data.
     * - context_type = 'documents': context_data es lista de IDs
     * - context_data['doc_ids'] si existe
     * - context_type = 'mixed': items[] con type=document|documents
     */
    private function collectSessionDocumentIds(AiChatSession $session): array
    {
        $ids = [];
        $data = is_array($session->context_data) ? $session->context_data : [];

        // doc_ids explicitamente agregados al contexto
        $docIds = Arr::get($data, 'doc_ids', []);
        if (is_array($docIds)) {
            $ids = array_merge($ids, $docIds);
        }

        // Sesión de tipo documentos: el context_data es una lista de IDs
        if ($session->context_type === 'documents' && is_array($data)) {
            foreach ($data as $v) {
                if (is_numeric($v)) $ids[] = (int) $v;
            }
        }

        // Sesión mixta: items[] puede contener documentos
        if ($session->context_type === 'mixed') {
            $items = Arr::get($data, 'items', []);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $type = $item['type'] ?? null;
                    $id = $item['id'] ?? null;
                    if (in_array($type, ['document', 'documents'], true) && is_numeric($id)) {
                        $ids[] = (int) $id;
                    }
                    if ($type === 'documents' && is_array($id)) {
                        foreach ($id as $sub) {
                            if (is_numeric($sub)) $ids[] = (int) $sub;
                        }
                    }
                }
            }
        }

        // Normalizar y devolver únicos
        return array_values(array_unique(array_map('intval', $ids)));
    }

    /**
     * Recolecta IDs de reuniones vinculadas a la sesión a partir de su contexto
     * - meeting: context_id es el ID de la reunión
     * - container: incluye todas las reuniones del contenedor
     * - mixed: items[] puede contener type=meeting o type=container
     */
    private function collectSessionMeetingIds(AiChatSession $session): array
    {
        $ids = [];

        try {
            if ($session->context_type === 'meeting' && $session->context_id && is_numeric($session->context_id)) {
                $ids[] = (int) $session->context_id;
            }

            if ($session->context_type === 'container' && $session->context_id) {
                $container = MeetingContentContainer::with(['meetings' => function ($q) {
                    $q->select('id');
                }])->find($session->context_id);
                if ($container) {
                    $ids = array_merge($ids, $container->meetings->pluck('id')->map(fn($v)=> (int)$v)->all());
                }
            }

            if ($session->context_type === 'mixed') {
                $data = is_array($session->context_data) ? $session->context_data : [];
                $items = \Illuminate\Support\Arr::get($data, 'items', []);
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (!is_array($item)) continue;
                        $type = $item['type'] ?? null;
                        $id = $item['id'] ?? null;
                        if ($type === 'meeting' && is_numeric($id)) {
                            $ids[] = (int) $id;
                        } elseif ($type === 'container' && $id) {
                            $container = MeetingContentContainer::with(['meetings' => function ($q) {
                                $q->select('id');
                            }])->find($id);
                            if ($container) {
                                $ids = array_merge($ids, $container->meetings->pluck('id')->map(fn($v)=> (int)$v)->all());
                            }
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and return what we have
        }

        return array_values(array_unique(array_filter(array_map('intval', $ids), fn($v)=> $v>0)));
    }

    /**
     * Obtener mensajes de una sesión específica
     */
    public function getMessages($sessionId): JsonResponse
    {
        $user = Auth::user();

        $session = AiChatSession::byUser($user->username)
            ->findOrFail($sessionId);

        $messages = AiChatMessage::where('session_id', $sessionId)
            ->visible()
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(function($message) {
                return [
                    'id' => $message->id,
                    'role' => $message->role,
                    'content' => $message->content,
                    'attachments' => $message->attachments,
                    'metadata' => $message->metadata,
                    'created_at' => $message->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'messages' => $messages,
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'context_type' => $session->context_type,
                'context_data' => $session->context_data
            ]
        ]);
    }

    /**
     * Enviar mensaje al chat
     */
    public function sendMessage(Request $request, $sessionId): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'content' => 'required|string',
            'attachments' => 'nullable|array',
            // Mentions: [{ type: document|meeting|container, id: int, title?: string }]
            'mentions' => 'nullable|array',
            'mentions.*.type' => 'required_with:mentions|string|in:document,meeting,container',
            'mentions.*.id' => 'required_with:mentions|integer',
            'mentions.*.title' => 'nullable|string'
        ]);

        // Modo offline opcional: responder sin llamar a OpenAI
        $offline = filter_var(env('AI_ASSISTANT_OFFLINE', false), FILTER_VALIDATE_BOOLEAN);
        // Validar API Key de OpenAI antes de continuar (si no estamos offline)
        $apiKey = OpenAiConfig::apiKey();
        if (!$offline && empty($apiKey)) {
            Log::warning('AI assistant: missing OPENAI_API_KEY');
            return response()->json([
                'success' => false,
                'message' => 'Falta la API Key de OpenAI. Configura OPENAI_API_KEY en el servidor e intenta de nuevo.',
                'code' => 'OPENAI_API_KEY_MISSING'
            ], 422);
        }

        $session = AiChatSession::byUser($user->username)
            ->findOrFail($sessionId);

        $isFirstUserMessage = ! $session->messages()
            ->visible()
            ->where('role', 'user')
            ->exists();

        // Crear mensaje del usuario (temporal, luego añadimos metadata de menciones si aplica)
        $userMessage = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $request->content,
            'attachments' => $request->attachments ?? []
        ]);

        // Aplicar menciones al contexto de la sesión y guardar en metadata del mensaje
        $mentions = Arr::wrap($request->input('mentions', []));
        if (!empty($mentions)) {
            try {
                $contextData = is_array($session->context_data) ? $session->context_data : [];
                // Documentos: agregar IDs explícitos para priorizar su uso en contexto
                $existingDocIds = Arr::get($contextData, 'doc_ids', []);
                if (!is_array($existingDocIds)) { $existingDocIds = []; }
                $newDocIds = collect($mentions)
                    ->where('type', 'document')
                    ->pluck('id')
                    ->filter(fn($v) => is_numeric($v))
                    ->map(fn($v) => (int) $v)
                    ->values()
                    ->all();
                $contextData['doc_ids'] = array_values(array_unique(array_merge($existingDocIds, $newDocIds)));

                // Reuniones y contenedores: registrar como elementos mencionados adicionales
                $mentionItems = Arr::get($contextData, 'mention_items', []);
                if (!is_array($mentionItems)) { $mentionItems = []; }
                foreach ($mentions as $m) {
                    if (!is_array($m)) continue;
                    $type = $m['type'] ?? null;
                    $id = $m['id'] ?? null;
                    if (!$type || $id === null) continue;
                    if (in_array($type, ['meeting','container','document'], true)) {
                        $mentionItems[] = [
                            'type' => $type,
                            'id' => (int) $id,
                            'title' => $m['title'] ?? null,
                        ];
                    }
                }
                // Limitar duplicados conservando el último título conocido
                $byKey = [];
                foreach ($mentionItems as $it) {
                    $k = ($it['type'] ?? '') . ':' . ($it['id'] ?? '');
                    if (!isset($byKey[$k]) || !empty($it['title'])) {
                        $byKey[$k] = $it;
                    }
                }
                $contextData['mention_items'] = array_values($byKey);

                // Guardar en sesión
                $session->context_data = $contextData;
                $session->save();

                // Guardar menciones en el mensaje del usuario para que el frontend pueda mostrarlas
                $userMessage->metadata = array_merge($userMessage->metadata ?? [], [ 'mentions' => $mentions ]);
                $userMessage->save();
            } catch (\Throwable $e) {
                Log::warning('No se pudieron aplicar menciones al contexto', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($isFirstUserMessage) {
            $derivedTitle = $this->deriveSessionTitle($request->content, $session->title);

            if ($derivedTitle && $derivedTitle !== $session->title) {
                $session->title = $derivedTitle;
                $session->save();
            }
        }

        // Si estamos en modo offline, generar una respuesta básica usando solo el contexto
        if ($offline) {
            $contextFragments = $this->gatherContext($session, $request->content);
            $snippet = '';
            if (!empty($contextFragments)) {
                $texts = array_map(fn($f) => $f['text'] ?? '', array_slice($contextFragments, 0, 3));
                $snippet = implode("\n- ", array_filter($texts));
            }
            $content = "(Modo offline) Puedo ayudarte a analizar esta conversación basándome en el contexto disponible.\n\nResumen de contexto:\n- " . ($snippet ?: 'No hay fragmentos de contexto disponibles.') . "\n\nHaz tu pregunta específica y te orientaré con la información encontrada.";

            $assistantMessage = AiChatMessage::create([
                'session_id' => $session->id,
                'role' => 'assistant',
                'content' => $content,
                'metadata' => [
                    'offline' => true,
                ],
            ]);
        } else {
            // Procesar con IA y generar respuesta
            $assistantMessage = $this->processAiResponse($session, $request->content, $request->attachments ?? []);
        }

        // Actualizar actividad de la sesión
        $session->updateActivity();

        $session->refresh();

        $assistantArray = $assistantMessage->toArray();

        return response()->json([
            'success' => true,
            'user_message' => $userMessage->toArray(),
            'assistant_message' => $assistantArray,
            'citations' => $assistantArray['metadata']['citations'] ?? [],
            'session' => [
                'id' => $session->id,
                'title' => $session->title,
                'context_type' => $session->context_type,
                'context_data' => $session->context_data,
                'last_activity' => $session->last_activity,
            ],
        ]);
    }

    /**
     * Obtener contenedores del usuario
     */
    public function getContainers(): JsonResponse
    {
        $user = Auth::user();

        // Incluir contenedores personales y organizacionales donde el usuario es miembro
        $containers = MeetingContentContainer::with('group')
            ->where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('username', $user->username)
                  ->orWhereIn('group_id', function ($sub) use ($user) {
                      $sub->select('id_grupo')
                          ->from('group_user')
                          ->where('user_id', $user->id);
                  });
            })
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($container) {
                return [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                    'created_at' => $container->created_at,
                    'meetings_count' => $container->meetingRelations()->count(),
                    'is_company' => $container->group_id !== null,
                    'group_name' => $container->group->nombre_grupo ?? null,
                ];
            });

        return response()->json([
            'success' => true,
            'containers' => $containers
        ]);
    }

    /**
     * Obtener reuniones del usuario
     */
    public function getMeetings(): JsonResponse
    {
        $user = Auth::user();

        // Reuniones propias
        $own = TranscriptionLaravel::where('username', $user->username)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (TranscriptionLaravel $meeting) {
                return [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'title' => $meeting->meeting_name,
                    'source' => 'transcriptions_laravel',
                    'is_legacy' => true,
                    'is_shared' => false,
                    'shared_by' => null,
                    'created_at' => optional($meeting->created_at)->toIso8601String(),
                    'updated_at' => optional($meeting->updated_at)->toIso8601String(),
                    'has_transcription' => ! empty($meeting->transcript_drive_id),
                    'has_audio' => ! empty($meeting->audio_drive_id),
                    'transcript_drive_id' => $meeting->transcript_drive_id,
                    'transcript_download_url' => $meeting->transcript_download_url,
                    'audio_drive_id' => $meeting->audio_drive_id,
                    'audio_download_url' => $meeting->audio_download_url,
                ];
            });

        // Reuniones compartidas contigo (aceptadas)
        $shared = collect();
        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('shared_meetings')) {
                $shared = \App\Models\SharedMeeting::with(['meeting','sharedBy'])
                    ->accepted()
                    ->forUser($user->id)
                    ->whereHas('meeting')
                    ->orderByDesc('shared_at')
                    ->get()
                    ->map(function($share) {
                        $m = $share->meeting;
                        $by = $share->sharedBy;
                        return [
                            'id' => $m->id,
                            'meeting_name' => $m->meeting_name,
                            'title' => $m->meeting_name,
                            'source' => 'shared',
                            'is_legacy' => true,
                            'is_shared' => true,
                            'shared_by' => $by?->full_name ?? $by?->username ?? 'Usuario',
                            'created_at' => optional($m->created_at)->toIso8601String(),
                            'updated_at' => optional($m->updated_at)->toIso8601String(),
                            'has_transcription' => ! empty($m->transcript_drive_id),
                            'has_audio' => ! empty($m->audio_drive_id),
                            'transcript_drive_id' => $m->transcript_drive_id,
                            'transcript_download_url' => $m->transcript_download_url,
                            'audio_drive_id' => $m->audio_drive_id,
                            'audio_download_url' => $m->audio_download_url,
                        ];
                    });
            }
        } catch (\Throwable $e) {
            // ignorar si la tabla/relación no existe
        }

        // Reuniones provenientes de contenedores accesibles
        $containerMeetings = collect();
        try {
            if (
                Schema::hasTable('meeting_content_relations') &&
                Schema::hasTable('meeting_content_containers')
            ) {
                $relations = MeetingContentRelation::with(['container' => function ($query) {
                        $query->with(['group.organization']);
                    }])
                    ->whereHas('container', function ($containerQuery) use ($user) {
                        $containerQuery->where('is_active', true)
                            ->where(function ($accessQuery) use ($user) {
                                $accessQuery->where('username', $user->username)
                                    ->orWhereIn('group_id', function ($sub) use ($user) {
                                        $sub->select('id_grupo')
                                            ->from('group_user')
                                            ->where('user_id', $user->id);
                                    })
                                    ->orWhereHas('group.organization', function ($orgQuery) use ($user) {
                                        $orgQuery->where('admin_id', $user->id);
                                    });
                            });
                    })
                    ->get();

                $meetingIds = $relations->pluck('meeting_id')->unique()->values();

                if ($meetingIds->isNotEmpty()) {
                    $meetingsById = TranscriptionLaravel::whereIn('id', $meetingIds)->get()->keyBy('id');
                    $byMeeting = [];

                    foreach ($relations as $relation) {
                        $meeting = $meetingsById->get($relation->meeting_id);

                        if (! $meeting || isset($byMeeting[$relation->meeting_id])) {
                            continue;
                        }

                        $container = $relation->container;
                        $containerName = $container?->name ?? 'Contenedor';

                        $byMeeting[$relation->meeting_id] = [
                            'id' => $meeting->id,
                            'meeting_name' => $meeting->meeting_name,
                            'title' => $meeting->meeting_name,
                            'source' => 'container',
                            'is_legacy' => true,
                            'is_shared' => true,
                            'shared_by' => $containerName,
                            'created_at' => optional($meeting->created_at)->toIso8601String(),
                            'updated_at' => optional($meeting->updated_at)->toIso8601String(),
                            'has_transcription' => ! empty($meeting->transcript_drive_id),
                            'has_audio' => ! empty($meeting->audio_drive_id),
                            'transcript_drive_id' => $meeting->transcript_drive_id,
                            'transcript_download_url' => $meeting->transcript_download_url,
                            'audio_drive_id' => $meeting->audio_drive_id,
                            'audio_download_url' => $meeting->audio_download_url,
                        ];
                    }

                    $containerMeetings = collect(array_values($byMeeting));
                }
            }
        } catch (\Throwable $e) {
            // Ignorar si las tablas/relaciones no existen
        }

        // Unir propias + compartidas, eliminar duplicados por id priorizando propias
        $byId = [];
        foreach ($own as $item) { $byId[$item['id']] = $item; }
        foreach ($shared as $item) { if (!isset($byId[$item['id']])) { $byId[$item['id']] = $item; } }
        foreach ($containerMeetings as $item) { if (!isset($byId[$item['id']])) { $byId[$item['id']] = $item; } }
        $meetings = collect(array_values($byId))
            ->sortByDesc(function($m) { return $m['created_at'] ?? null; })
            ->values();

        return response()->json([
            'success' => true,
            'meetings' => $meetings
        ]);
    }

    /**
     * Obtener chats de contactos del usuario
     */
    public function getContactChats(): JsonResponse
    {
        $user = Auth::user();

        $chats = Chat::where(function($query) use ($user) {
                $query->where('user_one_id', $user->id)
                      ->orWhere('user_two_id', $user->id);
            })
            ->with(['userOne', 'userTwo', 'messages' => function($query) {
                $query->latest()->limit(1);
            }])
            ->get()
            ->map(function($chat) use ($user) {
                $otherUser = $chat->user_one_id == $user->id ? $chat->userTwo : $chat->userOne;
                $lastMessage = $chat->messages->first();

                return [
                    'id' => $chat->id,
                    'contact_name' => $otherUser->full_name ?? $otherUser->username,
                    'contact_email' => $otherUser->email,
                    'messages_count' => $chat->messages()->count(),
                    'last_message' => $lastMessage ? [
                        'content' => Str::limit($lastMessage->body ?? '', 100),
                        'created_at' => $lastMessage->created_at
                    ] : null
                ];
            });

        return response()->json([
            'success' => true,
            'chats' => $chats
        ]);
    }

    /**
     * Subir documento para el asistente
     */
    public function uploadDocument(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'file' => 'sometimes|file|max:102400|mimes:pdf,jpg,jpeg,png,xlsx,docx,pptx', // 100MB max, allowed types only
            'files.*' => 'sometimes|file|max:102400|mimes:pdf,jpg,jpeg,png,xlsx,docx,pptx',
            'drive_folder_id' => 'nullable|string',
            'drive_type' => 'sometimes|in:personal,organization',
            'session_id' => 'nullable|integer'
        ]);

        try {
            $driveType = $request->input('drive_type', 'personal');
            $folderId = $request->input('drive_folder_id');
            $sessionId = (int) $request->input('session_id');

            $files = [];
            if ($request->hasFile('files')) {
                $files = $request->file('files');
            } elseif ($request->hasFile('file')) {
                $files = [$request->file('file')];
            }

            if (empty($files)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se proporcionó ningún archivo para subir.'
                ], 422);
            }

            $documents = [];
            foreach ($files as $file) {
                if (! $file) continue;
                $originalName = $file->getClientOriginalName();
                $mimeType = $file->getMimeType();
                $fileSize = $file->getSize();

                $documentType = $this->getDocumentType($mimeType, $originalName);
                $driveResult = $this->uploadToGoogleDrive($file, $folderId, $driveType, $user);

                $document = AiDocument::create([
                    'username' => $user->username,
                    'name' => pathinfo($originalName, PATHINFO_FILENAME),
                    'original_filename' => $originalName,
                    'document_type' => $documentType,
                    'mime_type' => $mimeType,
                    'file_size' => $fileSize,
                    'drive_file_id' => $driveResult['file_id'],
                    'drive_folder_id' => $driveResult['folder_id'],
                    'drive_type' => $driveType,
                    'processing_status' => 'pending',
                    'document_metadata' => array_merge((array) ($driveResult['metadata'] ?? []), [
                        'created_in_session' => $sessionId ? (string) $sessionId : null,
                        'created_via' => 'assistant_upload',
                    ]),
                ]);

                $this->processDocumentInBackground($document);

                if ($sessionId) {
                    $session = AiChatSession::where('id', $sessionId)->where('username', $user->username)->first();
                    if ($session) {
                        $this->associateDocumentToSessionContext($document, $session, $user->username);
                        // Agregar el documento al contexto de la sesión (lista doc_ids)
                        try {
                            $contextData = is_array($session->context_data) ? $session->context_data : [];
                            $docIds = \Illuminate\Support\Arr::get($contextData, 'doc_ids', []);
                            if (!is_array($docIds)) { $docIds = []; }
                            if (!in_array($document->id, $docIds, true)) {
                                $docIds[] = $document->id;
                                $contextData['doc_ids'] = array_values($docIds);
                                $session->context_data = $contextData;
                                $session->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo actualizar doc_ids en el contexto de la sesión', [
                                'session_id' => $session->id,
                                'document_id' => $document->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $documents[] = $document;
            }

            return response()->json([
                'success' => true,
                'documents' => $documents,
                'message' => 'Documento(s) subido(s). Procesando contenido...'
            ]);

        } catch (\Throwable $e) {
            Log::error('Error uploading AI document', [
                'username' => $user->username,
                'drive_type' => $request->drive_type,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Error al subir el documento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener documentos del usuario
     */
    public function getDocuments(): JsonResponse
    {
        $user = Auth::user();

        $documents = AiDocument::byUser($user->username)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn($document) => $this->formatDocumentForResponse($document));

        return response()->json([
            'success' => true,
            'documents' => $documents
        ]);
    }

    /**
     * Esperar hasta que los documentos indicados hayan sido procesados
     */
    public function waitDocuments(Request $request): JsonResponse
    {
        $user = Auth::user();
        $ids = Arr::wrap($request->input('ids', []));
        $timeoutMs = (int) ($request->input('timeout_ms', 12000));
        $pollMs = 400;
        $deadline = microtime(true) + ($timeoutMs / 1000.0);

        $last = collect();
        do {
            $docs = AiDocument::byUser($user->username)
                ->whereIn('id', $ids)
                ->get();
            $last = $docs;
            $allDone = $docs->every(function ($d) {
                return in_array($d->processing_status, ['completed','failed'], true);
            });
            if ($allDone) break;
            usleep($pollMs * 1000);
        } while (microtime(true) < $deadline);

        $documents = $last->map(fn($document) => $this->formatDocumentForResponse($document))->values();

        return response()->json([
            'success' => true,
            'documents' => $documents,
        ]);
    }

    /**
     * Listar archivos dentro de la carpeta "Documentos" de Google Drive
     * Permite visualizar documentos cargados directamente desde Drive y agregarlos al contexto.
     */
    public function listDriveDocuments(Request $request): JsonResponse
    {
        $user = Auth::user();
        $driveType = (string) $request->query('drive_type', 'personal'); // personal | organization
        $search = trim((string) $request->query('search', ''));

        try {
            $context = $this->resolveDriveContext($driveType, $user);
            $this->ensureValidAccessToken($driveType, $context);

            $folderId = $this->ensureDocumentsFolder($driveType, $context);

            // Construir query para listar archivos dentro de la carpeta
            $q = sprintf("'%s' in parents and trashed=false", $folderId);
            if ($search !== '') {
                $escaped = str_replace("'", "\\'", $search);
                $q .= " and name contains '" . $escaped . "'";
            }

            $drive = $this->googleDriveService->getDrive();
            $response = $drive->files->listFiles([
                'q' => $q,
                'fields' => 'files(id,name,mimeType,size,modifiedTime,webViewLink)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
                'orderBy' => 'modifiedTime desc,name',
                'pageSize' => 100,
            ]);

            $files = collect($response->getFiles() ?: [])->map(function ($file) {
                return [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                    'modified_time' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                ];
            })->values();

            return response()->json([
                'success' => true,
                'folder_id' => $folderId,
                'drive_type' => $driveType,
                'files' => $files,
            ]);
        } catch (\Throwable $e) {
            Log::error('Error listing Drive documents for assistant', [
                'drive_type' => $driveType,
                'user' => $user?->username,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron listar los documentos de Drive: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Adjuntar archivos seleccionados desde Drive como documentos del asistente.
     * Si no existen en la BD, se crean y se lanza su procesamiento.
     * Si se envía session_id, se agregan a context_data.doc_ids de esa sesión.
     */
    public function attachDriveDocuments(Request $request): JsonResponse
    {
        $user = Auth::user();
        $validated = $request->validate([
            'drive_file_ids' => 'required|array|min:1',
            'drive_file_ids.*' => 'string',
            'drive_type' => 'sometimes|in:personal,organization',
            'session_id' => 'nullable|integer',
        ]);

        $driveType = (string) ($validated['drive_type'] ?? 'personal');
        $driveIds = array_values(array_unique(array_filter($validated['drive_file_ids'] ?? [], fn($v) => is_string($v) && $v !== '')));
        $sessionId = (int) ($validated['session_id'] ?? 0);

        try {
            $context = $this->resolveDriveContext($driveType, $user);
            $this->ensureValidAccessToken($driveType, $context);

            $attached = [];
            foreach ($driveIds as $fileId) {
                // Buscar si ya existe para este usuario
                $existing = AiDocument::where('username', $user->username)
                    ->where('drive_file_id', $fileId)
                    ->first();

                if ($existing) {
                    $document = $existing;
                } else {
                    // Obtener info del archivo para nombre/mime/size
                    $info = $this->googleDriveService->getFileInfo($fileId);
                    $name = $info->getName() ?: ('Archivo ' . $fileId);
                    $mime = $info->getMimeType() ?: 'application/octet-stream';
                    $size = $info->getSize();

                    $document = AiDocument::create([
                        'username' => $user->username,
                        'name' => pathinfo($name, PATHINFO_FILENAME) ?: $name,
                        'original_filename' => $name,
                        'document_type' => $this->getDocumentType($mime, $name),
                        'mime_type' => $mime,
                        // file_size es NOT NULL en la tabla; usar 0 si Drive no reporta tamaño (p.ej. algunos tipos)
                        'file_size' => $size !== null ? (int) $size : 0,
                        'drive_file_id' => $fileId,
                        'drive_folder_id' => null,
                        'drive_type' => $driveType,
                        'processing_status' => 'pending',
                        'document_metadata' => [
                            'attached_from_drive' => true,
                            'created_in_session' => $sessionId ? (string) $sessionId : null,
                            'created_via' => 'assistant_attach',
                        ],
                    ]);

                    // Lanzar procesamiento (descarga/extracción)
                    $this->processDocumentInBackground($document);
                }

                // Si se envía session_id, agregarlos a doc_ids del contexto
                if ($sessionId) {
                    $session = AiChatSession::where('id', $sessionId)->where('username', $user->username)->first();
                    if ($session) {
                        try {
                            $contextData = is_array($session->context_data) ? $session->context_data : [];
                            $docIds = \Illuminate\Support\Arr::get($contextData, 'doc_ids', []);
                            if (!is_array($docIds)) { $docIds = []; }
                            if (!in_array($document->id, $docIds, true)) {
                                $docIds[] = $document->id;
                                $contextData['doc_ids'] = array_values($docIds);
                                $session->context_data = $contextData;
                                $session->save();
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo actualizar doc_ids al adjuntar desde Drive', [
                                'session_id' => $session->id,
                                'document_id' => $document->id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                }

                $attached[] = $this->formatDocumentForResponse($document);
            }

            return response()->json([
                'success' => true,
                'documents' => $attached,
                'message' => 'Documentos de Drive adjuntados correctamente.'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error attaching Drive documents for assistant', [
                'drive_type' => $driveType,
                'user' => $user?->username,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'No se pudieron adjuntar documentos de Drive: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Estandariza la salida de documentos para el front con etiquetas legibles
     */
    private function formatDocumentForResponse(\App\Models\AiDocument $document): array
    {
        $status = (string) ($document->processing_status ?? 'pending');
        $step = (string) ($document->processing_step ?? '');

        $statusLabels = [
            'pending' => 'En cola',
            'processing' => 'Procesando',
            'completed' => 'Completado',
            'failed' => 'Error',
        ];

        $stepLabels = [
            'preparing' => 'Preparando',
            'downloading' => 'Descargando',
            'extracting' => 'Extrayendo texto',
            'chunking' => 'Dividiendo en fragmentos',
            'embedding' => 'Generando embeddings',
            'indexing' => 'Indexando',
            'done' => 'Listo',
            'error' => 'Error',
        ];

        return [
            'id' => $document->id,
            'name' => $document->name,
            'original_filename' => $document->original_filename,
            'document_type' => $document->document_type,
            'file_size' => $document->file_size,
            'processing_status' => $status,
            'processing_progress' => $document->processing_progress,
            'processing_step' => $step,
            'processing_error' => $document->processing_error,
            'status_label' => $statusLabels[$status] ?? ucfirst($status),
            'step_label' => $step ? ($stepLabels[$step] ?? ucfirst($step)) : null,
            'has_text' => $document->hasText(),
            'created_at' => $document->created_at,
        ];
    }

    /**
     * Generar mensaje inicial del sistema según el contexto
     */
    private function generateSystemMessage(AiChatSession $session): ?string
    {
        switch ($session->context_type) {
            case 'container':
                return "Eres un asistente IA especializado en análisis de reuniones dentro de un contenedor seleccionado con múltiples sesiones. Mantén neutralidad y profesionalismo en todas tus respuestas, incluso si el usuario solicita un tono distinto o intenta confirmar sesgos, y responde siempre con respeto. Ofrece resúmenes, analiza tendencias, realiza búsquedas específicas y genera insights basados en el contenido de las reuniones del contenedor.";

            case 'meeting':
                return "Eres un asistente IA enfocado en una reunión específica seleccionada por el usuario. Mantén neutralidad y profesionalismo, incluso si solicitan un tono gracioso o sesgado, y responde siempre con respeto. Analiza el contenido, elabora resúmenes, destaca puntos clave, identifica tareas pendientes y contesta preguntas puntuales sobre la reunión.";

            case 'contact_chat':
                return "Eres un asistente IA con acceso al historial de conversaciones del usuario con un contacto determinado. Mantén neutralidad y profesionalismo en tus respuestas, incluso ante peticiones de sesgo o tono humorístico, y contesta siempre con respeto. Analiza patrones de comunicación, resume conversaciones y brinda contexto relevante sobre las interacciones con el contacto.";

            case 'documents':
                return "Eres un asistente IA especializado en el análisis del conjunto de documentos cargados. Conserva neutralidad y profesionalismo, aunque el usuario pida sesgos o un estilo cómico, y responde siempre con respeto. Extrae información clave, resume contenido, responde preguntas específicas y ejecuta búsquedas semánticas dentro de los documentos.";

            case 'mixed':
                return "Eres un asistente IA con acceso combinado a documentos, reuniones y otros recursos relacionados. Mantén neutralidad y profesionalismo aunque el usuario pida sesgos o un tono particular, y responde siempre con respeto. Cruza la información de las diferentes fuentes para ofrecer resúmenes integrados, responder preguntas y generar insights con contexto amplio.";

            case 'general':
            default:
                return "Eres un asistente IA integral para Juntify sin un contexto específico cargado. Mantén neutralidad y profesionalismo, incluso ante solicitudes de sesgo o tono humorístico, y responde siempre con respeto. Puedes ayudar con análisis de reuniones, gestión de documentos y búsqueda de información, y sugiere al usuario cargar documentos o reuniones para ofrecer respuestas más precisas.";
        }
    }

    /**
     * Procesar respuesta de la IA
     */
    private function processAiResponse(AiChatSession $session, string $userMessage, array $attachments = []): AiChatMessage
    {
        $contextFragments = $this->gatherContext($session, $userMessage);
        $systemMessage = $this->generateSystemMessage($session);

        /** @var AiChatService $service */
        $service = app(AiChatService::class);
        $reply = $service->generateReply($session, $systemMessage, $contextFragments);

        return AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $reply['content'],
            'metadata' => $reply['metadata'] ?? []
        ]);
    }

    /**
     * Recopilar contexto relevante para la consulta
     */
    private function gatherContext(AiChatSession $session, string $query): array
    {
        $contextFragments = [];

        // Preferencia: usar embeddings solo si está habilitado y disponible; si no, usar MetadataSearch
        $useEmbeddings = (bool) env('AI_ASSISTANT_USE_EMBEDDINGS', false);
        $apiKey = OpenAiConfig::apiKey();
        // Doc IDs explícitamente agregados al contexto de la sesión
        $explicitDocIds = [];
        try {
            $data = is_array($session->context_data) ? $session->context_data : [];
            $ids = Arr::get($data, 'doc_ids', []);
            if (is_array($ids)) {
                $explicitDocIds = array_values(array_unique(array_map('intval', array_filter($ids, fn($v) => is_numeric($v)))));
            }
        } catch (\Throwable $e) {}
        if ($useEmbeddings && !empty($apiKey)) {
            /** @var EmbeddingSearch $search */
            $search = app(EmbeddingSearch::class);
            try {
                $semanticLimit = $session->context_type === 'container'
                    ? (int) env('AI_ASSISTANT_SEMANTIC_LIMIT_CONTAINER', 20)
                    : 8;
                $options = [
                    'session' => $session,
                    'limit' => $semanticLimit,
                ];
                if (!empty($explicitDocIds)) {
                    $options['content_types'] = ['document_text'];
                    $options['content_ids'] = ['document_text' => $explicitDocIds];
                }
                $contextFragments = $search->search($session->username, $query, $options);
            } catch (\Throwable $e) {
                Log::warning('EmbeddingSearch failed, falling back to metadata search', [
                    'error' => $e->getMessage(),
                ]);
                $contextFragments = [];
            }
        }

        if (empty($contextFragments)) {
            /** @var \App\Services\MetadataSearch $meta */
            $meta = app(\App\Services\MetadataSearch::class);
            $metaLimit = $session->context_type === 'container' ? 20 : 8;
            $metaOptions = [
                'session' => $session,
                'limit' => $metaLimit,
            ];
            if (!empty($explicitDocIds)) {
                $metaOptions['doc_ids'] = $explicitDocIds;
            }
            $contextFragments = $meta->search($session->username, $query, $metaOptions);
        }

        // Si hay doc_ids explícitos, garantizamos incluir fragmentos de documentos de forma directa
        $additional = [];
        if (!empty($explicitDocIds)) {
            try {
                $docSession = $this->createVirtualSession($session, 'documents', null, $explicitDocIds);
                $docFrags = $this->buildDocumentContextFragments($docSession);
                if (!empty($docFrags)) {
                    $additional = array_merge($additional, $docFrags);
                }
            } catch (\Throwable $e) {
                Log::info('gatherContext: no se pudieron agregar fragmentos directos de documentos', ['error' => $e->getMessage()]);
            }
        }

        // Mezcla de fragmentos según el tipo de contexto y doc_ids explícitos
        // Regla: si hay doc_ids explícitos y el contexto es 'mixed', priorizamos documentos
        // pero también añadimos fragmentos de reuniones/contenedores seleccionados para no perder cobertura.
        if (!empty($explicitDocIds) && $session->context_type === 'mixed') {
            $additional = array_merge($additional, $this->buildMixedContextFragments($session, $query));
        } elseif (empty($explicitDocIds)) {
            switch ($session->context_type) {
                case 'container':
                    $additional = array_merge($additional, $this->buildContainerContextFragments($session, $query));
                    break;

                case 'meeting':
                    $additional = array_merge($additional, $this->buildMeetingContextFragments($session, $query));
                    break;

                case 'documents':
                    $additional = array_merge($additional, $this->buildDocumentContextFragments($session));
                    break;

                case 'contact_chat':
                    $additional = array_merge($additional, $this->buildChatContextFragments($session));
                    break;

                case 'mixed':
                    $additional = array_merge($additional, $this->buildMixedContextFragments($session, $query));
                    break;
            }
        }

        // Si hay documentos explícitos, no añadimos resúmenes/overviews para evitar ruido.

        // Añadir fragmentos derivados de menciones explícitas (meetings, containers, documents)
        try {
            $mentionItems = [];
            $data = is_array($session->context_data) ? $session->context_data : [];
            $items = Arr::get($data, 'mention_items', []);
            if (is_array($items) && !empty($items)) {
                $mentionItems = $items;
            }

            if (!empty($mentionItems)) {
                foreach ($mentionItems as $it) {
                    if (!is_array($it)) continue;
                    $type = $it['type'] ?? null;
                    $contextId = $it['id'] ?? null;
                    if (!$type || $contextId === null || $contextId === '') continue;
                    switch ($type) {
                        case 'meeting':
                            $meetingSession = $this->createVirtualSession($session, 'meeting', $contextId);
                            $additional = array_merge($additional, $this->buildMeetingContextFragments($meetingSession, $query));
                            break;
                        case 'container':
                            $containerSession = $this->createVirtualSession($session, 'container', $contextId);
                            $additional = array_merge($additional, $this->buildContainerContextFragments($containerSession, $query));
                            break;
                        case 'document':
                            // Para documentos, reutilizamos el builder de documentos creando una sesión virtual
                            $docSession = $this->createVirtualSession($session, 'documents', null, [ (int) $contextId ]);
                            $additional = array_merge($additional, $this->buildDocumentContextFragments($docSession));
                            break;
                    }
                }
            }
        } catch (\Throwable $e) {
            // Si algo falla al construir fragmentos por menciones, continuar sin bloquear
            Log::info('gatherContext: menciones ignoradas por error', [ 'error' => $e->getMessage() ]);
        }

        // Añadir fragmentos de documentos si el usuario se refiere a "imagen", "pdf", "documento", etc.
        try {
            [$requestedTypes, $isGenericDocs] = $this->detectDocumentTypesFromQuery($query);
            if ($isGenericDocs || !empty($requestedTypes)) {
                $docFrags = $this->buildDocumentFragmentsByTypesOrAll($session, $requestedTypes, 12);
                if (!empty($docFrags['list'])) {
                    $additional[] = $docFrags['list'];
                }
                if (!empty($docFrags['fragments'])) {
                    $additional = array_merge($additional, $docFrags['fragments']);
                }
            }
        } catch (\Throwable $e) {
            Log::info('gatherContext: ignorando doc-type inferidos por error', ['error' => $e->getMessage()]);
        }

        return array_values(array_merge($contextFragments, $additional));
    }

    /**
     * Detectar tipos de documento mencionados en la consulta del usuario.
     * Retorna [array<string> $types, bool $isGenericDocs]
     */
    private function detectDocumentTypesFromQuery(string $query): array
    {
        $q = Str::lower($query);
        // Normalizar algunos errores frecuentes
        $q = str_replace([' psf ', 'psf', 'pdfs'], [' pdf ', 'pdf', 'pdf'], $q);

        $map = [
            'image' => ['imagen', 'foto', 'png', 'jpg', 'jpeg', 'gif', 'bmp', 'logo', 'icono'],
            'pdf' => ['pdf'],
            'word' => ['word', 'doc', 'docx'],
            'excel' => ['excel', 'xls', 'xlsx', 'hoja de cálculo', 'hoja de calculo'],
            'powerpoint' => ['powerpoint', 'ppt', 'pptx', 'presentación', 'presentacion'],
            'text' => ['txt', 'texto', 'plain text'],
        ];

        $genericTokens = ['documento', 'documentos', 'archivo', 'archivos', 'fichero', 'ficheros'];

        $types = [];
        foreach ($map as $type => $tokens) {
            foreach ($tokens as $t) {
                if (Str::contains($q, $t)) { $types[$type] = true; break; }
            }
        }

        $isGeneric = false;
        foreach ($genericTokens as $gt) {
            if (Str::contains($q, $gt)) { $isGeneric = true; break; }
        }

        return [array_keys($types), $isGeneric];
    }

    /**
     * Construir fragmentos de contexto para documentos por tipo(s) o todos.
     * Retorna ['list' => fragmento resumen listado, 'fragments' => array de document_overview]
     */
    private function buildDocumentFragmentsByTypesOrAll(AiChatSession $session, array $types = [], int $limit = 12): array
    {
        $user = Auth::user();
        if (!$user) { return ['list' => null, 'fragments' => []]; }

        $query = \App\Models\AiDocument::byUser($session->username)->orderByDesc('created_at');
        if (!empty($types)) {
            $query->whereIn('document_type', $types);
        }
        $docs = $query->limit(max(1, $limit))->get();
        if ($docs->isEmpty()) { return ['list' => null, 'fragments' => []]; }

        $docIds = $docs->pluck('id')->map(fn($v) => (int) $v)->values()->all();

        // Construir listado legible
        $lines = [];
        foreach ($docs as $d) {
            $name = $d->name ?: ($d->original_filename ?: ('Documento ' . $d->id));
            $lines[] = sprintf('- %s (id %d, tipo %s)', $name, $d->id, $d->document_type);
        }
        $title = !empty($types) ? ('Documentos disponibles (' . implode(', ', $types) . ')') : 'Documentos disponibles';
        $listFragment = [
            'text' => $title . ":\n" . implode("\n", $lines),
            'source_id' => 'documents:list',
            'content_type' => 'documents_list',
            'location' => [ 'type' => 'documents' ],
            'similarity' => null,
            'citation' => 'docs:list',
            'metadata' => [ 'count' => count($docIds), 'types' => $types ],
        ];

        // Reutilizar builder de documentos creando una sesión virtual con doc_ids
        $docSession = $this->createVirtualSession($session, 'documents', null, $docIds);
        $docFragments = $this->buildDocumentContextFragments($docSession);

        return [ 'list' => $listFragment, 'fragments' => $docFragments ];
    }

    private function deriveSessionTitle(string $message, ?string $currentTitle = null): ?string
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', strip_tags($message)));

        if ($normalized === '') {
            return $currentTitle ?: null;
        }

        $title = Str::ucfirst(Str::words($normalized, 8, '…'));

        return Str::limit($title, 60, '…');
    }

    private function buildMixedContextFragments(AiChatSession $session, string $query): array
    {
        $items = Arr::get($session->context_data ?? [], 'items', []);

        if (! is_array($items) || empty($items)) {
            return [];
        }

        $fragments = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $type = $item['type'] ?? null;
            $contextId = $item['id'] ?? null;

            if (! $type || $contextId === null || $contextId === '') {
                continue;
            }

            switch ($type) {
                case 'meeting':
                    $meetingSession = $this->createVirtualSession($session, 'meeting', $contextId);
                    $fragments = array_merge($fragments, $this->buildMeetingContextFragments($meetingSession, $query));
                    break;

                case 'container':
                    $containerSession = $this->createVirtualSession($session, 'container', $contextId);
                    $fragments = array_merge($fragments, $this->buildContainerContextFragments($containerSession, $query));
                    break;

                case 'documents':
                case 'document':
                    $documentSession = $this->createVirtualSession($session, 'documents', null, [$contextId]);
                    $fragments = array_merge($fragments, $this->buildDocumentContextFragments($documentSession));
                    break;

                case 'contact_chat':
                case 'chat':
                    $chatSession = $this->createVirtualSession($session, 'contact_chat', $contextId);
                    $fragments = array_merge($fragments, $this->buildChatContextFragments($chatSession));
                    break;
            }
        }

        return $fragments;
    }

    private function createVirtualSession(AiChatSession $base, string $type, $contextId = null, $contextData = []): AiChatSession
    {
        $virtual = AiChatSession::make([
            'username' => $base->username,
            'context_type' => $type,
            'context_id' => $contextId,
            'context_data' => $contextData,
        ]);

        $virtual->id = $base->id;

        return $virtual;
    }

    private function buildContainerContextFragments(AiChatSession $session, string $query = ''): array
    {
        $fragments = [];

        if ($session->context_id) {
            $user = Auth::user();
            $container = MeetingContentContainer::withCount(['meetings'])
                ->with(['group', 'group.organization', 'meetings' => function ($q) {
                    $q->orderByDesc('created_at');
                }])
                ->where('id', $session->context_id)
                ->where('is_active', true)
                ->first();

            if ($container) {
                // Verificar permisos: creador, miembro del grupo o dueño de la organización
                $isCreator = $container->username === $session->username;
                $isMember = $container->group_id
                    ? DB::table('group_user')
                        ->where('id_grupo', $container->group_id)
                        ->where('user_id', $user->id)
                        ->exists()
                    : false;
                $isOrgOwner = $container->group && $container->group->organization
                    ? $container->group->organization->admin_id === $user->id
                    : false;

                if (!($isCreator || $isMember || $isOrgOwner)) {
                    return [];
                }

                $fragments[] = [
                    'text' => sprintf(
                        'Contenedor "%s" con %d reuniones registradas.',
                        $container->name,
                        (int)($container->meetings_count ?? $container->meetings()->count())
                    ),
                    'source_id' => 'container:' . $container->id,
                    'content_type' => 'container_overview',
                    'location' => array_filter([
                        'type' => 'container',
                        'container_id' => $container->id,
                        'name' => $container->name,
                        'description' => $container->description,
                    ]),
                    'similarity' => null,
                    'citation' => 'container:' . $container->id,
                    'metadata' => [],
                ];

                // Lista consolidada de reuniones del contenedor (ayuda a respuestas "todas las reuniones")
                try {
                    $lines = [];
                    foreach ($container->meetings as $m) {
                        $date = optional($m->created_at)->toDateString() ?? 'sin fecha';
                        $name = $m->meeting_name ?: ('Reunión #' . $m->id);
                        $lines[] = sprintf('- %s (id %d, %s)%s%s',
                            $name,
                            (int)$m->id,
                            $date,
                            $m->transcript_drive_id ? ', con transcripción' : '',
                            $m->audio_drive_id ? ', con audio' : ''
                        );
                    }
                    if (!empty($lines)) {
                        $fragments[] = [
                            'text' => "Reuniones en el contenedor:\n" . implode("\n", $lines),
                            'source_id' => 'container:' . $container->id . ':meetings_list',
                            'content_type' => 'container_meetings_list',
                            'location' => [ 'type' => 'container', 'container_id' => $container->id, 'name' => $container->name ],
                            'similarity' => null,
                            'citation' => 'container:' . $container->id . ' meetings',
                            'metadata' => [ 'count' => count($lines) ],
                        ];
                    }
                } catch (\Throwable $e) { /* ignore list build errors */ }

                // -------------------------------------------------------------
                //  DETECCIÓN DE ENFOQUE ESPECÍFICO A UNA REUNIÓN (robustecida)
                //  Patrones soportados:
                //  [#Kualifin #1] | Kualifin #1 | Kualifin 1 | reunion 72 | reunión 72 | #Kualifin #1 (sin corchetes)
                //  Implementamos normalización y búsqueda flexible.
                // -------------------------------------------------------------
                $focusedMeetingIds = [];
                $qLower = Str::lower($query);
                if ($query !== '') {
                    $normalizedQuery = Str::of($qLower)
                        ->replace(['[',']','(',')'], ' ')
                        ->replace('#', ' #') // separar hashes
                        ->replaceMatches('/\s+/', ' ')
                        ->trim();
                    foreach ($container->meetings as $m) {
                        $nameLower = Str::lower((string)$m->meeting_name);
                        $baseName = trim(Str::of($nameLower)->replace('#','')->replaceMatches('/\s+/', ' '));
                        $idPattern = (int)$m->id;
                        $patterns = [
                            '/' . preg_quote($nameLower,'/') . '\\s*#' . preg_quote((string)$m->id,'/') . '\\b/u',
                            '/' . preg_quote($baseName,'/') . '\\s*#?' . preg_quote((string)$m->id,'/') . '\\b/u',
                            '/#' . preg_quote($baseName,'/') . '\\s*#?' . preg_quote((string)$m->id,'/') . '\\b/u',
                            '/reuni[oó]n\\s*' . preg_quote((string)$m->id,'/') . '\\b/u',
                        ];
                        foreach ($patterns as $p) {
                            if (preg_match($p, $normalizedQuery)) { $focusedMeetingIds[] = (int)$m->id; break; }
                        }
                    }
                    $focusedMeetingIds = array_values(array_unique($focusedMeetingIds));
                }

                // -------------------------------------------------------------
                //  CONFIGURACIÓN DE LÍMITES
                // -------------------------------------------------------------
                $totalLimit = (int) config('ai_assistant.context.container.max_total_fragments', 240); // límite global
                $perMeetingLimit = (int) config('ai_assistant.context.container.per_meeting_limit', 8); // por reunión
                $detailQuery = Str::contains($qLower, ['detalles', 'detalle', 'de que se hablo', 'de qué se habló', 'de que se habló', 'que se hablo', 'qué se habló']);
                if (count($focusedMeetingIds) === 1) {
                    // Cuando solo preguntan por una reunión específica
                    $perMeetingLimit = $detailQuery ? 40 : 20; // más amplio si pide detalles
                }
                $tasksWanted = Str::contains($qLower, ['tarea', 'tareas', 'task', 'tasks', 'pendiente', 'pendientes', 'asignado']);
                $tasksPerMeetingLimit = $tasksWanted ? 10 : 3;

                // Selección de conjunto de reuniones a iterar
                $meetingsToIterate = $container->meetings; // por defecto TODAS las reuniones del contenedor
                if (!empty($focusedMeetingIds)) {
                    $meetingsToIterate = $container->meetings->filter(fn($m) => in_array((int)$m->id, $focusedMeetingIds))->values();
                }

                // -------------------------------------------------------------
                //  AGREGADO DE RESUMENES DE TODAS LAS REUNIONES (consulta global)
                //  Si la consulta es general (no enfocada a 1 sola) y pide "de qué se habló"
                // -------------------------------------------------------------
                $wantsGlobal = empty($focusedMeetingIds) && (
                    Str::contains($qLower, ['de que se hablo', 'de qué se habló', 'todas las reuniones', 'resumen general', 'que se hablo en el contenedor'])
                );
                if ($wantsGlobal || config('ai_assistant.context.container.always_aggregate_all_meetings', true)) {
                    // Construir bloques agregados usando el cache de TODAS las reuniones
                    try {
                        /** @var MeetingJuCacheService $cache */
                        $cache = app(MeetingJuCacheService::class);
                        $summaryLines = [];
                        $allKeyPoints = [];
                        $allTasks = [];
                        $aggregatedTaskLines = [];
                        $stats = [
                            'total_meetings' => 0,
                            'total_segments' => 0,
                            'total_key_points' => 0,
                            'total_tasks' => 0,
                        ];

                        foreach ($container->meetings as $m) {
                            $cached = $cache->getCachedParsed((int)$m->id);
                            if (!is_array($cached)) { continue; }
                            $stats['total_meetings']++;
                            $segmentsCount = is_countable($cached['segments'] ?? null) ? count($cached['segments']) : 0;
                            $kpCount = is_countable($cached['key_points'] ?? null) ? count($cached['key_points']) : 0;
                            $tasksCount = is_countable($cached['tasks'] ?? null) ? count($cached['tasks']) : 0;
                            $stats['total_segments'] += $segmentsCount;
                            $stats['total_key_points'] += $kpCount;
                            if (!empty($cached['summary'])) {
                                $summaryLines[] = $m->meeting_name . ': ' . Str::limit(trim((string)$cached['summary']), 800);
                            }
                            // Agregar key points (limitados) con prefijo del nombre de la reunión
                            if ($kpCount) {
                                $take = min(5, $kpCount); // limitar por reunión
                                foreach (array_slice($cached['key_points'], 0, $take) as $kp) {
                                    $kpTxt = is_array($kp) ? ($kp['text'] ?? ($kp['point'] ?? json_encode($kp))) : (string)$kp;
                                    $kpTxt = Str::limit(trim($kpTxt), 300);
                                    if ($kpTxt !== '') {
                                        $allKeyPoints[] = $m->meeting_name . ': ' . $kpTxt;
                                    }
                                }
                            }
                            // Agregar tasks (si existen) — tareas vienen vacías en tu dataset actual pero dejamos la lógica
                            if ($tasksCount) {
                                $takeT = min(5, $tasksCount);
                                foreach (array_slice($cached['tasks'], 0, $takeT) as $task) {
                                    if (is_array($task)) {
                                        $title = $task['tarea'] ?? $task['title'] ?? $task['name'] ?? json_encode($task);
                                    } else { $title = (string)$task; }
                                    $title = Str::limit(trim((string)$title), 160);
                                    if ($title === '') { continue; }
                                    $line = $m->meeting_name . ': ' . $title;
                                    $hash = md5($line);
                                    if (!isset($aggregatedTaskLines[$hash])) {
                                        $aggregatedTaskLines[$hash] = $line;
                                        $stats['total_tasks']++;
                                    }
                                }
                            }

                            // Incluir tareas persistidas en BD como respaldo consolidado
                            try {
                                $dbTasks = TaskLaravel::where('meeting_id', $m->id)
                                    ->orderByDesc('updated_at')
                                    ->limit(5)
                                    ->get();
                            } catch (\Throwable $taskFetchException) {
                                $dbTasks = collect();
                            }

                            if ($dbTasks->isNotEmpty()) {
                                foreach ($dbTasks as $taskModel) {
                                    $title = Str::limit(trim((string)$taskModel->tarea), 160);
                                    if ($title === '') {
                                        $title = 'Tarea sin título';
                                    }

                                    $metaBits = [];
                                    if ($taskModel->asignado) { $metaBits[] = 'Asignado: ' . $taskModel->asignado; }
                                    if ($taskModel->prioridad) { $metaBits[] = 'Prioridad: ' . $taskModel->prioridad; }
                                    if ($taskModel->fecha_inicio) { $metaBits[] = 'Inicio: ' . $taskModel->fecha_inicio; }
                                    if ($taskModel->fecha_limite) { $metaBits[] = 'Vence: ' . $taskModel->fecha_limite; }
                                    if ($taskModel->hora_limite) { $metaBits[] = 'Hora: ' . $taskModel->hora_limite; }
                                    $metaTxt = empty($metaBits) ? '' : ' [' . implode(' | ', $metaBits) . ']';

                                    $description = $taskModel->descripcion
                                        ? ' — ' . Str::limit(trim((string)$taskModel->descripcion), 200)
                                        : '';

                                    $line = $m->meeting_name . ': ' . $title . $metaTxt . $description;
                                    $hash = 'db:' . ($taskModel->id ?? md5($line));
                                    if (!isset($aggregatedTaskLines[$hash])) {
                                        $aggregatedTaskLines[$hash] = $line;
                                        $stats['total_tasks']++;
                                    }
                                }
                            }
                        }

                        if (!empty($aggregatedTaskLines)) {
                            $allTasks = array_values($aggregatedTaskLines);
                        }

                        if (!empty($summaryLines)) {
                            $fragments[] = [
                                'text' => "Resumen agregado de reuniones (" . $stats['total_meetings'] . " reuniones):\n" . implode("\n\n", $summaryLines),
                                'source_id' => 'container:' . $container->id . ':aggregated_summaries',
                                'content_type' => 'container_meetings_aggregated',
                                'location' => ['type' => 'container', 'container_id' => $container->id, 'aggregated' => true],
                                'similarity' => null,
                                'citation' => 'container:' . $container->id . ' resumenes',
                                'metadata' => ['aggregated' => true, 'count' => count($summaryLines), 'stats' => $stats],
                            ];
                        }
                        if (!empty($allKeyPoints)) {
                            $fragments[] = [
                                'text' => "Puntos clave destacados (primeros por reunión):\n" . implode("\n", $allKeyPoints),
                                'source_id' => 'container:' . $container->id . ':aggregated_key_points',
                                'content_type' => 'container_key_points_aggregated',
                                'location' => ['type' => 'container', 'container_id' => $container->id, 'aggregated' => true],
                                'similarity' => null,
                                'citation' => 'container:' . $container->id . ' keypoints',
                                'metadata' => ['aggregated' => true, 'count' => count($allKeyPoints)],
                            ];
                        }
                        if (!empty($allTasks)) {
                            $fragments[] = [
                                'text' => "Tareas detectadas (limitadas):\n" . implode("\n", $allTasks),
                                'source_id' => 'container:' . $container->id . ':aggregated_tasks',
                                'content_type' => 'container_tasks_aggregated',
                                'location' => ['type' => 'container', 'container_id' => $container->id, 'aggregated' => true],
                                'similarity' => null,
                                'citation' => 'container:' . $container->id . ' tareas',
                                'metadata' => ['aggregated' => true, 'count' => count($allTasks)],
                            ];
                        }
                        // Estadísticas compactas
                        if ($stats['total_meetings'] > 0) {
                            $fragments[] = [
                                'text' => sprintf('Estadísticas: %d reuniones, %d segmentos, %d puntos clave, %d tareas.',
                                    $stats['total_meetings'], $stats['total_segments'], $stats['total_key_points'], $stats['total_tasks']
                                ),
                                'source_id' => 'container:' . $container->id . ':aggregated_stats',
                                'content_type' => 'container_stats',
                                'location' => ['type' => 'container', 'container_id' => $container->id],
                                'similarity' => null,
                                'citation' => 'container:' . $container->id . ' stats',
                                'metadata' => $stats,
                            ];
                        }
                    } catch (\Throwable $e) { /* silencioso */ }
                }

                // -------------------------------------------------------------
                //  RECORRER REUNIONES SELECCIONADAS
                // -------------------------------------------------------------
                // Si se indica distribución equitativa, recolectar por reunión y luego mezclar round-robin
                $distribute = (bool) config('ai_assistant.context.container.distribute_evenly_across_meetings', true);
                $perMeetingBatches = [];
                foreach ($meetingsToIterate as $meeting) {
                    $meetingFragments = [];

                    // Breve ficha de la reunión como contexto estructural
                    $meetingFragments[] = [
                        'text' => sprintf(
                            'Reunión "%s" (%s). Recursos: %s transcripción, %s audio.',
                            $meeting->meeting_name,
                            optional($meeting->created_at)->toDateString() ?? 'sin fecha',
                            $meeting->transcript_drive_id ? 'con' : 'sin',
                            $meeting->audio_drive_id ? 'con' : 'sin'
                        ),
                        'source_id' => 'meeting:' . $meeting->id,
                        'content_type' => 'container_meeting',
                        'location' => $this->buildLegacyMeetingLocation($meeting),
                        'similarity' => null,
                        'citation' => 'meeting:' . $meeting->id,
                        'metadata' => $this->buildLegacyMeetingMetadata($meeting),
                    ];

                    // Si estamos enfocados a una sola reunión, o la consulta pide detalles explícitos, usamos query vacío (sin filtrado)
                    $effectiveQuery = (count($focusedMeetingIds) === 1 || $detailQuery) ? '' : $query;
                    $juFragments = $this->buildFragmentsFromJu($meeting, $effectiveQuery);
                    if (!empty($juFragments)) {
                        // Recortar por reunión si fuera necesario
                        $slice = array_slice($juFragments, 0, $perMeetingLimit);
                        $meetingFragments = array_merge($meetingFragments, $slice);
                        // Si pidió detalles y existe un summary completo no truncado disponible, asegurar que esté incluido primero
                        if ($detailQuery) {
                            $fullSummary = collect($juFragments)->firstWhere('content_type', 'meeting_summary_full');
                            if ($fullSummary) {
                                // Reordenar para ponerlo al inicio detrás de la ficha
                                // Mantener única aparición
                                $meetingFragments = array_values(array_unique($meetingFragments, SORT_REGULAR));
                            }
                        }
                    } else {
                        // Intentar Fallback: traer al menos los primeros 5 segmentos crudos del cache si existen
                        try {
                            /** @var \App\Services\MeetingJuCacheService $cache */
                            $cache = app(\App\Services\MeetingJuCacheService::class);
                            $cached = $cache->getCachedParsed((int)$meeting->id);
                            if (is_array($cached) && !empty($cached['segments']) && is_array($cached['segments'])) {
                                $rawSegs = array_slice($cached['segments'], 0, 5);
                                foreach ($rawSegs as $seg) {
                                    $txt = (string)($seg['text'] ?? '');
                                    if ($txt === '') continue;
                                    $speaker = $seg['speaker'] ?? $seg['display_speaker'] ?? 'Participante';
                                    $time = $seg['start'] ?? $seg['time'] ?? null;
                                    $meetingFragments[] = [
                                        'text' => $speaker . ': ' . $txt,
                                        'source_id' => 'meeting:' . $meeting->id . ':segment:fallback:' . ($time ?? uniqid()),
                                        'content_type' => 'meeting_transcription_segment_fallback',
                                        'location' => $this->buildLegacyMeetingLocation($meeting, [
                                            'section' => 'transcription',
                                            'speaker' => $speaker,
                                            'timestamp' => $time,
                                            'fallback' => true,
                                        ]),
                                        'similarity' => null,
                                        'citation' => 'meeting:' . $meeting->id . ' t.' . ($time ? $this->formatTimeForCitation($time) : '—'),
                                        'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                                            'transcription_segment' => true,
                                            'timestamp' => $time,
                                            'speaker' => $speaker,
                                            'source' => 'ju',
                                            'fallback' => true,
                                        ]),
                                    ];
                                }
                            } else {
                                // Agregar fragmento diagnóstico si sabemos que hay cache meta (summary length o counts) pero no se generaron fragmentos
                                if (is_array($cached) && (isset($cached['summary']) || isset($cached['segments']))) {
                                    $meetingFragments[] = [
                                        'text' => 'Diagnóstico: Se encontró cache .ju para la reunión pero no se pudieron generar fragmentos filtrados (posible filtrado por keywords o formato inesperado).',
                                        'source_id' => 'meeting:' . $meeting->id . ':diagnostic:ju',
                                        'content_type' => 'diagnostic',
                                        'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'diagnostic']),
                                        'similarity' => null,
                                        'citation' => 'meeting:' . $meeting->id . ' diag',
                                        'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['diagnostic' => true]),
                                    ];
                                }
                            }
                        } catch (\Throwable $e) {
                            // ignorar
                        }
                        // Fallback mínimo cuando no hay .ju utilizable
                        if (! empty($meeting->transcript_download_url)) {
                            $meetingFragments[] = [
                                'text' => sprintf('Transcripción disponible en: %s', $meeting->transcript_download_url),
                                'source_id' => 'meeting:' . $meeting->id . ':transcript',
                                'content_type' => 'meeting_transcript_link',
                                'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'transcript_link']),
                                'similarity' => null,
                                'citation' => 'meeting:' . $meeting->id . ' transcript',
                                'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['resource' => 'transcript']),
                            ];
                        }
                        if (! empty($meeting->audio_download_url)) {
                            $meetingFragments[] = [
                                'text' => sprintf('Audio disponible en: %s', $meeting->audio_download_url),
                                'source_id' => 'meeting:' . $meeting->id . ':audio',
                                'content_type' => 'meeting_audio_link',
                                'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'audio_link']),
                                'similarity' => null,
                                'citation' => 'meeting:' . $meeting->id . ' audio',
                                'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['resource' => 'audio']),
                            ];
                        }
                    }

                    // Incluir tareas de la reunión desde tasks_laravel (limitadas si el usuario no las pide explícitamente)
                    try {
                        if ($tasksPerMeetingLimit > 0) {
                            // Traer TODAS las tareas de la reunión (ya que el usuario tiene acceso a la reunión)
                            $tasks = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
                                ->orderByDesc('updated_at')
                                ->limit($tasksPerMeetingLimit)
                                ->get();
                            foreach ($tasks as $t) {
                                $desc = $t->descripcion ? ("\n" . trim((string)$t->descripcion)) : '';
                                $metaBits = [];
                                if ($t->prioridad) { $metaBits[] = 'prioridad ' . $t->prioridad; }
                                if ($t->fecha_inicio) { $metaBits[] = 'inicio ' . $t->fecha_inicio; }
                                if ($t->fecha_limite) { $metaBits[] = 'vence ' . $t->fecha_limite; }
                                if ($t->hora_limite) { $metaBits[] = 'hora ' . $t->hora_limite; }
                                if ($t->asignado) { $metaBits[] = 'asignado a ' . $t->asignado; }
                                $metaTxt = empty($metaBits) ? '' : (' (' . implode('; ', $metaBits) . ')');
                                $meetingFragments[] = [
                                    'text' => '- ' . trim((string)$t->tarea) . $metaTxt . $desc,
                                    'source_id' => 'meeting:' . $meeting->id . ':task:' . $t->id,
                                    'content_type' => 'meeting_task',
                                    'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'tasks', 'task_id' => $t->id]),
                                    'similarity' => null,
                                    'citation' => 'task:' . $t->id,
                                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['task_id' => $t->id]),
                                ];
                            }
                        }
                    } catch (\Throwable $e) { /* ignore tasks inclusion */ }

                    if ($distribute) {
                        $perMeetingBatches[] = $meetingFragments;
                    } else {
                        $fragments = array_merge($fragments, $meetingFragments);
                        if (count($fragments) > $totalLimit) {
                            $fragments = array_slice($fragments, 0, $totalLimit);
                        }
                    }
                }

                // Interleaving round-robin para distribuir los fragmentos entre todas las reuniones
                if ($distribute && !empty($perMeetingBatches)) {
                    $indexes = array_fill(0, count($perMeetingBatches), 0);
                    $added = 0;
                    $maxAdded = $totalLimit;
                    while ($added < $maxAdded) {
                        $progress = false;
                        for ($i = 0; $i < count($perMeetingBatches) && $added < $maxAdded; $i++) {
                            $batch = $perMeetingBatches[$i] ?? [];
                            $idx = $indexes[$i] ?? 0;
                            if ($idx < count($batch)) {
                                $fragments[] = $batch[$idx];
                                $indexes[$i] = $idx + 1;
                                $added++;
                                $progress = true;
                            }
                        }
                        if (!$progress) break; // no hay más elementos
                    }
                }
            }
        }

        $relatedMeetings = Arr::wrap($session->context_data ?? []);
        if (empty($fragments) && ! empty($relatedMeetings)) {
            $meetings = TranscriptionLaravel::where('username', $session->username)
                ->whereIn('id', $relatedMeetings)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get();

            foreach ($meetings as $meeting) {
                $fragments[] = [
                    'text' => sprintf(
                        'Reunión "%s" registrada el %s.',
                        $meeting->meeting_name,
                        optional($meeting->created_at)->toDateString() ?? 'sin fecha'
                    ),
                    'source_id' => 'meeting:' . $meeting->id,
                    'content_type' => 'container_meeting',
                    'location' => $this->buildLegacyMeetingLocation($meeting),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id,
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting),
                ];

                // Añadir también algo de contenido .ju cuando sea posible
                $juFragments = $this->buildFragmentsFromJu($meeting, $query);
                if (!empty($juFragments)) {
                    $fragments = array_merge($fragments, array_slice($juFragments, 0, 8));
                }
            }
        }

        return $fragments;
    }

    private function buildMeetingContextFragments(AiChatSession $session, string $query): array
    {
        if (! $session->context_id) {
            return [];
        }
        // Intentar construir contexto SIN depender de tablas transcriptions/key_points
        $loadKeyPoints = Schema::hasTable('key_points');

        $withRelations = [];
        if ($loadKeyPoints) {
            $withRelations['keyPoints'] = fn ($relation) => $relation->ordered()->limit(5);
        }

        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $meeting = $this->getMeetingIfAccessible((int) $session->context_id, $withRelations, $user);

        $fragments = [];

        if ($meeting) {
            if (! $loadKeyPoints && ! $meeting->relationLoaded('keyPoints')) {
                $meeting->setRelation('keyPoints', collect());
            }

            // 1) Prefer .ju file content to avoid DB transcriptions dependency
            $juFragments = $this->buildFragmentsFromJu($meeting, $query);
            if (!empty($juFragments)) {
                $fragments = array_merge($fragments, $juFragments);
            }

            // 2) Add key points if available (table exists)
            foreach ($meeting->keyPoints as $index => $point) {
                $fragments[] = [
                    'text' => $point->point_text,
                    'source_id' => 'meeting:' . $meeting->id . ':keypoint:' . $point->id,
                    'content_type' => 'meeting_key_point',
                    'location' => $this->buildLegacyMeetingLocation($meeting, [
                        'section' => 'key_point',
                        'order' => $point->order_num,
                        'point_id' => $point->id,
                    ]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' punto ' . ($index + 1),
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'key_point' => true,
                        'order' => $point->order_num,
                        'point_id' => $point->id,
                    ]),
                ];
            }

            // 3) Include meeting tasks from tasks_laravel
            try {
                // Todas las tareas de la reunión, no solo las del usuario
                $tasks = \App\Models\TaskLaravel::where('meeting_id', $meeting->id)
                    ->orderByDesc('updated_at')
                    ->limit(30)
                    ->get();
                foreach ($tasks as $t) {
                    $desc = $t->descripcion ? ("\n" . trim((string)$t->descripcion)) : '';
                    $metaBits = [];
                    if ($t->prioridad) { $metaBits[] = 'prioridad ' . $t->prioridad; }
                    if ($t->fecha_inicio) { $metaBits[] = 'inicio ' . $t->fecha_inicio; }
                    if ($t->fecha_limite) { $metaBits[] = 'vence ' . $t->fecha_limite; }
                    if ($t->hora_limite) { $metaBits[] = 'hora ' . $t->hora_limite; }
                    if ($t->asignado) { $metaBits[] = 'asignado a ' . $t->asignado; }
                    $metaTxt = empty($metaBits) ? '' : (' (' . implode('; ', $metaBits) . ')');
                    $fragments[] = [
                        'text' => '- ' . trim((string)$t->tarea) . $metaTxt . $desc,
                        'source_id' => 'meeting:' . $meeting->id . ':task:' . $t->id,
                        'content_type' => 'meeting_task',
                        'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'tasks', 'task_id' => $t->id]),
                        'similarity' => null,
                        'citation' => 'task:' . $t->id,
                        'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['task_id' => $t->id]),
                    ];
                }
            } catch (\Throwable $e) { /* ignore tasks inclusion */ }

            return $fragments;
        }

        $legacy = $this->getMeetingIfAccessible((int) $session->context_id, [], $user);
        if ($legacy) {
            if (! empty($legacy->transcript_download_url)) {
                $fragments[] = [
                    'text' => sprintf('Transcripción disponible en: %s', $legacy->transcript_download_url),
                    'source_id' => 'meeting:' . $legacy->id . ':transcript',
                    'content_type' => 'meeting_transcript_link',
                    'location' => $this->buildLegacyMeetingLocation($legacy, ['section' => 'transcript_link']),
                    'similarity' => null,
                    'citation' => 'meeting:' . $legacy->id . ' transcript',
                    'metadata' => $this->buildLegacyMeetingMetadata($legacy, ['resource' => 'transcript']),
                ];
            }

            if (! empty($legacy->audio_download_url)) {
                $fragments[] = [
                    'text' => sprintf('Audio disponible en: %s', $legacy->audio_download_url),
                    'source_id' => 'meeting:' . $legacy->id . ':audio',
                    'content_type' => 'meeting_audio_link',
                    'location' => $this->buildLegacyMeetingLocation($legacy, ['section' => 'audio_link']),
                    'similarity' => null,
                    'citation' => 'meeting:' . $legacy->id . ' audio',
                    'metadata' => $this->buildLegacyMeetingMetadata($legacy, ['resource' => 'audio']),
                ];
            }
        }

        return $fragments;
    }

    private function getMeetingIfAccessible(int $meetingId, array $withRelations, User $user): ?TranscriptionLaravel
    {
        $query = TranscriptionLaravel::query();

        if (! empty($withRelations)) {
            $query->with($withRelations);
        }

        $meeting = $query->where('id', $meetingId)->first();

        if (! $meeting) {
            return null;
        }

        return $this->userCanAccessMeeting($meeting, $user) ? $meeting : null;
    }

    private function userCanAccessMeeting(TranscriptionLaravel $meeting, User $user): bool
    {
        if ($meeting->username === $user->username) {
            return true;
        }

        try {
            if (Schema::hasTable('shared_meetings')) {
                $hasShare = SharedMeeting::query()
                    ->where('meeting_id', $meeting->id)
                    ->where('shared_with', $user->id)
                    ->where('status', 'accepted')
                    ->exists();

                if ($hasShare) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // ignore share table issues
        }

        try {
            if (
                Schema::hasTable('meeting_content_relations') &&
                Schema::hasTable('meeting_content_containers')
            ) {
                $hasContainerAccess = MeetingContentRelation::query()
                    ->where('meeting_id', $meeting->id)
                    ->whereHas('container', function ($containerQuery) use ($user) {
                        $containerQuery->where('is_active', true)
                            ->where(function ($accessQuery) use ($user) {
                                $accessQuery->where('username', $user->username)
                                    ->orWhereIn('group_id', function ($sub) use ($user) {
                                        $sub->select('id_grupo')
                                            ->from('group_user')
                                            ->where('user_id', $user->id);
                                    })
                                    ->orWhereHas('group.organization', function ($orgQuery) use ($user) {
                                        $orgQuery->where('admin_id', $user->id);
                                    });
                            });
                    })
                    ->exists();

                if ($hasContainerAccess) {
                    return true;
                }
            }
        } catch (\Throwable $e) {
            // ignore container table issues
        }

        return false;
    }

    /**
     * Construye fragmentos en memoria a partir del archivo .ju (en Drive) sin usar la tabla transcriptions
     */
    private function buildFragmentsFromJu(TranscriptionLaravel $meeting, string $query): array
    {
        $fragments = [];

        $fileId = $meeting->transcript_drive_id;
        if (! $fileId) {
            // Intentar localizar el .ju cuando no está seteado en la reunión
            try {
                // Buscar en posibles contenedores vinculados para ubicar el .ju
                $containers = $meeting->containers()->with(['group', 'group.organization'])->get();
                foreach ($containers as $container) {
                    $found = $this->locateJuForMeeting($meeting, $container);
                    if ($found) {
                        $meeting->transcript_drive_id = $found;
                        $meeting->save();
                        $fileId = $found;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // continuar sin bloquear
            }

            if (! $fileId) {
                return $fragments;
            }
        }

        try {
            /** @var MeetingJuCacheService $cache */
            $cache = app(MeetingJuCacheService::class);
            $data = $cache->getCachedParsed((int)$meeting->id);
            if (!is_array($data)) {
                // Descargar y cachear si no existe
                $content = $this->tryDownloadJuContent($meeting);
                if (! is_string($content) || $content === '') {
                    // Si falló la descarga, intentar localizar de nuevo por si cambió el contexto
                    try {
                        $containers = $meeting->containers()->with(['group', 'group.organization'])->get();
                        foreach ($containers as $container) {
                            $found = $this->locateJuForMeeting($meeting, $container);
                            if ($found) {
                                $meeting->transcript_drive_id = $found;
                                $meeting->save();
                                $content = $this->tryDownloadJuContent($meeting);
                                if (is_string($content) && $content !== '') { break; }
                            }
                        }
                    } catch (\Throwable $e) { /* ignore */ }

                    if (! is_string($content) || $content === '') {
                        return $fragments;
                    }
                }
                $parsed = $this->decryptJuFile($content);
                $data = $this->processTranscriptData($parsed['data'] ?? []);
                $cache->setCachedParsed((int)$meeting->id, $data, (string)$meeting->transcript_drive_id, $parsed['raw'] ?? null);
            }

            // -------------------------------------------------
            // Resumen (modo truncado o completo según query)
            // -------------------------------------------------
            $qLower = Str::lower($query);
            $wantsFullSummary = Str::contains($qLower, ['resumen completo', 'resumen detallado', 'resumen extendido', 'summary full', 'full summary']);
            if (! empty($data['summary'])) {
                $summaryText = (string)$data['summary'];
                $fragments[] = [
                    'text' => $wantsFullSummary ? $summaryText : Str::limit($summaryText, 800),
                    'source_id' => 'meeting:' . $meeting->id . ':summary' . ($wantsFullSummary ? ':full' : ''),
                    'content_type' => $wantsFullSummary ? 'meeting_summary_full' : 'meeting_summary',
                    'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'summary', 'full' => $wantsFullSummary]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' resumen',
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['summary' => true, 'full' => $wantsFullSummary]),
                ];
            }

            // Puntos clave
            foreach (($data['key_points'] ?? []) as $idx => $kp) {
                $text = is_array($kp) ? ($kp['text'] ?? ($kp['point'] ?? json_encode($kp))) : (string)$kp;
                if (trim($text) === '') continue;
                $fragments[] = [
                    'text' => $text,
                    'source_id' => 'meeting:' . $meeting->id . ':keypoint:ju:' . ($idx+1),
                    'content_type' => 'meeting_key_point',
                    'location' => $this->buildLegacyMeetingLocation($meeting, [
                        'section' => 'key_point',
                        'order' => $idx + 1,
                    ]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' punto ' . ($idx + 1),
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'key_point' => true,
                        'order' => $idx + 1,
                        'source' => 'ju',
                    ]),
                ];
            }

            // -------------------------------------------------
            // Segmentos (modo focalizado por speaker/tema o estándar)
            // -------------------------------------------------
            $segmentsAll = is_array($data['segments'] ?? null) ? $data['segments'] : [];
            $segmentsSelected = [];
            // Derivar lista de participantes potenciales (speakers distintos)
            $participantsSet = [];
            $participantsNormalizedMap = [];
            foreach ($segmentsAll as $segP) {
                if (!is_array($segP)) continue;
                $sp = $segP['speaker'] ?? $segP['display_speaker'] ?? null;
                if (is_string($sp) && $sp !== '') {
                    $normSp = trim(preg_replace('/\s+/', ' ', $sp));
                    if ($normSp !== '' && mb_strlen($normSp) <= 60) {
                        $participantsSet[$normSp] = true;
                        $normalizedKey = $this->normalizeSpeakerString($normSp);
                        if ($normalizedKey !== '') {
                            $participantsNormalizedMap[$normSp] = $normalizedKey;
                        }
                    }
                }
            }

            // Detección simple de speaker (nombres capitalizados en query) y tema (resto de palabras)
            $speakerCandidate = null;
            $speakerCandidateNormalized = null;
            $speakerCandidateParts = [];

            $normalizedQuery = $this->normalizeSpeakerString($query);
            if (!empty($participantsNormalizedMap) && $normalizedQuery !== '') {
                foreach ($participantsNormalizedMap as $originalName => $normalizedName) {
                    if ($normalizedName !== '' && str_contains($normalizedQuery, $normalizedName)) {
                        $speakerCandidate = $originalName;
                        $speakerCandidateNormalized = $normalizedName;
                        break;
                    }
                }

                if (!$speakerCandidate) {
                    foreach ($participantsNormalizedMap as $originalName => $normalizedName) {
                        $parts = array_filter(explode(' ', $normalizedName), fn ($part) => mb_strlen($part) >= 3);
                        foreach ($parts as $part) {
                            if ($part !== '' && str_contains($normalizedQuery, $part)) {
                                $speakerCandidate = $originalName;
                                $speakerCandidateNormalized = $normalizedName;
                                $speakerCandidateParts = $parts;
                                break 2;
                            }
                        }
                    }
                }
            }

            if (!$speakerCandidate && preg_match('/\b([A-ZÁÉÍÓÚÑ][\p{L}]{2,}(?:\s+[A-ZÁÉÍÓÚÑ][\p{L}]{2,})*)\b/u', $query, $mSp)) {
                $speakerCandidate = trim($mSp[1]);
                $speakerCandidateNormalized = $this->normalizeSpeakerString($speakerCandidate);
            }

            if ($speakerCandidateNormalized && empty($speakerCandidateParts)) {
                $speakerCandidateParts = array_filter(
                    explode(' ', $speakerCandidateNormalized),
                    fn ($part) => mb_strlen($part) >= 3
                );
            }
            $keywords = $this->extractQueryKeywords($query);

            if ($speakerCandidate) {
                // Filtrar segmentos del speaker y, si hay keywords adicionales, que coincidan
                foreach ($segmentsAll as $seg) {
                    $txt = is_array($seg) ? ($seg['text'] ?? '') : '';
                    if (trim($txt) === '') continue;
                    $speaker = $seg['speaker'] ?? $seg['display_speaker'] ?? '';
                    if ($speakerCandidateNormalized) {
                        $speakerNormalized = $this->normalizeSpeakerString((string)$speaker);
                        $matchesSpeaker = $speakerNormalized !== ''
                            && str_contains($speakerNormalized, $speakerCandidateNormalized);
                        if (!$matchesSpeaker && !empty($speakerCandidateParts)) {
                            foreach ($speakerCandidateParts as $part) {
                                if ($part !== '' && str_contains($speakerNormalized, $part)) {
                                    $matchesSpeaker = true;
                                    break;
                                }
                            }
                        }
                        if (!$matchesSpeaker) {
                            continue;
                        }
                    } elseif ($speaker && stripos((string)$speaker, $speakerCandidate) === false) {
                        continue;
                    }
                    $ok = true;
                    foreach ($keywords as $kw) {
                        if (stripos($txt, $kw) === false && stripos((string)$speaker, $kw) === false) { $ok = false; break; }
                    }
                    if ($ok) { $segmentsSelected[] = $seg; }
                }
                // En modo focalizado no limitamos a 5, pero ponemos un máximo alto para evitar excesos
                $segmentsSelected = array_slice($segmentsSelected, 0, 80);
            } else {
                // Modo estándar previo
                $segmentsSelected = array_values(array_filter($segmentsAll, function($seg) use ($keywords) {
                    $txt = is_array($seg) ? ($seg['text'] ?? '') : '';
                    if (trim($txt) === '') return false;
                    if (empty($keywords)) return true;
                    foreach ($keywords as $kw) {
                        if (stripos($txt, $kw) !== false) return true;
                    }
                    return false;
                }));
                $segmentsSelected = array_slice($segmentsSelected, 0, 5);
            }

            foreach ($segmentsSelected as $seg) {
                $txt = (string)($seg['text'] ?? '');
                $speaker = $seg['speaker'] ?? $seg['display_speaker'] ?? 'Participante';
                $time = $seg['start'] ?? $seg['time'] ?? null;
                $fragments[] = [
                    'text' => trim($speaker . ': ' . $txt),
                    'source_id' => 'meeting:' . $meeting->id . ':segment:ju:' . ($time ?? uniqid()),
                    'content_type' => 'meeting_transcription_segment',
                    'location' => $this->buildLegacyMeetingLocation($meeting, [
                        'section' => 'transcription',
                        'speaker' => $speaker,
                        'timestamp' => $time,
                        'focused_speaker' => (bool)$speakerCandidate,
                        'focused_speaker_name' => $speakerCandidate,
                    ]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' t.' . ($time ? $this->formatTimeForCitation($time) : '—'),
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'transcription_segment' => true,
                        'timestamp' => $time,
                        'speaker' => $speaker,
                        'source' => 'ju',
                        'focused_speaker' => (bool)$speakerCandidate,
                        'focused_speaker_name' => $speakerCandidate,
                    ]),
                ];
            }

            // Agregar fragmento de participantes si se detectan
            if (!empty($participantsSet)) {
                $participantsList = array_keys($participantsSet);
                sort($participantsList);
                $fragments[] = [
                    'text' => 'Participantes detectados: ' . implode(', ', $participantsList),
                    'source_id' => 'meeting:' . $meeting->id . ':participants',
                    'content_type' => 'meeting_participants',
                    'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'participants']),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' participantes',
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'participants_count' => count($participantsList),
                        'participants' => $participantsList,
                    ]),
                ];
            }

        } catch (\Throwable $e) {
            Log::warning('buildFragmentsFromJu failed', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $fragments;
    }

    /**
     * Descarga el contenido del .ju con una secuencia tolerante a fallos:
     * 1) Si la reuni f3n pertenece a un contenedor de organizaci f3n que tiene token: usar ese token.
     * 2) Service Account impersonando al due f1o de la reuni f3n (si hay email).
     * 3) Service Account sin impersonate.
     * 4) Token del due f1o.
     * 5) Token del usuario actual.
     */
    private function tryDownloadJuContent(TranscriptionLaravel $meeting): ?string
    {
        $fileId = $meeting->transcript_drive_id;
        if (! $fileId) { return null; }

        // 1) Intentar con token de organizaci f3n de alg fan contenedor vinculado
        try {
            $containers = $meeting->containers()->with(['group.organization.googleToken'])->get();
            foreach ($containers as $container) {
                $org = $container->group?->organization;
                $orgTokenModel = $org?->googleToken;
                if ($orgTokenModel) {
                    try {
                        $tokenData = $this->normalizeOrganizationToken($orgTokenModel);
                        $this->googleDriveService->setAccessToken($tokenData);
                        $content = $this->googleDriveService->downloadFileContent($fileId);
                        if (is_string($content) && $content !== '') {
                            $this->debugJuDownloadSample((int)$meeting->id, 'org_token', $content);
                            return $content;
                        }
                    } catch (\Throwable $eOrg) {
                        // seguir con siguientes metodos
                        Log::info('tryDownloadJuContent: fallo acceso por token de organizacion, continuar', [
                            'meeting_id' => $meeting->id,
                            'error' => $eOrg->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // Continuar con siguientes metodos
        }

    // 2) Service Account con impersonate del dueno
        try {
            /** @var GoogleServiceAccount $sa */
            $sa = app(GoogleServiceAccount::class);
            $owner = $meeting->user()->first();
            if ($owner && !empty($owner->email)) {
                try {
                    $sa->impersonate($owner->email);
                } catch (\Throwable $eImp) {
                    // Si falla impersonate, intentar sin el mas abajo
                }
            }
            $content = $sa->downloadFile($fileId);
            if (is_string($content) && $content !== '') { $this->debugJuDownloadSample((int)$meeting->id, 'sa_impersonate', $content); return $content; }
        } catch (\Throwable $eSaImp) {
            // continuar
        }

        // 3) Service Account sin impersonate
        try {
            /** @var GoogleServiceAccount $saNo */
            $saNo = app(GoogleServiceAccount::class);
            $content = $saNo->downloadFile($fileId);
            if (is_string($content) && $content !== '') { $this->debugJuDownloadSample((int)$meeting->id, 'sa_direct', $content); return $content; }
        } catch (\Throwable $eSa) {
            // continuar
        }

    // 4) Token del dueno (si existe)
        try {
            $owner = $meeting->user()->first();
            $ownerToken = $owner?->googleToken;
            if ($ownerToken && method_exists($ownerToken, 'getTokenArray')) {
                $this->googleDriveService->setAccessToken($ownerToken->getTokenArray());
                $content = $this->googleDriveService->downloadFileContent($fileId);
                if (is_string($content) && $content !== '') { $this->debugJuDownloadSample((int)$meeting->id, 'owner_token', $content); return $content; }
            }
        } catch (\Throwable $eOwner) {
            // continuar
        }

    // 5) Token del usuario actual (si existe)
        try {
            $user = \Illuminate\Support\Facades\Auth::user();
            $userToken = $user?->googleToken;
            if ($userToken && method_exists($userToken, 'getTokenArray')) {
                $this->googleDriveService->setAccessToken($userToken->getTokenArray());
                $content = $this->googleDriveService->downloadFileContent($fileId);
                if (is_string($content) && $content !== '') { $this->debugJuDownloadSample((int)$meeting->id, 'user_token', $content); return $content; }
            }
        } catch (\Throwable $eUser) {
            // continuar
        }

        return null;
    }

    /**
     * Debug helper to preview downloaded .ju content safely.
     * Logs length, a short prefix, and JSON keys if parseable. In non-production, optionally
     * writes a tiny sample file when JU_DEBUG_SAMPLES=true.
     */
    private function debugJuDownloadSample(int $meetingId, string $accessPath, string $content): void
    {
        try {
            $prefix = substr($content, 0, 200);
            $isJson = false; $jsonKeys = [];
            $decoded = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $isJson = true;
                $jsonKeys = array_slice(array_keys($decoded), 0, 10);
            }

            Log::info('ju-download: preview', [
                'meeting_id' => $meetingId,
                'path' => $accessPath,
                'length' => strlen($content),
                'is_json' => $isJson,
                'json_keys' => $jsonKeys,
                'first_200' => $prefix,
            ]);

            $shouldPersist = (bool) (env('JU_DEBUG_SAMPLES', false) && ! app()->environment('production'));
            if ($shouldPersist) {
                $dir = storage_path('logs/ju_samples');
                if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
                $file = $dir . DIRECTORY_SEPARATOR . 'meeting_' . $meetingId . '_' . date('Ymd_His') . '_' . $accessPath . '.txt';
                @file_put_contents($file, $prefix);
            }
        } catch (\Throwable $e) {
            // Swallow any debug-time errors
        }
    }

    private function buildDocumentContextFragments(AiChatSession $session): array
    {
        $documentIds = Arr::wrap($session->context_data ?? []);
        if (empty($documentIds)) {
            return [];
        }

        $documents = AiDocument::whereIn('id', $documentIds)->limit(8)->get();
        $fragments = [];

        foreach ($documents as $document) {
            $title = $document->name ?? $document->original_filename ?? ('Documento ' . $document->id);
            $summary = $document->document_metadata['summary'] ?? null;
            $fallback = $document->extracted_text ? Str::limit($document->extracted_text, 800) : null;
            $text = $summary ?? $fallback;

            if (! $text) {
                continue;
            }

            $fragments[] = [
                'text' => $text,
                'source_id' => 'document:' . $document->id . ':overview',
                'content_type' => 'document_overview',
                'location' => array_filter([
                    'type' => 'document',
                    'document_id' => $document->id,
                    'title' => $title,
                    'url' => $document->drive_file_id ? sprintf('https://drive.google.com/file/d/%s/view', $document->drive_file_id) : null,
                ]),
                'similarity' => null,
                'citation' => 'doc:' . $document->id . ' resumen',
                'metadata' => ['summary' => (bool) $summary],
            ];
        }

        return $fragments;
    }

    private function buildChatContextFragments(AiChatSession $session): array
    {
        if (! $session->context_id) {
            return [];
        }

        $chat = Chat::with(['messages' => function ($query) {
            $query->with('sender')->orderByDesc('created_at')->limit(10);
        }])->find($session->context_id);

        if (! $chat) {
            return [];
        }

        $fragments = [];
        $messages = $chat->messages->sortBy('created_at');

        foreach ($messages as $message) {
            $body = $message->body ?? '';
            if (trim($body) === '') {
                continue;
            }

            $sender = $message->sender?->full_name
                ?? $message->sender?->username
                ?? 'Contacto';

            $fragments[] = [
                'text' => $sender . ': ' . Str::limit($body, 300),
                'source_id' => 'chat:' . $chat->id . ':message:' . $message->id,
                'content_type' => 'chat_message',
                'location' => array_filter([
                    'type' => 'chat',
                    'chat_id' => $chat->id,
                    'message_id' => $message->id,
                    'sender' => $sender,
                    'sent_at' => optional($message->created_at)->toIso8601String(),
                ]),
                'similarity' => null,
                'citation' => 'chat:' . $message->id,
                'metadata' => [],
            ];
        }

        return $fragments;
    }

    private function buildLegacyMeetingLocation(TranscriptionLaravel $meeting, array $extra = []): array
    {
        $base = [
            'type' => 'meeting',
            'meeting_id' => $meeting->id,
            'title' => $meeting->meeting_name,
            'source' => 'transcriptions_laravel',
            'created_at' => optional($meeting->created_at)->toIso8601String(),
            'updated_at' => optional($meeting->updated_at)->toIso8601String(),
            'transcript_drive_id' => $meeting->transcript_drive_id,
            'transcript_url' => $meeting->transcript_download_url,
            'audio_drive_id' => $meeting->audio_drive_id,
            'audio_url' => $meeting->audio_download_url,
        ];

        return $this->filterArray(array_merge($base, $extra));
    }

    private function buildLegacyMeetingMetadata(TranscriptionLaravel $meeting, array $extra = []): array
    {
        $base = [
            'source' => 'transcriptions_laravel',
            'legacy_meeting_id' => $meeting->id,
            'created_at' => optional($meeting->created_at)->toIso8601String(),
            'updated_at' => optional($meeting->updated_at)->toIso8601String(),
            'has_transcription' => ! empty($meeting->transcript_drive_id),
            'has_audio' => ! empty($meeting->audio_drive_id),
            'transcript_drive_id' => $meeting->transcript_drive_id,
            'transcript_download_url' => $meeting->transcript_download_url,
            'audio_drive_id' => $meeting->audio_drive_id,
            'audio_download_url' => $meeting->audio_download_url,
        ];

        return $this->filterArray(array_merge($base, $extra));
    }

    private function filterArray(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_string($value)) {
                return $value !== '';
            }

            return $value !== null;
        });
    }

    private function normalizeSpeakerString(?string $value): string
    {
        if (!is_string($value) || $value === '') {
            return '';
        }

        return (string) Str::of($value)
            ->lower()
            ->replaceMatches('/[\p{P}\p{S}]+/u', ' ')
            ->replace(
                ['á', 'é', 'í', 'ó', 'ú', 'ü', 'ñ'],
                ['a', 'e', 'i', 'o', 'u', 'u', 'n']
            )
            ->replaceMatches('/\s+/', ' ')
            ->trim();
    }

    private function extractQueryKeywords(string $query, int $limit = 5): array
    {
        $normalized = Str::of($query)
            ->lower()
            ->replaceMatches('/[\p{P}\p{S}]+/u', ' ')
            ->replace(['á','é','í','ó','ú','ñ'], ['a','e','i','o','u','n']);

        $tokens = preg_split('/\s+/u', (string)$normalized);
        if (!is_array($tokens)) { return []; }

        // Stopwords básicas español + genéricas
        $stop = [
            'de','la','el','los','las','un','una','unos','unas','que','en','y','o','u','del','al','se','lo','por','con','para','a','su','sus','sobre','sin','mas','más','es','fue','son','eran','ser','como','qué','que','donde','dónde','cuando','cuándo','cual','cuál',' cuales','con','este','esta','esto','estos','estas','hay','hubo','han','todas','toda','todos','todo','reunion','reunión','hablo','habló','dice','dijo','dime','pregunta','pregunto','acerca','sobre','de','las','los','al','del','por','que','quiero','ver','mostrar','muestra','dame','dame','resumen','completo','detallado'
        ];
        $stop = array_unique($stop);

        $filtered = [];
        foreach ($tokens as $t) {
            $t = trim($t);
            if ($t === '' || mb_strlen($t) < 3) { continue; }
            if (in_array($t, $stop, true)) { continue; }
            $filtered[] = $t;
        }

        $filtered = array_values(array_unique($filtered));
        return array_slice($filtered, 0, $limit);
    }

    private function formatTimeForCitation($time): string
    {
        if (is_numeric($time)) {
            $seconds = (int) $time;
            $hours = floor($seconds / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            $remainingSeconds = $seconds % 60;

            return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
        }

        if (is_string($time) && preg_match('/^\d{2}:\d{2}:\d{2}$/', $time)) {
            return $time;
        }

        return (string) $time;
    }

    private function formatParticipants($participants): string
    {
        if (is_array($participants)) {
            return implode(', ', array_slice($participants, 0, 5));
        }

        if (is_string($participants)) {
            return $participants;
        }

        return 'sin participantes registrados';
    }

    /**
     * Determinar tipo de documento basado en MIME type
     */
    private function getDocumentType(string $mimeType, string $filename): string
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        if (str_contains($mimeType, 'pdf') || $extension === 'pdf') {
            return 'pdf';
        } elseif (str_contains($mimeType, 'word') || in_array($extension, ['doc', 'docx'])) {
            return 'word';
        } elseif (str_contains($mimeType, 'sheet') || in_array($extension, ['xls', 'xlsx'])) {
            return 'excel';
        } elseif (str_contains($mimeType, 'presentation') || in_array($extension, ['ppt', 'pptx'])) {
            return 'powerpoint';
        } elseif (str_contains($mimeType, 'image') || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp'])) {
            return 'image';
        } else {
            return 'text';
        }
    }

    /**
     * Subir archivo a Google Drive
     */
    private function uploadToGoogleDrive($file, $folderId, $driveType, $user): array
    {
        $context = $this->resolveDriveContext($driveType, $user);

        $attempt = 0;
        $maxAttempts = 2;

        while ($attempt < $maxAttempts) {
            try {
                $this->ensureValidAccessToken($driveType, $context);

                $destinationFolderId = $folderId ?: $this->ensureDocumentsFolder($driveType, $context);

                $fileContents = @file_get_contents($file->getRealPath());
                if ($fileContents === false) {
                    throw new RuntimeException('No se pudo leer el archivo a subir.');
                }

                $fileId = $this->googleDriveService->uploadFile(
                    $file->getClientOriginalName(),
                    $file->getMimeType() ?? 'application/octet-stream',
                    $destinationFolderId,
                    $fileContents
                );

                $metadata = $this->buildDocumentMetadata($fileId, $destinationFolderId, $driveType);

                return [
                    'file_id' => $fileId,
                    'folder_id' => $destinationFolderId,
                    'metadata' => $metadata,
                ];
            } catch (GoogleServiceException $exception) {
                $attempt++;

                Log::error('Google Drive API error while uploading AI assistant document', [
                    'attempt' => $attempt,
                    'drive_type' => $driveType,
                    'username' => $user->username,
                    'code' => $exception->getCode(),
                    'error' => $exception->getMessage(),
                ]);

                $refreshed = $this->refreshAccessToken($driveType, $context);
                if ($attempt >= $maxAttempts || ! $refreshed) {
                    throw $exception;
                }

                continue;
            } catch (\Throwable $exception) {
                Log::error('Unexpected error while uploading AI assistant document to Drive', [
                    'drive_type' => $driveType,
                    'username' => $user->username,
                    'error' => $exception->getMessage(),
                ]);

                throw $exception;
            }
        }

        throw new RuntimeException('No se pudo subir el archivo a Google Drive después de reintentos.');
    }

    private function resolveDriveContext(string $driveType, $user): array
    {
        if ($driveType === 'organization') {
            $organization = $user->organization;

            if (! $organization) {
                throw new RuntimeException('El usuario no pertenece a ninguna organización activa.');
            }

            $token = $organization->googleToken;
            if (! $token) {
                throw new RuntimeException('La organización no tiene configurado Google Drive.');
            }

            $tokenData = $this->normalizeOrganizationToken($token);

            return [
                'token' => $tokenData,
                'token_model' => $token,
                'organization' => $organization,
                'root_folder_id' => $organization->folder?->google_id,
                'username' => $user->username,
            ];
        }

        $token = $user->googleToken;
        if (! $token || ! $token->hasValidAccessToken()) {
            throw new RuntimeException('El usuario no tiene un token de Google Drive válido.');
        }

        return [
            'token' => $token->getTokenArray(),
            'token_model' => $token,
            'root_folder_id' => $token->recordings_folder_id ?? null,
            'username' => $user->username,
        ];
    }

    private function normalizeOrganizationToken(OrganizationGoogleToken $token): array
    {
        $rawAccessToken = $token->access_token;
        $accessToken = null;

        if (is_array($rawAccessToken)) {
            $accessToken = $rawAccessToken['access_token'] ?? null;
        } elseif (is_string($rawAccessToken)) {
            $accessToken = $rawAccessToken;
        }

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('El token de acceso de la organización no es válido.');
        }

        $tokenData = [
            'access_token' => $accessToken,
        ];

        if (! empty($token->refresh_token)) {
            $tokenData['refresh_token'] = $token->refresh_token;
        }

        if ($token->expiry_date) {
            $tokenData['expiry_date'] = $token->expiry_date->timestamp;
        }

        return $tokenData;
    }

    private function ensureValidAccessToken(string $driveType, array &$context): array
    {
        $tokenData = $context['token'];

        $this->googleDriveService->setAccessToken($tokenData);

        $client = $this->googleDriveService->getClient();
        if ($client->isAccessTokenExpired()) {
            $refreshed = $this->refreshAccessToken($driveType, $context);
            if (! $refreshed) {
                throw new RuntimeException('Token de Google Drive expirado y no se pudo renovar.');
            }

            $tokenData = $refreshed;
            $this->googleDriveService->setAccessToken($tokenData);
        }

        $context['token'] = $tokenData;

        return is_array($tokenData) ? $tokenData : ['access_token' => $tokenData];
    }

    private function refreshAccessToken(string $driveType, array &$context): ?array
    {
        try {
            $tokenModel = $context['token_model'] ?? null;

            if ($driveType === 'personal' && $tokenModel instanceof GoogleToken) {
                if (empty($tokenModel->refresh_token)) {
                    Log::warning('User Google token cannot be refreshed: missing refresh_token', [
                        'username' => $context['username'] ?? null,
                    ]);
                    return null;
                }

                if (! $this->googleTokenRefreshService->refreshToken($tokenModel)) {
                    Log::error('Failed to refresh Google token for user', [
                        'username' => $context['username'] ?? null,
                    ]);
                    return null;
                }

                $tokenModel->refresh();
                $context['token_model'] = $tokenModel;
                $context['token'] = $tokenModel->getTokenArray();

                return $context['token'];
            }

            if ($driveType === 'organization' && $tokenModel instanceof OrganizationGoogleToken) {
                if (empty($tokenModel->refresh_token)) {
                    Log::warning('Organization Google token cannot be refreshed: missing refresh_token', [
                        'organization_id' => $tokenModel->organization_id,
                    ]);
                    return null;
                }

                $newToken = $this->googleDriveService->refreshToken($tokenModel->refresh_token);

                if (empty($newToken['access_token'])) {
                    Log::error('Failed to refresh organization Google token: missing access_token', [
                        'organization_id' => $tokenModel->organization_id,
                    ]);
                    return null;
                }

                $tokenModel->update([
                    'access_token' => $newToken,
                    'expiry_date' => now()->addSeconds($newToken['expires_in'] ?? 3600),
                ]);
                $tokenModel->refresh();

                $newToken['refresh_token'] = $tokenModel->refresh_token;
                $context['token_model'] = $tokenModel;
                $context['token'] = $newToken;

                return $newToken;
            }
        } catch (\Throwable $exception) {
            Log::error('Failed to refresh Google access token for AI assistant upload', [
                'drive_type' => $driveType,
                'username' => $context['username'] ?? null,
                'error' => $exception->getMessage(),
            ]);
        }

        return null;
    }

    private function ensureDocumentsFolder(string $driveType, array $context): string
    {
        $parentId = $context['root_folder_id'] ?? null;

        if ($driveType === 'organization' && ! $parentId) {
            throw new RuntimeException('La organización no tiene una carpeta raíz configurada en Google Drive.');
        }

    $folderName = self::DOCUMENTS_FOLDER_NAME;

        $existingFolder = $this->findExistingFolder($folderName, $parentId);
        if ($existingFolder) {
            return $existingFolder;
        }

        $createdId = $this->googleDriveService->createFolder($folderName, $parentId);

        Log::info('Created AI assistant folder in Google Drive', [
            'drive_type' => $driveType,
            'folder_id' => $createdId,
            'parent_id' => $parentId,
        ]);

        return $createdId;
    }

    private function findExistingFolder(string $folderName, ?string $parentId = null): ?string
    {
        $escapedName = str_replace("'", "\\'", $folderName);
        $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and name='{$escapedName}'";

        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $folders = $this->googleDriveService->listFolders($query);

        foreach ($folders as $folder) {
            if (strcasecmp($folder->getName(), $folderName) === 0) {
                return $folder->getId();
            }
        }

        return null;
    }

    private function buildDocumentMetadata(string $fileId, string $folderId, string $driveType): array
    {
        $metadata = [
            'file_id' => $fileId,
            'folder_id' => $folderId,
            'drive_type' => $driveType,
        ];

        try {
            $metadata['web_view_link'] = $this->googleDriveService->getFileLink($fileId);
        } catch (\Throwable $exception) {
            Log::warning('Unable to obtain Drive webViewLink for AI document', [
                'file_id' => $fileId,
                'error' => $exception->getMessage(),
            ]);
        }

        try {
            $downloadLink = $this->googleDriveService->getWebContentLink($fileId);
            if ($downloadLink) {
                $metadata['web_content_link'] = $downloadLink;
            }
        } catch (\Throwable $exception) {
            Log::warning('Unable to obtain Drive download link for AI document', [
                'file_id' => $fileId,
                'error' => $exception->getMessage(),
            ]);
        }

        return $metadata;
    }

    /**
     * Procesar documento en background
     */
    private function processDocumentInBackground(AiDocument $document): void
    {
        $document->update([
            'processing_status' => 'processing',
            'processing_error' => null,
        ]);

        // If the queue driver is 'sync', dispatching will block the request and can cause 504s.
        // In that case, prefer dispatchAfterResponse so we return JSON immediately and process afterwards.
        $driver = (string) config('queue.default', 'sync');
        if ($driver === 'sync') {
            ProcessAiDocumentJob::dispatchAfterResponse($document->id);
        } else {
            ProcessAiDocumentJob::dispatch($document->id);
        }
    }

    private function associateDocumentToSessionContext(AiDocument $document, AiChatSession $session, string $username): void
    {
        if ($session->context_type === 'meeting' && $session->context_id) {
            AiMeetingDocument::create([
                'document_id' => $document->id,
                'meeting_id' => (string) $session->context_id,
                'meeting_type' => AiMeetingDocument::MEETING_TYPE_LEGACY,
                'assigned_by_username' => $username,
                'assignment_note' => 'Cargado desde el asistente',
            ]);
            return;
        }

        if ($session->context_type === 'container' && $session->context_id) {
            // Permitir contenedores organizacionales donde el usuario tenga acceso,
            // sin restringir al creador únicamente
            $container = MeetingContentContainer::where('id', $session->context_id)
                ->where('is_active', true)
                ->first();
            if ($container) {
                // meetings() is a hasManyThrough to TranscriptionLaravel
                $meetingIds = $container->meetings()->pluck('transcriptions_laravel.id')->all();
                foreach ($meetingIds as $mid) {
                    AiMeetingDocument::create([
                        'document_id' => $document->id,
                        'meeting_id' => (string) $mid,
                        'meeting_type' => AiMeetingDocument::MEETING_TYPE_LEGACY,
                        'assigned_by_username' => $username,
                        'assignment_note' => 'Cargado desde el asistente (contenedor)',
                    ]);
                }
            }
        }
    }

    public function generateSummaryPdf(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $session = AiChatSession::where('id', $id)->where('username', $user->username)->firstOrFail();

        $fragments = [];
        if ($session->context_type === 'meeting') {
            $virtual = $this->createVirtualSession($session, 'meeting', $session->context_id);
            $fragments = $this->buildMeetingContextFragments($virtual, '');
        } elseif ($session->context_type === 'container') {
            $virtual = $this->createVirtualSession($session, 'container', $session->context_id);
            $fragments = $this->buildContainerContextFragments($virtual);
        } else {
            $fragments = $this->gatherContext($session, 'resumen');
        }

        $title = match ($session->context_type) {
            'meeting' => 'Resumen de reunión',
            'container' => 'Resumen del contenedor',
            default => 'Resumen general',
        };

        $citations = array_values(array_unique(array_filter(array_map(fn($f) => $f['citation'] ?? null, $fragments))));

        $html = view('ai.summary_pdf', [
            'title' => $title,
            'session' => $session,
            'fragments' => $fragments,
            'citations' => $citations,
            'generatedAt' => now(),
            'user' => $user,
        ])->render();

        $pdf = app('dompdf.wrapper');
        $pdf->loadHTML($html)->setPaper('a4', 'portrait');

        $tmpPath = storage_path('app/tmp');
        if (!is_dir($tmpPath)) @mkdir($tmpPath, 0775, true);
        $filename = $title . ' - ' . now()->format('Ymd_His') . '.pdf';
        $absolute = $tmpPath . DIRECTORY_SEPARATOR . $filename;
        file_put_contents($absolute, $pdf->output());

        try {
            $driveResult = $this->uploadToGoogleDrive(new \Illuminate\Http\File($absolute), null, $request->input('drive_type', 'personal'), $user);

            $document = AiDocument::create([
                'username' => $user->username,
                'name' => pathinfo($filename, PATHINFO_FILENAME),
                'original_filename' => $filename,
                'document_type' => 'pdf',
                'mime_type' => 'application/pdf',
                'file_size' => @filesize($absolute) ?: null,
                'drive_file_id' => $driveResult['file_id'],
                'drive_folder_id' => $driveResult['folder_id'],
                'drive_type' => $request->input('drive_type', 'personal'),
                'processing_status' => 'completed',
                'document_metadata' => [
                    'summary_generated' => true,
                    'citations' => $citations,
                    'created_in_session' => (string) $session->id,
                    'created_via' => 'assistant_summary_pdf',
                ],
            ]);

            $this->associateDocumentToSessionContext($document, $session, $user->username);

            return response()->json([
                'success' => true,
                'document' => $document,
                'message' => 'Resumen PDF generado y guardado en Drive.'
            ]);
        } finally {
            @unlink($absolute);
        }
    }
}
