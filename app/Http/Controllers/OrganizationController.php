<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Obtener organizaciones del usuario a través de los grupos
        $organizations = Organization::whereHas('groups', function ($query) use ($user) {
            $query->whereHas('users', function ($subQuery) use ($user) {
                $subQuery->where('users.id', $user->id);
            });
        })->with([
            'groups' => function ($query) use ($user) {
                $query->whereHas('users', function ($subQuery) use ($user) {
                    $subQuery->where('users.id', $user->id);
                })->with(['users', 'code']);
            }
        ])->get();

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

        // Si es una petición AJAX/API, devolver JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'organizations' => $organizations,
                'user' => $user,
                'isOnlyGuest' => $isOnlyGuest,
            ]);
        }

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
            'num_miembros' => 0, // Cambiar a 0 ya que no se crea grupo automático
            'admin_id' => $user->id
        ]);

        return response()->json($organization, 201);
    }

    public function join(Request $request, $token)
    {
        $organization = Organization::where('id', $token)->firstOrFail();
        $user = $request->user();

        // Verificar si el usuario ya pertenece a alguna organización
        $alreadyMember = Organization::whereHas('groups.users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->exists();

        if ($alreadyMember) {
            return response()->json([
                'message' => 'El usuario ya pertenece a una organización y no puede unirse a otra'
            ], 409);
        }

        // Buscar el grupo principal de la organización
        $mainGroup = $organization->groups()->first();
        if (!$mainGroup) {
            return response()->json([
                'message' => 'No existe un grupo al que unirse'
            ], 404);
        }

        // Agregar usuario al grupo principal con rol invitado por defecto
        $mainGroup->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
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
        if (!$user) {
            Log::error('Organization update: Usuario no autenticado');
            abort(403, 'Usuario no autenticado');
        }

        Log::info('Organization update attempt', [
            'user_id' => $user->id,
            'org_id' => $organization->id,
            'org_admin_id' => $organization->admin_id,
            'user_roles' => $user->roles
        ]);

        // Verificar si el usuario es el administrador de la organización
        $isOwner = $organization->admin_id === $user->id;

        // También verificar si tiene rol de administrador en algún grupo de la organización
        $hasAdminRole = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'administrador');
        })->exists();

        Log::info('Permission check', [
            'isOwner' => $isOwner,
            'hasAdminRole' => $hasAdminRole
        ]);

        if (!$isOwner && !$hasAdminRole) {
            Log::error('Organization update: Permisos insuficientes', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'isOwner' => $isOwner,
                'hasAdminRole' => $hasAdminRole
            ]);
            abort(403, 'No tienes permisos para editar esta organización');
        }

        $validated = $request->validate([
            'nombre_organizacion' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|string',
        ]);

        $organization->update($validated);

        Log::info('Organization updated successfully', ['org_id' => $organization->id]);

        // Cargar la organización con sus relaciones para mantener la integridad de los datos
        $organizationWithRelations = $organization->fresh()->load([
            'groups' => function ($query) {
                $query->withCount('users');
            }
        ]);

        Log::info('Organization with relations loaded', [
            'org_id' => $organizationWithRelations->id,
            'groups_count' => $organizationWithRelations->groups->count(),
            'groups' => $organizationWithRelations->groups->pluck('id', 'nombre_grupo')
        ]);

        return response()->json($organizationWithRelations);
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

        $isOwner = $organization->admin_id === $user->id;
        $hasAdminRole = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'administrador');
        })->exists();

        if (!$isOwner && !$hasAdminRole) {
            abort(403);
        }

        foreach ($organization->groups as $group) {
            $group->users()->detach();
        }

        $organization->delete();

        return response()->json(['deleted' => true]);
    }
}
