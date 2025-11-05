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
            // Proteger roles que no deben expirar: BNI, developer, founder, superadmin
            // Usar comparación case-insensitive
            $protectedRoles = ['bni', 'developer', 'founder', 'superadmin'];

            // Si el plan expiró, cambiar todas las columnas relacionadas con el plan
            // No degradar si la bandera is_role_protected está activada
            if (!empty($user->is_role_protected)) {
                return $next($request);
            }

            if (strtolower($user->roles) !== 'free' && !in_array(strtolower($user->roles), $protectedRoles) && !in_array(strtolower($user->plan), $protectedRoles)) {
                $user->update([
                    'roles' => 'free',
                    'plan' => 'free',
                    'plan_code' => 'free'
                ]);

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
