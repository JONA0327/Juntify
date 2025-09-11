<?php

namespace App\Http\Controllers;

use App\Models\Chat;
use App\Models\ChatMessage;
use App\Models\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
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

            // Soporte para ver si hay cambios desde un timestamp (ISO / Y-m-d H:i:s)
            $since = request('since');
            $sinceTs = $since ? strtotime($since) : null;

            // Cache muy corto (evita múltiples queries en pocos segundos)
            $cacheKey = 'chat_list_user_' . $userId;
            $debugAll = request('debug_all') == 1; // listar todos los chats para diagnóstico
            $payload = Cache::remember($cacheKey . ($debugAll ? '_all' : ''), 8, function () use ($userId, $debugAll) {
                // 1. Obtener chats (sin mensajes) + usuarios relacionados (2 queries)
                $baseQuery = Chat::with(['userOne:id,full_name,email', 'userTwo:id,full_name,email'])
                    ->orderByDesc('updated_at');

                if (!$debugAll) {
                    $baseQuery->where(function($q) use ($userId) {
                        $q->where('user_one_id', $userId)->orWhere('user_two_id', $userId);
                    });
                }

                $chats = $baseQuery->get();

                if ($chats->isEmpty()) {
                    return [ 'list' => collect(), 'last_updated_unix' => null, 'last_updated_iso' => null ];
                }

                $chatIds = $chats->pluck('id');

                // 2. Últimos mensajes por chat (usa subconsulta agregada) (1 query)
                $lastMessageIds = ChatMessage::selectRaw('MAX(id) as id, chat_id')
                    ->whereIn('chat_id', $chatIds)
                    ->groupBy('chat_id');

                $lastMessages = ChatMessage::select('id','chat_id','body','created_at','sender_id')
                    ->whereIn('id', $lastMessageIds->pluck('id'))
                    ->get()
                    ->keyBy('chat_id');

                // 3. Conteos de no leídos agregados (1 query)
                $unreadCounts = ChatMessage::selectRaw('chat_id, COUNT(*) as unread')
                    ->whereIn('chat_id', $chatIds)
                    ->where('sender_id', '!=', $userId)
                    ->whereNull('read_at')
                    ->groupBy('chat_id')
                    ->pluck('unread', 'chat_id');

                $formatted = $chats->map(function($chat) use ($userId, $lastMessages, $unreadCounts) {
                    $otherUser = $chat->user_one_id === $userId ? $chat->userTwo : $chat->userOne;
                    if (!$otherUser) return null;
                    $lastMessage = $lastMessages->get($chat->id);
                    $unread = (int) ($unreadCounts[$chat->id] ?? 0);
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
                        'unread_count' => $unread,
                        'has_unread' => $unread > 0,
                        'updated_at' => $chat->updated_at,
                        'updated_at_unix' => $chat->updated_at ? $chat->updated_at->getTimestamp() : null,
                    ];
                })->filter()->values();

                $lastUpdated = $formatted->max('updated_at_unix');
                return [
                    'list' => $formatted,
                    'last_updated_unix' => $lastUpdated,
                    'last_updated_iso' => $lastUpdated ? date('c', $lastUpdated) : null,
                    'raw_total' => $chats->count(),
                    'debug_all' => $debugAll,
                ];
            });

            // Si el cliente envía ?since= y no hubo cambios posteriores devolver indicador rápido
            if ($sinceTs && $payload['last_updated_unix'] && $sinceTs >= $payload['last_updated_unix']) {
                return response()->json([
                    'no_changes' => true,
                    'last_updated' => $payload['last_updated_iso']
                ]);
            }

            $response = [
                'no_changes' => false,
                'last_updated' => $payload['last_updated_iso'],
                'chats' => $payload['list']
            ];
        if (request('debug') == 1) {
                $response['_debug'] = [
                    'auth_user_id' => $userId,
                    'raw_total' => $payload['raw_total'] ?? null,
                    'list_count' => is_countable($payload['list']) ? count($payload['list']) : null,
                    'first_chat_ids' => $payload['list'][0]['id'] ?? null,
            'debug_all' => $payload['debug_all'] ?? false,
                ];
            }
            return response()->json($response);
        } catch (\Throwable $e) {
            Log::error('Error en apiIndex de Chat', [
                'user_id' => Auth::id(),
                'message' => $e->getMessage(),
                'trace' => substr($e->getTraceAsString(),0,1200)
            ]);
            // Intentar fallback desde caché previa
            try {
                $userId = Auth::id();
                $cacheKey = 'chat_list_user_' . $userId;
                $cached = Cache::get($cacheKey);
                if ($cached && isset($cached['list'])) {
                    return response()->json([
                        'no_changes' => false,
                        'last_updated' => $cached['last_updated_iso'] ?? null,
                        'chats' => $cached['list'],
                        'rate_limited' => true,
                        'warning' => 'Servicio de chat degradado: usando datos en caché.'
                    ], 200);
                }
            } catch (\Throwable $inner) {
                // Ignorar
            }
            // Si no había caché previa devolver lista vacía degradada (mejor UX que 503 duro)
            return response()->json([
                'no_changes' => false,
                'last_updated' => null,
                'chats' => [],
                'rate_limited' => true,
                'warning' => 'Servicio de chat degradado: sin datos disponibles aún.'
            ], 200);
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
