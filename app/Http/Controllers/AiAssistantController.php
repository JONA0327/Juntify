<?php

namespace App\Http\Controllers;

use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiDocument;
use App\Models\Container;
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
use Illuminate\Support\Str;
use App\Services\AiChatService;
use App\Services\GoogleDriveService;
use App\Services\GoogleTokenRefreshService;
use App\Jobs\ProcessAiDocumentJob;
use App\Services\EmbeddingSearch;
use Google\Service\Exception as GoogleServiceException;
use RuntimeException;

class AiAssistantController extends Controller
{
    private const DEFAULT_AI_FOLDER_NAME = 'Juntify AI Documents';

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
     * Crear nueva sesión de chat
     */
    public function createSession(Request $request): JsonResponse
    {
        $user = Auth::user();

        $request->validate([
            'title' => 'sometimes|string|max:255',
            'context_type' => 'required|in:general,container,meeting,contact_chat,documents,mixed',
            'context_id' => 'nullable|string',
            'context_data' => 'nullable|array'
        ]);

        $session = AiChatSession::create([
            'username' => $user->username,
            'title' => $request->title ?? 'Nueva conversación',
            'context_type' => $request->context_type,
            'context_id' => $request->context_id,
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

        $session = AiChatSession::byUser($user->username)
            ->findOrFail($sessionId);

        // Crear mensaje del usuario
        $userMessage = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'user',
            'content' => $request->content,
            'attachments' => $request->attachments ?? []
        ]);

        // Procesar con IA y generar respuesta
        $assistantMessage = $this->processAiResponse($session, $request->content, $request->attachments ?? []);

        // Actualizar actividad de la sesión
        $session->updateActivity();

        $assistantArray = $assistantMessage->toArray();

        return response()->json([
            'success' => true,
            'user_message' => $userMessage->toArray(),
            'assistant_message' => $assistantArray,
            'citations' => $assistantArray['metadata']['citations'] ?? [],
        ]);
    }

    /**
     * Obtener contenedores del usuario
     */
    public function getContainers(): JsonResponse
    {
        $user = Auth::user();

        $containers = Container::where('username', $user->username)
            ->withCount('meetings')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($container) {
                return [
                    'id' => $container->id,
                    'name' => $container->name,
                    'meetings_count' => $container->meetings_count,
                    'created_at' => $container->created_at
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
            'file' => 'required|file|max:10240', // 10MB max
            'drive_folder_id' => 'nullable|string',
            'drive_type' => 'required|in:personal,organization'
        ]);

        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            $mimeType = $file->getMimeType();
            $fileSize = $file->getSize();

            // Determinar tipo de documento
            $documentType = $this->getDocumentType($mimeType, $originalName);

            // Subir a Google Drive
            $driveResult = $this->uploadToGoogleDrive($file, $request->drive_folder_id, $request->drive_type, $user);

            // Crear registro en la base de datos
            $document = AiDocument::create([
                'username' => $user->username,
                'name' => pathinfo($originalName, PATHINFO_FILENAME),
                'original_filename' => $originalName,
                'document_type' => $documentType,
                'mime_type' => $mimeType,
                'file_size' => $fileSize,
                'drive_file_id' => $driveResult['file_id'],
                'drive_folder_id' => $driveResult['folder_id'],
                'drive_type' => $request->drive_type,
                'processing_status' => 'pending',
                'document_metadata' => $driveResult['metadata'] ?? null,
            ]);

            // Procesar documento en background (OCR, extracción de texto)
            $this->processDocumentInBackground($document);

            return response()->json([
                'success' => true,
                'document' => $document,
                'message' => 'Documento subido correctamente. Se está procesando...'
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
            ->map(function($document) {
                return [
                    'id' => $document->id,
                    'name' => $document->name,
                    'original_filename' => $document->original_filename,
                    'document_type' => $document->document_type,
                    'file_size' => $document->file_size,
                    'processing_status' => $document->processing_status,
                    'has_text' => $document->hasText(),
                    'created_at' => $document->created_at
                ];
            });

        return response()->json([
            'success' => true,
            'documents' => $documents
        ]);
    }

    /**
     * Generar mensaje inicial del sistema según el contexto
     */
    private function generateSystemMessage(AiChatSession $session): ?string
    {
        switch ($session->context_type) {
            case 'container':
                return "Eres un asistente IA especializado en análisis de reuniones. El usuario ha seleccionado un contenedor específico con reuniones agrupadas. Puedes ayudar con resúmenes, análisis de tendencias, búsqueda de información específica y generar insights basados en el contenido de las reuniones.";

            case 'meeting':
                return "Eres un asistente IA especializado en análisis de reuniones. El usuario ha seleccionado una reunión específica. Puedes ayudar con el análisis del contenido, resúmenes, puntos clave, tareas pendientes y responder preguntas específicas sobre la reunión.";

            case 'contact_chat':
                return "Eres un asistente IA con acceso al historial de conversaciones del usuario. Puedes ayudar a analizar patrones de comunicación, resumir conversaciones y proporcionar contexto sobre las interacciones con contactos.";

            case 'documents':
                return "Eres un asistente IA especializado en análisis de documentos. Puedes ayudar a extraer información, resumir contenido, responder preguntas específicas sobre los documentos y realizar búsquedas semánticas en el contenido.";

            default:
                return "Eres un asistente IA integral para Juntify. Puedes ayudar con análisis de reuniones, gestión de documentos, búsqueda de información y responder preguntas sobre el contenido disponible.";
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
        /** @var EmbeddingSearch $search */
        $search = app(EmbeddingSearch::class);
        $contextFragments = $search->search($session->username, $query, [
            'session' => $session,
            'limit' => 8,
        ]);

        $additional = [];

        switch ($session->context_type) {
            case 'container':
                $additional = $this->buildContainerContextFragments($session);
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

        return array_values(array_merge($contextFragments, $additional));
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
                    $fragments = array_merge($fragments, $this->buildContainerContextFragments($containerSession));
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

    private function buildContainerContextFragments(AiChatSession $session): array
    {
        $fragments = [];

        if ($session->context_id) {
            $container = Container::withCount(['meetings' => function ($query) use ($session) {
                    $query->where('username', $session->username);
                }])
                ->with(['meetings' => function ($query) use ($session) {
                    $query->where('username', $session->username)
                        ->latest('created_at')
                        ->limit(5);
                }])
                ->where('id', $session->context_id)
                ->where('username', $session->username)
                ->first();

            if ($container) {
                $fragments[] = [
                    'text' => sprintf(
                        'Contenedor "%s" con %d reuniones registradas.',
                        $container->name,
                        $container->meetings_count
                            ?? $container->meetings()
                                ->where('username', $session->username)
                                ->count()
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

                foreach ($container->meetings as $meeting) {
                    $fragments[] = [
                        'text' => sprintf(
                            'Reunión "%s" con transcripción %s y audio %s disponible.',
                            $meeting->meeting_name,
                            $meeting->transcript_drive_id ? 'en' : 'no',
                            $meeting->audio_drive_id ? 'sí' : 'no'
                        ),
                        'source_id' => 'meeting:' . $meeting->id,
                        'content_type' => 'container_meeting',
                        'location' => $this->buildLegacyMeetingLocation($meeting),
                        'similarity' => null,
                        'citation' => 'meeting:' . $meeting->id . ' recursos',
                        'metadata' => $this->buildLegacyMeetingMetadata($meeting),
                    ];
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
            }
        }

        return $fragments;
    }

    private function buildMeetingContextFragments(AiChatSession $session, string $query): array
    {
        if (! $session->context_id) {
            return [];
        }

        $meeting = TranscriptionLaravel::with([
            'keyPoints' => fn ($relation) => $relation->ordered()->limit(5),
            'transcriptions' => function ($relation) use ($query) {
                $relation->orderBy('time');

                $keywords = $this->extractQueryKeywords($query);
                if (! empty($keywords)) {
                    $relation->where(function ($builder) use ($keywords) {
                        foreach ($keywords as $keyword) {
                            $builder->orWhere('text', 'like', '%' . $keyword . '%');
                        }
                    });
                }

                $relation->limit(5);
            },
        ])
            ->where('username', $session->username)
            ->where('id', $session->context_id)
            ->first();

        $fragments = [];

        if ($meeting) {
            if (! empty($meeting->summary)) {
                $fragments[] = [
                    'text' => Str::limit($meeting->summary, 800),
                    'source_id' => 'meeting:' . $meeting->id . ':summary',
                    'content_type' => 'meeting_summary',
                    'location' => $this->buildLegacyMeetingLocation($meeting, ['section' => 'summary']),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' resumen',
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, ['summary' => true]),
                ];
            }

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

            $segments = $meeting->transcriptions;
            if ($segments->isEmpty()) {
                $segments = $meeting->transcriptions()->orderBy('time')->limit(3)->get();
            }

            foreach ($segments as $segment) {
                if (trim((string) $segment->text) === '') {
                    continue;
                }

                $speaker = $segment->display_speaker ?? $segment->speaker;
                $fragments[] = [
                    'text' => trim(($speaker ?? 'Participante') . ': ' . $segment->text),
                    'source_id' => 'meeting:' . $meeting->id . ':segment:' . ($segment->id ?? $segment->time),
                    'content_type' => 'meeting_transcription_segment',
                    'location' => $this->buildLegacyMeetingLocation($meeting, [
                        'section' => 'transcription',
                        'speaker' => $speaker,
                        'timestamp' => $segment->time,
                        'segment_id' => $segment->id,
                    ]),
                    'similarity' => null,
                    'citation' => 'meeting:' . $meeting->id . ' t.' . $this->formatTimeForCitation($segment->time),
                    'metadata' => $this->buildLegacyMeetingMetadata($meeting, [
                        'transcription_segment' => true,
                        'segment_id' => $segment->id,
                        'timestamp' => $segment->time,
                        'speaker' => $speaker,
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

                $destinationFolderId = $folderId ?: $this->ensureDestinationFolder($driveType, $context);

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

    private function ensureDestinationFolder(string $driveType, array $context): string
    {
        $parentId = $context['root_folder_id'] ?? null;

        if ($driveType === 'organization' && ! $parentId) {
            throw new RuntimeException('La organización no tiene una carpeta raíz configurada en Google Drive.');
        }

        $folderName = self::DEFAULT_AI_FOLDER_NAME;

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

        ProcessAiDocumentJob::dispatch($document->id);
    }
}
