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

        $role = DB::table('group_user')
            ->where('user_id', $user->id)
            ->when($groupId, function ($query) use ($groupId) {
                $query->where('id_grupo', $groupId);
            })
            ->value('rol');

        if ($role === 'invitado') {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return $next($request);
    }
}
