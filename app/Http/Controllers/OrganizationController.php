<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Group;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Obtener organizaciones del usuario a través de los grupos
        $organizations = Organization::whereHas('groups.users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->with(['groups' => function($query) use ($user) {
            $query->whereHas('users', function($subQuery) use ($user) {
                $subQuery->where('users.id', $user->id);
            })->with(['users' => function($q) use ($user) {
                $q->where('users.id', $user->id);
            }]);
        }])->get();

        // Marcar si el usuario es propietario de la organización y obtener su rol más alto
        $organizations->each(function ($organization) use ($user) {
            $organization->is_owner = $organization->admin_id === $user->id;

            // Obtener el rol más alto del usuario en esta organización
            $userRoles = $organization->groups->flatMap->users
                ->where('id', $user->id)
                ->pluck('pivot.rol')
                ->unique();

            $organization->user_role = $userRoles->contains('administrador') ? 'administrador' :
                                     ($userRoles->contains('colaborador') ? 'colaborador' : 'invitado');
        });

        // Verificar si el usuario es solo invitado en todas las organizaciones
        $isOnlyGuest = $organizations->every(function($org) {
            return $org->user_role === 'invitado';
        });

        return view('organization.index', [
            'organizations' => $organizations,
            'user' => $user,
            'isOnlyGuest' => $isOnlyGuest,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        // Verificar si el usuario ya pertenece a una organización
        $hasOrganization = Organization::whereHas('groups.users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->exists();

        if ($hasOrganization) {
            return response()->json([
                'message' => 'Ya perteneces a una organización'],
                403
            );
        }

        $validated = $request->validate([
            'nombre_organizacion' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|string',
        ]);

        $organization = Organization::create($validated + [
            'num_miembros' => 1,
            'admin_id' => $user->id
        ]);

        // Crear grupo principal de la organización
        $mainGroup = Group::create([
            'nombre_grupo' => 'General',
            'descripcion' => 'Grupo principal de la organización',
            'id_organizacion' => $organization->id,
            'miembros' => 1
        ]);

        // Agregar al usuario como administrador del grupo principal
        $mainGroup->users()->attach($user->id, ['rol' => 'administrador']);

        return response()->json($organization, 201);
    }

    public function join(Request $request, $token)
    {
        $organization = Organization::where('id', $token)->firstOrFail();
        $user = $request->user();

        // Buscar el grupo principal de la organización
        $mainGroup = $organization->groups()->first();
        if (!$mainGroup) {
            return response()->json([
                'message' => 'No existe un grupo al que unirse'
            ], 404);
        }

        // Agregar usuario al grupo principal
        $mainGroup->users()->syncWithoutDetaching([$user->id]);
        $organization->increment('num_miembros');

        return response()->json(['joined' => true]);
    }

    public function leave(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Obtener todas las organizaciones del usuario
        $organizations = Organization::whereHas('groups.users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->get();

        foreach ($organizations as $organization) {
            // Si es el admin de la organización, no puede salirse
            if ($organization->admin_id === $user->id) {
                return response()->json([
                    'message' => 'No puedes salir de una organización que administras'
                ], 403);
            }

            // Remover de todos los grupos de la organización
            foreach ($organization->groups as $group) {
                $group->users()->detach($user->id);
                $group->update(['miembros' => $group->users()->count()]);
            }

            // Actualizar contador de miembros de la organización
            $organization->refreshMemberCount();
        }

        return response()->json(['left' => true]);
    }

    public function update(Request $request, Organization $organization)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $isOwner = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'owner');
        })->exists();

        if (!$isOwner) {
            abort(403);
        }

        $validated = $request->validate([
            'nombre_organizacion' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|string',
        ]);

        $organization->update($validated);

        return response()->json($organization->fresh());
    }

    public function show(Organization $organization)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $organization->load(['groups', 'users']);

        return response()->json($organization);
    }

    public function destroy(Organization $organization)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $isOwner = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'owner');
        })->exists();

        if (!$isOwner) {
            abort(403);
        }

        foreach ($organization->groups as $group) {
            $group->users()->detach();
        }

        $organization->delete();

        return response()->json(['deleted' => true]);
    }
}

