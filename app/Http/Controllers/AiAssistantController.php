<?php

namespace App\Http\Controllers;

use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiDocument;
use App\Models\AiMeetingDocument;
use App\Models\MeetingContentContainer;
use App\Models\TranscriptionLaravel;
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
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;
use App\Support\OpenAiConfig;
use App\Traits\MeetingContentParsing;

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

        foreach ($container->meetings as $meeting) {
            try {
                // Trigger download/parse flow by attempting to build fragments from .ju
                // This does not persist, but verifies availability and warms caches
                $this->buildFragmentsFromJu($meeting, '');
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
            'attachments' => 'nullable|array'
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

        // Crear mensaje del usuario
        $userMessage = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $request->content,
            'attachments' => $request->attachments ?? []
        ]);

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

        $meetings = TranscriptionLaravel::where('username', $user->username)
            ->orderByDesc('created_at')
            ->get()
            ->map(function (TranscriptionLaravel $meeting) {
                return [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'title' => $meeting->meeting_name,
                    'source' => 'transcriptions_laravel',
                    'is_legacy' => true,
                    'created_at' => optional($meeting->created_at)->toIso8601String(),
                    'updated_at' => optional($meeting->updated_at)->toIso8601String(),
                    'has_transcription' => ! empty($meeting->transcript_drive_id),
                    'has_audio' => ! empty($meeting->audio_drive_id),
                    'transcript_drive_id' => $meeting->transcript_drive_id,
                    'transcript_download_url' => $meeting->transcript_download_url,
                    'audio_drive_id' => $meeting->audio_drive_id,
                    'audio_download_url' => $meeting->audio_download_url,
                ];
            })
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
            'file' => 'sometimes|file|max:51200', // 50MB max
            'files.*' => 'sometimes|file|max:51200',
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
        if ($useEmbeddings && !empty($apiKey)) {
            /** @var EmbeddingSearch $search */
            $search = app(EmbeddingSearch::class);
            try {
                $semanticLimit = $session->context_type === 'container'
                    ? (int) env('AI_ASSISTANT_SEMANTIC_LIMIT_CONTAINER', 20)
                    : 8;
                $contextFragments = $search->search($session->username, $query, [
                    'session' => $session,
                    'limit' => $semanticLimit,
                ]);
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
            $contextFragments = $meta->search($session->username, $query, [
                'session' => $session,
                'limit' => $metaLimit,
            ]);
        }

    $additional = [];

        switch ($session->context_type) {
            case 'container':
                $additional = $this->buildContainerContextFragments($session, $query);
                break;

            case 'meeting':
                $additional = $this->buildMeetingContextFragments($session, $query);
                break;

            case 'documents':
                $additional = $this->buildDocumentContextFragments($session);
                break;

            case 'contact_chat':
                $additional = $this->buildChatContextFragments($session);
                break;

            case 'mixed':
                $additional = $this->buildMixedContextFragments($session, $query);
                break;
        }

        // Incluir documentos agregados explícitamente a la sesión (doc_ids en context_data)
        try {
            $docIds = Arr::get(is_array($session->context_data) ? $session->context_data : [], 'doc_ids', []);
            if (is_array($docIds) && count($docIds) > 0) {
                $docSession = $this->createVirtualSession($session, 'documents', null, $docIds);
                $docFragments = $this->buildDocumentContextFragments($docSession);
                $additional = array_merge($additional, $docFragments);
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to build document fragments from session doc_ids', ['error' => $e->getMessage()]);
        }

        return array_values(array_merge($contextFragments, $additional));
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

                // Incluir fragmentos desde los .ju de TODAS las reuniones del contenedor
                $totalLimit = 120; // límite de seguridad global de fragmentos
                $perMeetingLimit = 10; // orientativo (buildFragmentsFromJu ya limita segmentos)
                $count = 0;
                foreach ($container->meetings as $meeting) {
                    // Breve ficha de la reunión como contexto estructural
                    $fragments[] = [
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

                    if ($count >= $totalLimit) continue;
                    $juFragments = $this->buildFragmentsFromJu($meeting, $query);
                    if (!empty($juFragments)) {
                        // Recortar por reunión si fuera necesario
                        $slice = array_slice($juFragments, 0, $perMeetingLimit);
                        $fragments = array_merge($fragments, $slice);
                        $count += count($slice);
                        if ($count >= $totalLimit) continue;
                    } else {
                        // Fallback mínimo cuando no hay .ju utilizable
                        if (! empty($meeting->transcript_download_url)) {
                            $fragments[] = [
                                'text' => sprintf('Transcripción disponible en: %s', $meeting->transcript_download_url),
                                'source_id' => 'meeting:' . $meeting->id . ':transcript',
                                'content_type' => 'meeting_transcript_link',
                                'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'transcript_link']),
                                'similarity' => null,
                                'citation' => 'meeting:' . $meeting->id . ' transcript',
                                'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['resource' => 'transcript']),
                            ];
                            $count++;
                            if ($count >= $totalLimit) continue;
                        }
                        if (! empty($meeting->audio_download_url) && $count < $totalLimit) {
                            $fragments[] = [
                                'text' => sprintf('Audio disponible en: %s', $meeting->audio_download_url),
                                'source_id' => 'meeting:' . $meeting->id . ':audio',
                                'content_type' => 'meeting_audio_link',
                                'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'audio_link']),
                                'similarity' => null,
                                'citation' => 'meeting:' . $meeting->id . ' audio',
                                'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['resource' => 'audio']),
                            ];
                            $count++;
                        }
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

        $meeting = TranscriptionLaravel::with($withRelations)
            ->where('username', $session->username)
            ->where('id', $session->context_id)
            ->first();

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

            return $fragments;
        }

        $legacy = TranscriptionLaravel::where('username', $session->username)
            ->where('id', $session->context_id)
            ->first();
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

    /**
     * Construye fragmentos en memoria a partir del archivo .ju (en Drive) sin usar la tabla transcriptions
     */
    private function buildFragmentsFromJu(TranscriptionLaravel $meeting, string $query): array
    {
        $fragments = [];

        $fileId = $meeting->transcript_drive_id;
        if (! $fileId) {
            return $fragments;
        }

        try {
            // Intentar descargar y desencriptar .ju usando Service Account primero
            try {
                /** @var GoogleServiceAccount $sa */
                $sa = app(GoogleServiceAccount::class);
                $content = $sa->downloadFile($fileId);
            } catch (\Throwable $inner) {
                // Fallback a GoogleDriveService (OAuth del usuario/organización)
                $content = $this->googleDriveService->downloadFileContent($fileId);
            }
            if (! is_string($content) || $content === '') {
                return $fragments;
            }

            $parsed = $this->decryptJuFile($content);
            $data = $this->processTranscriptData($parsed['data'] ?? []);

            // Resumen
            if (! empty($data['summary'])) {
                $fragments[] = [
                    'text' => Str::limit((string)$data['summary'], 800),
                    'source_id' => 'meeting:' . $meeting->id . ':summary',
                    'content_type' => 'meeting_summary',
                    'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'summary']),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' resumen',
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['summary' => true]),
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

            // Segmentos: filtrar por palabras clave del query y limitar
            $keywords = $this->extractQueryKeywords($query);
            $segments = is_array($data['segments'] ?? null) ? $data['segments'] : [];
            $segments = array_values(array_filter($segments, function($seg) use ($keywords) {
                $txt = is_array($seg) ? ($seg['text'] ?? '') : '';
                if (trim($txt) === '') return false;
                if (empty($keywords)) return true;
                foreach ($keywords as $kw) {
                    if (stripos($txt, $kw) !== false) return true;
                }
                return false;
            }));
            $segments = array_slice($segments, 0, 5);

            foreach ($segments as $seg) {
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
                    ]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' t.' . ($time ? $this->formatTimeForCitation($time) : '—'),
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'transcription_segment' => true,
                        'timestamp' => $time,
                        'speaker' => $speaker,
                        'source' => 'ju',
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

    private function extractQueryKeywords(string $query, int $limit = 5): array
    {
        $tokens = preg_split('/\s+/u', Str::lower($query));
        if (! is_array($tokens)) {
            return [];
        }

        $keywords = array_values(array_unique(array_filter($tokens, function ($token) {
            return mb_strlen($token) >= 3;
        })));

        return array_slice($keywords, 0, $limit);
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
