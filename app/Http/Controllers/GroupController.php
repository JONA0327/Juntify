<?php

namespace App\Http\Controllers;

use App\Models\Group;
use Illuminate\Http\Request;

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
            'user_id' => 'required|exists:users,id',
        ]);

        $group->users()->syncWithoutDetaching([$validated['user_id']]);
        $group->increment('miembros');

        return response()->json(['invited' => true]);
    }
}

