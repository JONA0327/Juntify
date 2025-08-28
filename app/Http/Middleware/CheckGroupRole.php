<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CheckGroupRole
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $groupId = $request->route('group') ?? $request->input('group_id');

        if ($groupId) {
            // Si la acción es sobre un grupo concreto, bloquear sólo si el rol en ese grupo es invitado
            $role = DB::table('group_user')
                ->where('user_id', $user->id)
                ->where('id_grupo', $groupId)
                ->value('rol');
            if ($role === 'invitado') {
                return response()->json(['message' => 'Forbidden'], 403);
            }
        } else {
            // Acciones generales (p.ej. crear reunión): permitir si tiene al menos un grupo con rol distinto a invitado
            $hasGroups = DB::table('group_user')->where('user_id', $user->id)->exists();
            if ($hasGroups) {
                $hasPrivilege = DB::table('group_user')
                    ->where('user_id', $user->id)
                    ->where('rol', '!=', 'invitado')
                    ->exists();
                if (! $hasPrivilege) {
                    return response()->json(['message' => 'Forbidden'], 403);
                }
            }
            // Si no pertenece a ningún grupo, permitir
        }

        return $next($request);
    }
}
