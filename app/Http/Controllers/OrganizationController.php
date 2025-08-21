<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        // Obtener organizaciones del usuario a través de los grupos
        $organizations = Organization::whereHas('groups.users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->with(['groups' => function($query) use ($user) {
            $query->whereHas('users', function($subQuery) use ($user) {
                $subQuery->where('users.id', $user->id);
            });
        }])->get();

        return view('organization.index', [
            'organizations' => $organizations,
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

        $organization = Organization::create($validated + ['num_miembros' => 1]);

        // Crear un grupo principal para la organización y agregar al usuario
        $mainGroup = $organization->groups()->create([
            'nombre_grupo' => 'Grupo Principal',
            'descripcion' => 'Grupo principal de la organización',
        ]);

        $mainGroup->users()->attach($user->id);

        return response()->json($organization, 201);
    }

    public function join(Request $request, $token)
    {
        $organization = Organization::where('id', $token)->firstOrFail();
        $user = $request->user();

        // Buscar el grupo principal de la organización o crear uno si no existe
        $mainGroup = $organization->groups()->first();
        if (!$mainGroup) {
            $mainGroup = $organization->groups()->create([
                'nombre_grupo' => 'Grupo Principal',
                'descripcion' => 'Grupo principal de la organización',
            ]);
        }

        // Agregar usuario al grupo principal
        $mainGroup->users()->syncWithoutDetaching([$user->id]);
        $organization->increment('num_miembros');

        return response()->json(['joined' => true]);
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
}

