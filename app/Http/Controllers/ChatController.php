<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\ChatMessageUserDeletion;
use App\Models\ChatUserDeletion;
use App\Models\Contact;
use App\Models\User;
use App\Models\GoogleToken;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class ChatController extends Controller
{
    public function index(): View
    {
        return view('chat.index');
    }

    public function apiTest(): JsonResponse
    {
        try {
            $userId = Auth::id();
            Log::info('API Test - User:', [
                'user_id' => $userId,
                'user' => Auth::check() ? 'authenticated' : 'not authenticated'
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'API test successful',
                'user_id' => $userId,
                'authenticated' => Auth::check()
            ]);
        } catch (\Exception $e) {
            Log::error('API Test Error:', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'API test failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function apiIndex(): JsonResponse
    {
        try {
            $userId = Auth::id();

            if (!$userId) {
                return response()->json(['error' => 'Usuario no autenticado'], 401);
            }

                        $chats = Chat::where(function($q) use ($userId) {
                                        $q->where('user_one_id', $userId)
                                            ->orWhere('user_two_id', $userId);
                                })
                ->with(['userOne:id,full_name,email', 'userTwo:id,full_name,email'])
                ->orderByDesc('updated_at')
                ->get();

            // Formatear los chats para incluir información del otro usuario
            $formattedChats = $chats->map(function($chat) use ($userId) {
                $otherUser = $chat->user_one_id === $userId ? $chat->userTwo : $chat->userOne;

                // Verificar que el otro usuario existe
                if (!$otherUser) {
                    return null;
                }

                // Si el usuario borró el chat en algún momento, ocultar todo lo anterior a esa fecha
                $deletedAt = optional(ChatUserDeletion::where('chat_id', $chat->id)->where('user_id', $userId)->first())->deleted_at;

                // Obtener último mensaje (respetando fecha de borrado del chat) y mostrar "tombstone" si fue eliminado para mí
                $deletedIds = ChatMessageUserDeletion::where('user_id', $userId)
                    ->whereIn('chat_message_id', function($q) use ($chat) {
                        $q->select('id')->from('chat_messages')->where('chat_id', $chat->id);
                    })->pluck('chat_message_id')->all();

                $lastMessage = ChatMessage::where('chat_id', $chat->id)
                    ->when($deletedAt, function($q) use ($deletedAt) { $q->where('created_at', '>', $deletedAt); })
                    ->latest()
                    ->first();

                // Contar mensajes no leídos (mensajes del otro usuario que no han sido leídos por el usuario actual)
                $unreadBase = ChatMessage::where('chat_id', $chat->id)
                    ->where('sender_id', '!=', $userId) // Mensajes del otro usuario
                    ->whereNull('read_at'); // No leídos
                if (!empty($deletedIds)) {
                    $unreadBase->whereNotIn('id', $deletedIds);
                }
                if ($deletedAt) { $unreadBase->where('created_at', '>', $deletedAt); }
                $unreadCount = $unreadBase->count();

                // Si el último mensaje está eliminado para mí, presentar tombstone
                $last = $lastMessage ? [
                    'body' => ($lastMessage && in_array($lastMessage->id, $deletedIds)) ? 'Se eliminó este mensaje' : $lastMessage->body,
                    'created_at' => $lastMessage->created_at,
                    'is_mine' => $lastMessage->sender_id === $userId
                ] : null;

                return [
                    'id' => $chat->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->full_name,
                        'email' => $otherUser->email,
                        'avatar' => strtoupper(substr($otherUser->full_name, 0, 1))
                    ],
                    'last_message' => $last,
                    'unread_count' => $unreadCount,
                    'has_unread' => $unreadCount > 0,
                    'updated_at' => $chat->updated_at
                ];
            })->filter(); // Filtrar elementos null

            return response()->json($formattedChats->values());

        } catch (\Exception $e) {
            Log::error('Error en apiIndex de Chat: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno del servidor'], 500);
        }
    }

    public function show(Chat $chat): JsonResponse
    {
        $userId = Auth::id();
        abort_unless($chat->user_one_id === $userId || $chat->user_two_id === $userId, 403);

        // Marcar todos los mensajes del otro usuario como leídos
        ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Ocultar historial previo si el usuario borró el chat
        $deletedIds = ChatMessageUserDeletion::where('user_id', $userId)
            ->whereIn('chat_message_id', function($q) use ($chat) {
                $q->select('id')->from('chat_messages')->where('chat_id', $chat->id);
            })->pluck('chat_message_id')->all();
        $deletedAt = optional(ChatUserDeletion::where('chat_id', $chat->id)->where('user_id', $userId)->first())->deleted_at;

        $messages = $chat->messages()
            ->when($deletedAt, function($q) use ($deletedAt) { $q->where('created_at', '>', $deletedAt); })
            ->with('sender')
            ->orderBy('created_at')
            ->get()
            ->map(function($m) use ($deletedIds) {
                if (in_array($m->id, $deletedIds)) {
                    // Convertir a "tombstone" para este usuario
                    $m->body = 'Se eliminó este mensaje';
                    $m->file_path = null;
                    $m->drive_file_id = null;
                    $m->original_name = null;
                    $m->mime_type = null;
                    $m->file_size = null;
                    $m->preview_url = null;
                    $m->voice_path = null;
                    $m->voice_base64 = null;
                    $m->voice_mime = null;
                }
                return $m;
            });

        return response()->json($messages);
    }
    /**
     * Lista contactos del usuario autenticado para iniciar chats rápidos
     */
    public function contacts(): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) { return response()->json([], 200); }
        $contacts = Contact::where('user_id', $userId)
            ->with(['contact:id,full_name,email'])
            ->get()
            ->map(fn($c) => [
                'id' => $c->contact->id,
                'name' => $c->contact->full_name,
                'email' => $c->contact->email,
                'avatar' => strtoupper(substr($c->contact->full_name,0,1))
            ]);
        return response()->json($contacts);
    }

    public function store(Request $request, Chat $chat, GoogleDriveService $driveService): JsonResponse
    {
        $user = Auth::user();
        abort_unless($chat->user_one_id === $user->id || $chat->user_two_id === $user->id, 403);

        $data = $request->validate([
            'body' => 'nullable|string',
            'file' => 'nullable|file',
            'voice' => 'nullable|file',
            // opcional: envío de audio como base64 desde el front
            'voice_base64' => 'nullable|string',
            'voice_mime' => 'nullable|string|max:100',
        ]);
    $uploadedFile = $request->file('file');
    $filePath = $uploadedFile ? $uploadedFile->store('chat_files') : null; // local fallback
    $voicePath = $request->file('voice') ? $request->file('voice')->store('chat_files') : null;
    $voiceBase64 = $data['voice_base64'] ?? null;
    $voiceMime = $data['voice_mime'] ?? null;

        $driveFileId = null; $previewUrl = null; $mime = null; $origName = null; $size = null;
        if ($uploadedFile) {
            $mime = $uploadedFile->getClientMimeType();
            $origName = $uploadedFile->getClientOriginalName();
            $size = $uploadedFile->getSize();

            // Intentar subir a carpeta raíz del remitente para compartir
            try {
                $token = GoogleToken::where('username', $user->username)->first();
                if ($token && $token->recordings_folder_id) {
                    $driveService->setAccessToken($token->getTokenArray());
                    $contents = file_get_contents($uploadedFile->getRealPath());

                    // Crear/asegurar carpeta chats/<chat_id>
                    $chatsFolderId = null;
                    try {
                        // Buscar carpeta 'chats' bajo recordings_folder_id; si no existe, crearla
                        $existing = $driveService->listSubfolders($token->recordings_folder_id);
                        $chatsFolderId = collect($existing)->firstWhere('name', 'chats')?->getId();
                    } catch (\Throwable $e) { $chatsFolderId = null; }
                    if (!$chatsFolderId) {
                        $chatsFolderId = $driveService->createFolder('chats', $token->recordings_folder_id);
                    }

                    // Subcarpeta por chat
                    $chatFolderId = null;
                    try {
                        $subs = $driveService->listSubfolders($chatsFolderId);
                        $chatFolderId = collect($subs)->firstWhere('name', 'chat_' . $chat->id)?->getId();
                    } catch (\Throwable $e) { $chatFolderId = null; }
                    if (!$chatFolderId) {
                        $chatFolderId = $driveService->createFolder('chat_' . $chat->id, $chatsFolderId);
                    }

                    $driveFileId = $driveService->uploadFile(
                        $origName,
                        $mime ?: 'application/octet-stream',
                        $chatFolderId,
                        $contents
                    );
                    // Compartir con el otro usuario
                    $otherUser = $chat->user_one_id === $user->id ? $chat->userTwo : $chat->userOne;
                    if ($otherUser?->email) {
                        try { $driveService->shareItem($driveFileId, $otherUser->email, 'reader'); } catch (\Throwable $e) { /* ignore */ }
                    }
                    // No usaremos preview embebido por CSP; el front mostrará solo enlace/indicador
                }
            } catch (\Throwable $e) {
                // Mantener solo filePath local si falla Drive
                Log::warning('Chat file upload Drive failed', ['error' => $e->getMessage()]);
            }
        }

        // Evitar body=null con adjuntos/voz: poner etiquetas amigables si viene vacío
        $bodyToStore = $data['body'] ?? null;
        if (!$bodyToStore) {
            if ($uploadedFile) {
                $bodyToStore = 'Se envió un archivo';
            } elseif ($voiceBase64 || $voicePath) {
                $bodyToStore = 'Se envió un audio';
            }
        }

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'body' => $bodyToStore,
            'file_path' => $filePath,
            'drive_file_id' => $driveFileId,
            'original_name' => $origName,
            'mime_type' => $mime,
            'file_size' => $size,
            'preview_url' => $previewUrl,
            'voice_path' => $voicePath,
            'voice_base64' => $voiceBase64,
            'voice_mime' => $voiceMime,
            'created_at' => now(),
        ]);

        return response()->json($message->load('sender'));
    }

    /**
     * Obtener el conteo de mensajes no leídos para un contacto específico
     */
    public function getUnreadCount(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id' => 'required|exists:users,id'
        ]);

        $userId = Auth::id();
        $contactId = $request->contact_id;

        // Buscar el chat entre estos dos usuarios
        $chat = Chat::where(function($query) use ($userId, $contactId) {
            $query->where('user_one_id', $userId)->where('user_two_id', $contactId);
        })->orWhere(function($query) use ($userId, $contactId) {
            $query->where('user_one_id', $contactId)->where('user_two_id', $userId);
        })->first();

        if (!$chat) {
            return response()->json(['unread_count' => 0, 'has_unread' => false]);
        }

        // Contar mensajes no leídos del contacto
        $unreadCount = ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', $contactId)
            ->whereNull('read_at')
            ->count();

        return response()->json([
            'unread_count' => $unreadCount,
            'has_unread' => $unreadCount > 0,
            'chat_id' => $chat->id
        ]);
    }

    /**
     * Crear o encontrar un chat entre dos usuarios
     */
    public function createOrFind(Request $request): JsonResponse
    {
        $request->validate([
            'contact_id' => 'nullable|exists:users,id',
            'user_query' => 'nullable|string'
        ]);
        $userId = Auth::id();
        $contactId = $request->contact_id;
        // Si no viene contact_id, permitir búsqueda por username/email para iniciar chat con no-contacto
        if (!$contactId && $request->filled('user_query')) {
            $query = trim($request->input('user_query'));
            $target = User::where('username', $query)->orWhere('email', $query)->first();
            if (!$target) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }
            $contactId = $target->id;
        }
        if (!$contactId) { return response()->json(['error' => 'Sin destino'], 422); }

        // Buscar chat existente
        $chat = Chat::where(function($query) use ($userId, $contactId) {
            $query->where('user_one_id', $userId)
                  ->where('user_two_id', $contactId);
        })->orWhere(function($query) use ($userId, $contactId) {
            $query->where('user_one_id', $contactId)
                  ->where('user_two_id', $userId);
        })->first();

        // Si no existe, crear uno nuevo
        if (!$chat) {
            $chat = Chat::create([
                'user_one_id' => $userId,
                'user_two_id' => $contactId,
            ]);
        }

        return response()->json([
            'chat_id' => $chat->id
        ]);
    }

    /**
     * Mostrar la vista del chat
     */
    public function showView(Chat $chat): View
    {
        $userId = Auth::id();

        // Verificar que el usuario tiene acceso al chat
        if ($chat->user_one_id !== $userId && $chat->user_two_id !== $userId) {
            abort(403);
        }

        // Obtener el otro usuario del chat
        $otherUser = $chat->user_one_id === $userId ? $chat->userTwo : $chat->userOne;

        return view('contacts.chat', compact('chat', 'otherUser'));
    }

    /**
     * Eliminar un chat (soft o hard). Actualmente eliminación dura y cascada de mensajes.
     * Sólo uno de los participantes puede eliminarlo.
     */
    public function destroy(Chat $chat, GoogleDriveService $drive): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) return response()->json(['error' => 'No autenticado'], 401);
        if ($chat->user_one_id !== $userId && $chat->user_two_id !== $userId) {
            return response()->json(['error' => 'No autorizado'], 403);
        }
        try {
            // Marcar eliminado para este usuario
            ChatUserDeletion::updateOrCreate([
                'chat_id' => $chat->id,
                'user_id' => $userId,
            ], [
                'deleted_at' => now(),
            ]);

            // Si ambos usuarios lo eliminaron, proceder a limpieza real
            $otherUserId = $chat->user_one_id === $userId ? $chat->user_two_id : $chat->user_one_id;
            $otherDeleted = ChatUserDeletion::where('chat_id', $chat->id)->where('user_id', $otherUserId)->exists();
            if ($otherDeleted) {
                $messages = ChatMessage::where('chat_id', $chat->id)->get();
                foreach ($messages as $msg) {
                    if (!empty($msg->drive_file_id)) {
                        try {
                            // Intentar borrar con token del autor del mensaje si existe
                            $owner = User::find($msg->sender_id);
                            $token = $owner ? \App\Models\GoogleToken::where('username', $owner->username)->first() : null;
                            if ($token) {
                                $drive->setAccessToken($token->getTokenArray());
                                $drive->deleteFile($msg->drive_file_id);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('No se pudo eliminar archivo en Drive del chat', [
                                'chat_id' => $chat->id,
                                'file_id' => $msg->drive_file_id,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
                    if (!empty($msg->file_path)) { try { @unlink(storage_path('app/' . ltrim($msg->file_path, '/'))); } catch (\Throwable $e) {} }
                    if (!empty($msg->voice_path)) { try { @unlink(storage_path('app/' . ltrim($msg->voice_path, '/'))); } catch (\Throwable $e) {} }
                }
                ChatMessage::where('chat_id', $chat->id)->delete();
                $chat->delete();
            }
            return response()->json(['status' => 'ok']);
        } catch (\Throwable $e) {
            Log::error('Error marcando chat eliminado', ['chat_id' => $chat->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error eliminando chat'], 500);
        }
    }

    /**
     * Eliminar un mensaje solo para el usuario autenticado (ocultar).
     */
    public function deleteMessageForMe(Chat $chat, ChatMessage $message): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) return response()->json(['error' => 'No autenticado'], 401);
        if ($message->chat_id !== $chat->id) return response()->json(['error' => 'Mensaje no pertenece al chat'], 422);
        if ($chat->user_one_id !== $userId && $chat->user_two_id !== $userId) return response()->json(['error' => 'No autorizado'], 403);

        ChatMessageUserDeletion::firstOrCreate([
            'user_id' => $userId,
            'chat_message_id' => $message->id,
        ], [
            'deleted_at' => now(),
        ]);

        return response()->json(['status' => 'ok']);
    }

    /**
     * Eliminar un mensaje para todos (sólo autor del mensaje).
     */
    public function deleteMessageForAll(Chat $chat, ChatMessage $message, GoogleDriveService $drive): JsonResponse
    {
        $userId = Auth::id();
        if (!$userId) return response()->json(['error' => 'No autenticado'], 401);
        if ($message->chat_id !== $chat->id) return response()->json(['error' => 'Mensaje no pertenece al chat'], 422);
        if ($chat->user_one_id !== $userId && $chat->user_two_id !== $userId) return response()->json(['error' => 'No autorizado'], 403);
        if ($message->sender_id !== $userId) return response()->json(['error' => 'Solo el autor puede eliminar para todos'], 403);

        try {
            // Borrar archivo en Drive si hay
            if (!empty($message->drive_file_id)) {
                try {
                    $owner = User::find($userId);
                    $token = $owner ? GoogleToken::where('username', $owner->username)->first() : null;
                    if ($token) {
                        $drive->setAccessToken($token->getTokenArray());
                        $drive->deleteFile($message->drive_file_id);
                    }
                } catch (\Throwable $e) {
                    Log::warning('No se pudo eliminar archivo en Drive del mensaje', [
                        'message_id' => $message->id,
                        'file_id' => $message->drive_file_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            // Borrar archivos locales si existen
            if (!empty($message->file_path)) {
                try { @unlink(storage_path('app/' . ltrim($message->file_path, '/'))); } catch (\Throwable $e) {}
            }
            if (!empty($message->voice_path)) {
                try { @unlink(storage_path('app/' . ltrim($message->voice_path, '/'))); } catch (\Throwable $e) {}
            }

            // Limpiar adjuntos y marcar como "eliminado" para todos
            $message->body = 'Se eliminó este mensaje';
            $message->file_path = null;
            $message->drive_file_id = null;
            $message->original_name = null;
            $message->mime_type = null;
            $message->file_size = null;
            $message->preview_url = null;
            $message->voice_path = null;
            $message->voice_base64 = null;
            $message->voice_mime = null;
            $message->save();

            // Remover ocultamientos previos (ya no aplican)
            ChatMessageUserDeletion::where('chat_message_id', $message->id)->delete();

            return response()->json(['status' => 'ok', 'message' => $message]);
        } catch (\Throwable $e) {
            Log::error('Error eliminando mensaje', ['message_id' => $message->id, 'error' => $e->getMessage()]);
            return response()->json(['error' => 'Error eliminando mensaje'], 500);
        }
    }
}
