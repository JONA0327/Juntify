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

        $organizations = $user->organizations()->with('groups')->get();

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

        if ($user->organizations()->exists()) {
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
        $organization->users()->attach($user->id);

        return response()->json($organization, 201);
    }

    public function join(Request $request, $token)
    {
        $organization = Organization::where('id', $token)->firstOrFail();

        $organization->users()->syncWithoutDetaching([$request->user()->id]);
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

