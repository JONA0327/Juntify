<?php

namespace App\Http\Controllers;

use App\Mail\GroupInvitation;
use App\Models\Group;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class GroupController extends Controller
{
    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $validated = $request->validate([
            'id_organizacion' => 'required|exists:organizations,id',
            'nombre_grupo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $group = Group::create($validated + ['miembros' => 1]);
        $group->users()->attach($user->id, ['rol' => 'full_meeting_access']);

        return response()->json($group, 201);
    }

    public function show(Group $group)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $group->load(['organization', 'users']);

        return response()->json($group);
    }

    public function update(Request $request, Group $group)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $validated = $request->validate([
            'nombre_grupo' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
        ]);

        $group->update($validated);

        return response()->json($group->fresh());
    }

    public function invite(Request $request, Group $group)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'send_notification' => 'boolean',
        ]);

        $user = User::where('email', $validated['email'])->first();
        $sendNotification = $validated['send_notification'] ?? false;

        if ($sendNotification && $user) {
            // Usuario existe en Juntify - enviar notificación interna
            Notification::create([
                'remitente' => auth()->id(),
                'emisor' => $user->id,
                'status' => 'pending',
                'message' => "Has sido invitado al grupo {$group->nombre_grupo}",
                'type' => 'group_invitation',
                'data' => json_encode(['group_id' => $group->id])
            ]);

            return response()->json([
                'success' => true,
                'type' => 'notification',
                'message' => 'Notificación enviada al usuario de Juntify'
            ]);
        } else {
            // Usuario no existe o se forzó email - enviar por correo
            $code = Str::uuid()->toString();

            // Aquí puedes implementar el envío de email
            // Mail::to($validated['email'])->send(new GroupInvitation($code, $group->id));

            return response()->json([
                'success' => true,
                'type' => 'email',
                'message' => 'Invitación enviada por correo electrónico'
            ]);
        }
    }

    public function accept(Group $group)
    {
        $user = auth()->user();

        $group->users()->syncWithoutDetaching([$user->id => ['rol' => 'meeting_viewer']]);
        $group->increment('miembros');

        Notification::where('emisor', $user->id)
            ->where('type', 'group_invitation')
            ->delete();

        $group->load('users');

        return response()->json(['joined' => true, 'group' => $group]);
    }

    public function members(Group $group)
    {
        $group->load('users');

        return view('group.members', compact('group'));
    }

    public function updateMemberRole(Request $request, Group $group, User $user)
    {
        $validated = $request->validate([
            'rol' => 'required|in:meeting_viewer,full_meeting_access',
        ]);

        $group->users()->updateExistingPivot($user->id, ['rol' => $validated['rol']]);

        return response()->json(['role_updated' => true]);
    }
}

