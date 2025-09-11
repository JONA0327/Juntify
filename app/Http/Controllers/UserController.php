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

        $actor = auth()->user();
        if (!$actor) {
            return response()->json(['error' => 'No autenticado'], 401);
        }

        // Compatibilidad: identificar columnas nuevas o antiguas
        $targetUserId = $notification->user_id ?? $notification->emisor; // destinatario
        $senderUserId = $notification->from_user_id ?? $notification->remitente; // quien envía

        // Autorización: sólo destinatario puede aceptar/rechazar; el remitente podría cancelar (rechazar) si fuera necesario
        $isTarget = (string)$targetUserId === (string)$actor->id;
        $isSender = (string)$senderUserId === (string)$actor->id;
        if (!$isTarget && !$isSender) {
            return response()->json(['error' => 'No autorizado para responder esta notificación'], 403);
        }

        // Trabajar con data ya casteada a array (ver $casts en modelo)
        $data = is_array($notification->data) ? $notification->data : (array)json_decode($notification->data ?? '[]', true);
        $groupId = $data['group_id'] ?? null;
        $role = $data['role'] ?? 'invitado';

        // Si es acción reject: simplemente borrar y salir
        if ($request->action === 'reject') {
            $notification->delete();
            return response()->json(['message' => 'Invitación rechazada']);
        }

        // Aceptar
        if (!$groupId) {
            // Datos corruptos o antiguos sin group_id
            $notification->delete();
            return response()->json(['message' => 'Invitación inválida o grupo inexistente (limpiada)'], 410);
        }

        $group = Group::find($groupId);
        if (!$group) {
            $notification->delete();
            return response()->json(['message' => 'El grupo ya no existe (invitación removida)'], 410);
        }

        // Prevenir mezcla de organizaciones distintas
        if ($actor->current_organization_id && $actor->current_organization_id !== $group->id_organizacion) {
            return response()->json(['message' => 'Ya perteneces a otra organización'], 409);
        }

        // Añadir al grupo con el rol indicado (fallback a invitado)
        $group->users()->syncWithoutDetaching([$actor->id => ['rol' => $role]]);
        $group->update(['miembros' => $group->users()->count()]);

        // Asegurar pertenencia a la organización
        $org = $group->organization;
        if ($org) {
            $org->users()->syncWithoutDetaching([$actor->id => ['rol' => $role]]);
            $numMiembros = $org->refreshMemberCount();
            User::where('id', $actor->id)->update(['current_organization_id' => $org->id]);
        } else {
            $numMiembros = null;
        }

        // Limpiar notificación
        $notification->delete();

        return response()->json([
            'message' => 'Invitación aceptada',
            'num_miembros' => $numMiembros,
            'group_id' => $group->id,
            'organization_id' => $org?->id,
            'role' => $role
        ]);
    }
}
