<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;

class CheckOrganizationRole
{
    public function handle(Request $request, Closure $next, ...$roles)
    {
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $organization = $request->route('organization') ?? $request->route('org');
        if (! $organization instanceof Organization) {
            $organization = Organization::find($organization);
        }
        if (! $organization) {
            return response()->json(['message' => 'Organization not found'], 404);
        }

        $roles = array_map('strtolower', $roles);
        $allowedRoles = $roles ?: ['invitado', 'colaborador', 'administrador'];

        $userRole = null;
        if ($organization->admin_id === $user->id) {
            $userRole = 'administrador';
        } else {
            $membership = $organization->users()->where('users.id', $user->id)->first();
            $userRole = $membership ? $membership->pivot->rol : null;
        }
        $userRole = $userRole ? strtolower($userRole) : null;

        if (! $userRole || ! in_array($userRole, $allowedRoles)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
