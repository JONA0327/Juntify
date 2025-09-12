<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationController extends Controller
{
    /**
     * Obtener todas las notificaciones del usuario actual
     */
    public function index(): JsonResponse
    {
    try {
            if (!auth()->check()) {
                return response()->json([], 401);
            }

            // Verificar qué columnas usar basándose en el esquema actual
            $userIdColumn = Schema::hasColumn('notifications', 'user_id') ? 'user_id' : 'emisor';

            $userId = Auth::id();
            $since = request('since');
            $sinceTs = $since ? strtotime($since) : null;

            $cacheKey = 'notifications_user_' . $userId;
            $payload = Cache::remember($cacheKey, 10, function () use ($userIdColumn, $userId) {
                $collection = Notification::where($userIdColumn, $userId)
                    ->orderBy('created_at', 'desc')
                    ->with('fromUser')
                    ->get()
                    ->map(function ($notification) use ($userIdColumn) {
                    $data = null;
                    if ($notification->data) {
                        try {
                            $data = is_array($notification->data)
                                ? $notification->data
                                : json_decode($notification->data, true, 512, JSON_THROW_ON_ERROR);
                        } catch (\Throwable $e) {
                            $data = null;
                        }
                    }

                    // Información del remitente usando la relación dinámica (from_user_id o remitente)
                    $fromUser = null;
                    $legacyRemitente = null; // compatibilidad con frontend antiguo
                    $u = null;
                    // Intentar primero relación dinámica (from_user_id o remitente según schema)
                    if ($notification->fromUser) {
                        $u = $notification->fromUser;
                    } else {
                        // Fallback: si existe columna remitente con valor pero from_user_id es null
                        try {
                            if ($notification->getAttribute('remitente')) {
                                $u = $notification->remitente()->first();
                            }
                        } catch (\Throwable $e) {
                            // Ignorar errores de fallback
                        }
                    }
                    if ($u) {
                        $name = $u->full_name;
                        if (!$name || trim($name) === '') {
                            $name = $u->username ?: ($u->email ?: 'Usuario');
                        }
                        $fromUser = [
                            'id' => $u->id,
                            'name' => $name,
                            'email' => $u->email ?? '',
                            'avatar' => strtoupper(substr($name, 0, 1))
                        ];
                        $legacyRemitente = [
                            'id' => $u->id,
                            'full_name' => $u->full_name ?: $name,
                            'username' => $u->username,
                            'email' => $u->email ?? ''
                        ];
                    }

                        return [
                            'id' => $notification->id,
                            'type' => $notification->type ?? 'general',
                            'title' => $notification->title ?? 'Notificación',
                            'message' => $notification->message ?? '',
                            'data' => $data,
                            'read' => $notification->read ?? false,
                            'read_at' => $notification->read_at ?? null,
                            'created_at' => $notification->created_at,
                            'created_at_unix' => $notification->created_at ? $notification->created_at->getTimestamp() : null,
                            'from_user' => $fromUser,
                            // Campos de compatibilidad
                            'remitente' => $legacyRemitente,
                            'sender_name' => $fromUser['name'] ?? 'Usuario'
                        ];
                    });
                $lastUpdated = $collection->max('created_at_unix');
                return [
                    'list' => $collection->values(),
                    'last_updated_unix' => $lastUpdated,
                    'last_updated_iso' => $lastUpdated ? date('c', $lastUpdated) : null,
                ];
            });

            if ($sinceTs && $payload['last_updated_unix'] && $sinceTs >= $payload['last_updated_unix']) {
                return response()->json([
                    'no_changes' => true,
                    'last_updated' => $payload['last_updated_iso']
                ]);
            }

            return response()->json([
                'no_changes' => false,
                'last_updated' => $payload['last_updated_iso'],
                'notifications' => $payload['list']
            ]);

        } catch (\Throwable $e) {
            // Intentar servir datos en caché si existen para degradación elegante
            try {
                $userId = Auth::id();
                $fallback = Cache::get('notifications_user_' . $userId);
                if ($fallback && is_array($fallback) && isset($fallback['list'])) {
                    return response()->json([
                        'no_changes' => false,
                        'last_updated' => $fallback['last_updated_iso'] ?? null,
                        'notifications' => $fallback['list'],
                        'rate_limited' => true,
                        'warning' => 'Servicio degradado: usando datos en caché.'
                    ], 200);
                }
            } catch (\Throwable $inner) {
                // Ignorar errores de fallback
            }

            Log::error('Error en NotificationController::index (sin fallback): ' . $e->getMessage());
            return response()->json([
                'error' => 'Servicio temporalmente no disponible',
                'rate_limited' => true
            ], 503);
        }
    }

    /**
     * Obtener solo notificaciones no leídas
     */
    public function unread(): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json([], 401);
        }

        $notifications = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->with(['fromUser'])
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($notification) {
                $data = null;
                if ($notification->data) {
                    try {
                        $data = is_array($notification->data)
                            ? $notification->data
                            : json_decode($notification->data, true, 512, JSON_THROW_ON_ERROR);
                    } catch (\Throwable $e) {
                        $data = null;
                    }
                }

                $fromUser = null;
                $u = null;
                if ($notification->fromUser) {
                    $u = $notification->fromUser;
                } else if ($notification->getAttribute('remitente')) {
                    try { $u = $notification->remitente()->first(); } catch (\Throwable $e) { /* ignore */ }
                }
                if ($u) {
                    $name = $u->full_name;
                    if (!$name || trim($name) === '') {
                        $name = $u->username ?: ($u->email ?: 'Usuario');
                    }
                    $fromUser = [
                        'id' => $u->id,
                        'name' => $name,
                        'avatar' => strtoupper(substr($name, 0, 1))
                    ];
                }

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'data' => $data,
                    'created_at' => $notification->created_at,
                    'from_user' => $fromUser
                ];
            });

        return response()->json($notifications);
    }

    /**
     * Marcar una notificación como leída
     */
    public function markAsRead($id): JsonResponse
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->update([
                'read' => true,
                'read_at' => now()
            ]);

            return response()->json(['success' => true, 'message' => 'Notificación marcada como leída']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
    }

    /**
     * Marcar todas las notificaciones como leídas
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            Notification::where('user_id', Auth::id())
                ->where('read', false)
                ->update([
                    'read' => true,
                    'read_at' => now()
                ]);

            return response()->json(['success' => true, 'message' => 'Todas las notificaciones marcadas como leídas']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al marcar notificaciones'], 500);
        }
    }

    /**
     * Contar notificaciones no leídas
     */
    public function getUnreadCount(): JsonResponse
    {
        $count = Notification::where('user_id', Auth::id())
            ->where('read', false)
            ->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Crear una nueva notificación
     */
    public function store(Request $request): JsonResponse
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $request->validate([
            'type' => 'required|string',
            'title' => 'required|string',
            'message' => 'required|string',
            'data' => 'nullable|array',
            'user_id' => 'required|exists:users,id'
        ]);

        $notification = Notification::create([
            'user_id' => $request->user_id,
            'from_user_id' => Auth::id(),
            'type' => $request->type,
            'title' => $request->title,
            'message' => $request->message,
            'data' => $request->data ? json_encode($request->data) : null,
            'read' => false
        ]);

        return response()->json($notification, 201);
    }

    /**
     * Eliminar una notificación
     */
    public function destroy($id): JsonResponse
    {
        try {
            $notification = Notification::where('id', $id)
                ->where('user_id', Auth::id())
                ->firstOrFail();

            $notification->delete();

            return response()->json(['success' => true, 'message' => 'Notificación eliminada']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Notificación no encontrada'], 404);
        }
    }
}
