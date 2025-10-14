<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckExpiredPlans
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo verificar una vez cada hora para evitar sobrecarga
        $cacheKey = 'plans_check_last_run';
        $lastRun = Cache::get($cacheKey);
        $now = Carbon::now();

        // Si no se ha ejecutado en la última hora, ejecutar verificación
        if (!$lastRun || $now->diffInHours($lastRun) >= 1) {
            try {
                // Ejecutar comando en background para no bloquear la respuesta
                if (app()->environment('production')) {
                    // En producción, usar queue
                    dispatch(function () {
                        Artisan::call('plans:update-expired');
                    })->onQueue('low');
                } else {
                    // En desarrollo, ejecutar directamente pero rápido
                    Artisan::call('plans:update-expired');
                }

                // Marcar que se ejecutó
                Cache::put($cacheKey, $now, 3600); // Cache por 1 hora

                Log::info('Automatic plan expiration check executed');
            } catch (\Exception $e) {
                Log::error('Error during automatic plan check', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $next($request);
    }
}
