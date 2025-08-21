<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Notification;
use App\Models\Group;

class UserController extends Controller
{
    public function checkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email'
        ]);

        $userExists = User::where('email', $request->email)->exists();

        return response()->json([
            'exists' => $userExists,
            'message' => $userExists ? 'Usuario encontrado en Juntify' : 'Usuario no encontrado'
        ]);
    }

    public function getNotifications()
    {
        $notifications = Notification::where('emisor', auth()->id())
            ->with(['remitente:id,username,full_name'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($notifications);
    }

    public function respondToNotification(Request $request, Notification $notification)
    {
        $request->validate([
            'action' => 'required|in:accept,reject'
        ]);

        // Verificar que la notificación pertenece al usuario autenticado
        if ($notification->emisor !== auth()->id()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        if ($request->action === 'accept') {
            // Obtener datos de la invitación
            $data = json_decode($notification->data, true);
            $groupId = $data['group_id'] ?? null;

            if ($groupId) {
                $group = Group::find($groupId);
                if ($group) {
                    // Agregar usuario al grupo
                    $group->users()->syncWithoutDetaching([auth()->id() => ['rol' => 'meeting_viewer']]);
                    $group->increment('miembros');
                }
            }

            $notification->update(['status' => 'accepted']);
            return response()->json(['message' => 'Invitación aceptada', 'status' => 'accepted']);
        } else {
            $notification->update(['status' => 'rejected']);
            return response()->json(['message' => 'Invitación rechazada', 'status' => 'rejected']);
        }
    }
}
