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
        $group->users()->attach($user->id);

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

    public function invite(Request $request, Group $group)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'method' => 'nullable|in:notification,email',
        ]);

        $user = User::where('email', $validated['email'])->first();
        $method = $validated['method'] ?? null;

        if ($method === 'notification') {
            if (!$user) {
                return response()->json(['error' => 'Usuario no encontrado'], 404);
            }

            Notification::create([
                'remitente' => auth()->id(),
                'emisor' => $user->id,
                'status' => 'pending',
                'message' => "Has sido invitado al grupo {$group->nombre_grupo}",
                'type' => 'group_invitation',
            ]);

            return response()->json(['notified' => true]);
        }

        if ($method === 'email' || !$user) {
            $code = Str::uuid()->toString();
            Mail::to($validated['email'])->send(new GroupInvitation($code, $group->id));

            return response()->json(['invitation_sent' => true]);
        }

        Notification::create([
            'remitente' => auth()->id(),
            'emisor' => $user->id,
            'status' => 'pending',
            'message' => "Has sido invitado al grupo {$group->nombre_grupo}",
            'type' => 'group_invitation',
        ]);

        return response()->json(['notified' => true]);
    }

    public function accept(Group $group)
    {
        $user = auth()->user();

        $group->users()->syncWithoutDetaching([$user->id]);
        $group->increment('miembros');

        Notification::where('emisor', $user->id)
            ->where('type', 'group_invitation')
            ->delete();

        $group->load('users');

        return response()->json(['joined' => true, 'group' => $group]);
    }
}

