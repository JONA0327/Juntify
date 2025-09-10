<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Log;

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

            $notifications = Notification::where($userIdColumn, Auth::id())
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
                    if ($notification->fromUser) {
                        $u = $notification->fromUser;
                        $name = $u->full_name ?? $u->username ?? 'Usuario';
                        $fromUser = [
                            'id' => $u->id,
                            'name' => $name,
                            'email' => $u->email ?? '',
                            'avatar' => strtoupper(substr($name, 0, 1))
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
                        'from_user' => $fromUser
                    ];
                });

            return response()->json($notifications);

        } catch (\Exception $e) {
            Log::error('Error en NotificationController::index: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return response()->json(['error' => 'Error interno del servidor'], 500);
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
                if ($notification->fromUser) {
                    $u = $notification->fromUser;
                    $name = $u->full_name ?? $u->username ?? 'Usuario';
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
