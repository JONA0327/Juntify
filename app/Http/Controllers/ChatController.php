<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Contact;
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

            $chats = Chat::where('user_one_id', $userId)
                ->orWhere('user_two_id', $userId)
                ->with(['messages' => function($query) {
                    $query->latest()->limit(1);
                }])
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

                $lastMessage = $chat->messages->first();

                // Contar mensajes no leídos (mensajes del otro usuario que no han sido leídos por el usuario actual)
                $unreadCount = ChatMessage::where('chat_id', $chat->id)
                    ->where('sender_id', '!=', $userId) // Mensajes del otro usuario
                    ->whereNull('read_at') // No leídos
                    ->count();

                return [
                    'id' => $chat->id,
                    'other_user' => [
                        'id' => $otherUser->id,
                        'name' => $otherUser->full_name,
                        'email' => $otherUser->email,
                        'avatar' => strtoupper(substr($otherUser->full_name, 0, 1))
                    ],
                    'last_message' => $lastMessage ? [
                        'body' => $lastMessage->body,
                        'created_at' => $lastMessage->created_at,
                        'is_mine' => $lastMessage->sender_id === $userId
                    ] : null,
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
    }    public function show(Chat $chat): JsonResponse
    {
        $userId = Auth::id();
        abort_unless($chat->user_one_id === $userId || $chat->user_two_id === $userId, 403);

        // Marcar todos los mensajes del otro usuario como leídos
        ChatMessage::where('chat_id', $chat->id)
            ->where('sender_id', '!=', $userId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $messages = $chat->messages()->with('sender')->orderBy('created_at')->get();

        return response()->json($messages);
    }

    public function store(Request $request, Chat $chat): JsonResponse
    {
        $user = Auth::user();
        abort_unless($chat->user_one_id === $user->id || $chat->user_two_id === $user->id, 403);

        $data = $request->validate([
            'body' => 'nullable|string',
            'file' => 'nullable|file',
            'voice' => 'nullable|file',
        ]);

        $filePath = $request->file('file') ? $request->file('file')->store('chat_files') : null;
        $voicePath = $request->file('voice') ? $request->file('voice')->store('chat_files') : null;

        $message = $chat->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'] ?? null,
            'file_path' => $filePath,
            'voice_path' => $voicePath,
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
            'contact_id' => 'required|exists:users,id'
        ]);

        $userId = Auth::id();
        $contactId = $request->contact_id;

        // Verificar que son contactos
        $isContact = Contact::where('user_id', $userId)
            ->where('contact_id', $contactId)
            ->exists();

        if (!$isContact) {
            return response()->json(['error' => 'No son contactos'], 403);
        }

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
}
