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
        $actor = auth()->user();
        if (!$actor) {
            return response()->json(['error' => 'No autenticado'], 401);
        }
        // Permitir responder si es el invitado (emisor) o quien envió (remitente),
        // para que el emisor pueda aceptar/rechazar y el remitente pueda cancelar.
        $isInvitedUser = (string)$notification->emisor === (string)$actor->id;
        $isSender = (string)$notification->remitente === (string)$actor->id;
        if (!($isInvitedUser || $isSender)) {
            return response()->json(['error' => 'No autorizado para responder esta notificación'], 403);
        }

        if ($request->action === 'accept') {
            // Obtener datos de la invitación
            $data = json_decode($notification->data, true);
            $groupId = $data['group_id'] ?? null;
            $numMiembros = null;

            if ($groupId) {
                $group = Group::find($groupId);
                if ($group) {
                    // Agregar usuario al grupo y asegurar pertenencia a la organización
                    $group->users()->syncWithoutDetaching([$actor->id => ['rol' => 'invitado']]);
                    $group->update(['miembros' => $group->users()->count()]);
                    $org = $group->organization;
                    $org->users()->syncWithoutDetaching([$actor->id => ['rol' => 'invitado']]);
                    $numMiembros = $org->refreshMemberCount();

                    // Actualizar current_organization_id del usuario
                    User::where('id', $actor->id)->update(['current_organization_id' => $org->id]);
                }
            }

            // Eliminar la notificación después de aceptar
            $notification->delete();

            return response()->json([
                'message' => 'Invitación aceptada',
                'num_miembros' => $numMiembros,
            ]);
        } else {
            // Eliminar la notificación después de rechazar
            $notification->delete();

            return response()->json(['message' => 'Invitación rechazada']);
        }
    }
}
