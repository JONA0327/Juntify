<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\User;

class CheckExpiredPlan
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $user->isPlanExpired()) {
            // Si el plan expiró, cambiar rol a free
            if ($user->roles !== 'free') {
                $user->update(['roles' => 'free']);

                // Si es una petición AJAX, devolver info del plan expirado
                if ($request->expectsJson()) {
                    return response()->json([
                        'plan_expired' => true,
                        'message' => 'Tu plan ha expirado. Has sido cambiado al plan gratuito.'
                    ], 200);
                }

                // Para peticiones web, agregar flag a la sesión
                session()->flash('plan_expired', true);
                session()->flash('plan_expired_message', 'Tu plan ha expirado. Has sido cambiado al plan gratuito.');
            }
        }

        return $next($request);
    }
}
