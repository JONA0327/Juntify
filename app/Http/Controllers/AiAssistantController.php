<?php

namespace App\Http\Controllers;

use App\Models\AiChatSession;
use App\Models\AiChatMessage;
use App\Models\AiDocument;
use App\Models\Container;
use App\Models\TranscriptionLaravel;
use App\Models\Meeting;
use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AiAssistantController extends Controller
{
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
            'context_type' => 'required|in:general,container,meeting,contact_chat,documents',
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
        $aiResponse = $this->processAiResponse($session, $request->content, $request->attachments ?? []);

        // Crear mensaje de respuesta de la IA
        $assistantMessage = AiChatMessage::create([
            'session_id' => $session->id,
            'role' => 'assistant',
            'content' => $aiResponse['content'],
            'metadata' => $aiResponse['metadata'] ?? []
        ]);

        // Actualizar actividad de la sesión
        $session->updateActivity();

        return response()->json([
            'success' => true,
            'user_message' => $userMessage,
            'assistant_message' => $assistantMessage
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

        // Reuniones legacy
        $legacyMeetings = TranscriptionLaravel::where('username', $user->username)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($meeting) {
                return [
                    'id' => $meeting->id,
                    'name' => $meeting->meeting_name,
                    'type' => 'legacy',
                    'created_at' => $meeting->created_at,
                    'has_summary' => !empty($meeting->summary),
                    'has_transcription' => !empty($meeting->transcript_drive_id)
                ];
            });

        // Reuniones modernas
        $modernMeetings = Meeting::where('username', $user->username)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($meeting) {
                return [
                    'id' => $meeting->id,
                    'name' => $meeting->title,
                    'type' => 'modern',
                    'created_at' => $meeting->created_at,
                    'has_summary' => !empty($meeting->summary),
                    'has_transcription' => $meeting->transcriptions()->exists()
                ];
            });

        $meetings = $legacyMeetings->concat($modernMeetings)->sortByDesc('created_at')->values();

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
                'processing_status' => 'pending'
            ]);

            // Procesar documento en background (OCR, extracción de texto)
            $this->processDocumentInBackground($document);

            return response()->json([
                'success' => true,
                'document' => $document,
                'message' => 'Documento subido correctamente. Se está procesando...'
            ]);

        } catch (\Exception $e) {
            Log::error('Error uploading document: ' . $e->getMessage());
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
    private function processAiResponse(AiChatSession $session, string $userMessage, array $attachments = []): array
    {
        // Aquí implementarías la lógica de IA
        // Por ahora, una respuesta simulada

        $context = $this->gatherContext($session, $userMessage);

        // Simular procesamiento de IA
        $response = "He analizado tu consulta: '$userMessage'.\n\n";

        if (!empty($context)) {
            $response .= "Basándome en el contexto disponible:\n";
            foreach ($context as $item) {
                $response .= "- " . $item . "\n";
            }
        }

        $response .= "\n¿En qué más puedo ayudarte?";

        return [
            'content' => $response,
            'metadata' => [
                'context_items_used' => count($context),
                'processing_time' => now()->toISOString()
            ]
        ];
    }

    /**
     * Recopilar contexto relevante para la consulta
     */
    private function gatherContext(AiChatSession $session, string $query): array
    {
        $context = [];

        // Reunir contexto según el tipo de sesión
        switch ($session->context_type) {
            case 'container':
                if ($session->context_id) {
                    $container = Container::find($session->context_id);
                    if ($container) {
                        $context[] = "Contenedor: {$container->name}";
                        $context[] = "Número de reuniones: " . $container->meetings()->count();
                    }
                }
                break;

            case 'meeting':
                if ($session->context_id) {
                    // Buscar en reuniones legacy y modernas
                    $legacy = TranscriptionLaravel::find($session->context_id);
                    $modern = Meeting::find($session->context_id);

                    if ($legacy) {
                        $context[] = "Reunión: {$legacy->meeting_name}";
                        if ($legacy->summary) {
                            $context[] = "Resumen disponible";
                        }
                    } elseif ($modern) {
                        $context[] = "Reunión: {$modern->title}";
                        if ($modern->summary) {
                            $context[] = "Resumen disponible";
                        }
                    }
                }
                break;
        }

        return $context;
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
        // Aquí implementarías la lógica de subida a Google Drive
        // Por ahora, simular la respuesta
        return [
            'file_id' => 'dummy_file_id_' . Str::random(10),
            'folder_id' => $folderId ?? 'default_folder_id'
        ];
    }

    /**
     * Procesar documento en background
     */
    private function processDocumentInBackground(AiDocument $document): void
    {
        // Aquí implementarías el procesamiento en background
        // OCR, extracción de texto, generación de embeddings, etc.

        // Por ahora, marcar como completado
        $document->update([
            'processing_status' => 'completed',
            'extracted_text' => 'Texto extraído simulado del documento: ' . $document->original_filename
        ]);
    }
}
