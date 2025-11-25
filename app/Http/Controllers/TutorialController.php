<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class TutorialController extends Controller
{
    /**
     * Obtener el estado del tutorial para el usuario actual
     */
    public function getStatus()
    {
        $userId = Auth::id();

        $status = Cache::get("tutorial_status_{$userId}", [
            'completed' => false,
            'current_step' => 0,
            'completed_sections' => [],
            'last_seen' => null,
            'preferences' => [
                'auto_start' => true,
                'show_help_button' => true,
                'skip_completed_sections' => false,
            ]
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $status
        ]);
    }

    /**
     * Actualizar el progreso del tutorial
     */
    public function updateProgress(Request $request)
    {
        $request->validate([
            'progress' => 'integer|min:0|max:100',
            'completed' => 'boolean',
            'completion_date' => 'nullable|date',
            'step' => 'integer|min:0',
            'section' => 'string|max:50',
            'action' => 'string|in:started,completed,cancelled,step_completed'
        ]);

        $userId = Auth::id();
        $cacheKey = "tutorial_status_{$userId}";

        $status = Cache::get($cacheKey, [
            'completed' => false,
            'current_step' => 0,
            'completed_sections' => [],
            'last_seen' => null,
            'progress' => 0,
            'completion_date' => null,
            'preferences' => [
                'auto_start' => true,
                'show_help_button' => true,
                'skip_completed_sections' => false,
            ]
        ]);

        // Si se proporciona progreso y completed directamente (llamada desde JS)
        if ($request->has('progress') && $request->has('completed')) {
            $status['progress'] = $request->progress;
            $status['completed'] = $request->completed;
            $status['completion_date'] = $request->completion_date;
            $status['last_seen'] = now()->toISOString();

            if ($request->completed) {
                $status['current_step'] = 0; // Tutorial completado
            }
        }
        // Manejo por acciones (compatibilidad con código anterior)
        elseif ($request->has('action')) {
            switch ($request->action) {
                case 'started':
                    $status['last_seen'] = now()->toISOString();
                    break;

                case 'completed':
                    $status['completed'] = true;
                    $status['progress'] = 100;
                    $status['current_step'] = 0;
                    $status['completion_date'] = now()->toISOString();
                    $status['last_seen'] = now()->toISOString();
                    if ($request->section) {
                        $status['completed_sections'][] = $request->section;
                        $status['completed_sections'] = array_unique($status['completed_sections']);
                    }
                    break;

                case 'cancelled':
                    $status['last_seen'] = now()->toISOString();
                    break;

                case 'step_completed':
                    $status['current_step'] = $request->step ?? $status['current_step'];
                    $status['last_seen'] = now()->toISOString();
                    break;
            }
        }

        // Guardar en cache por 30 días
        Cache::put($cacheKey, $status, now()->addDays(30));

        return response()->json([
            'status' => 'success',
            'message' => 'Progreso del tutorial actualizado correctamente',
            'data' => $status
        ]);
    }

    /**
     * Actualizar preferencias del tutorial
     */
    public function updatePreferences(Request $request)
    {
        $request->validate([
            'auto_start' => 'boolean',
            'show_help_button' => 'boolean',
            'skip_completed_sections' => 'boolean',
        ]);

        $userId = Auth::id();
        $cacheKey = "tutorial_status_{$userId}";

        $status = Cache::get($cacheKey, [
            'completed' => false,
            'current_step' => 0,
            'completed_sections' => [],
            'last_seen' => null,
            'preferences' => []
        ]);

        $status['preferences'] = array_merge($status['preferences'] ?? [], $request->only([
            'auto_start',
            'show_help_button',
            'skip_completed_sections'
        ]));

        Cache::put($cacheKey, $status, now()->addDays(30));

        return response()->json([
            'status' => 'success',
            'message' => 'Preferencias del tutorial actualizadas',
            'data' => $status
        ]);
    }

    /**
     * Reiniciar el tutorial para el usuario
     */
    public function reset()
    {
        $userId = Auth::id();
        $cacheKey = "tutorial_status_{$userId}";

        Cache::forget($cacheKey);

        return response()->json([
            'status' => 'success',
            'message' => 'Tutorial reiniciado correctamente'
        ]);
    }

    /**
     * Obtener configuración de tutorial para una página específica
     */
    public function getPageConfig(Request $request)
    {
        $page = $request->query('page', 'dashboard');
        $userId = Auth::id();

        $status = Cache::get("tutorial_status_{$userId}", [
            'completed' => false,
            'completed_sections' => [],
            'preferences' => [
                'auto_start' => true,
                'show_help_button' => true,
                'skip_completed_sections' => false,
            ]
        ]);

        $config = [
            'should_auto_start' => !$status['completed'] && ($status['preferences']['auto_start'] ?? true),
            'show_help_button' => $status['preferences']['show_help_button'] ?? true,
            'skip_completed' => $status['preferences']['skip_completed_sections'] ?? false,
            'completed_sections' => $status['completed_sections'] ?? [],
            'page' => $page,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $config
        ]);
    }

    /**
     * Obtener estadísticas del tutorial (para admin)
     */
    public function getStatistics()
    {
        // Solo para administradores
        if (!Auth::user()->isAdmin()) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        // Aquí podrías implementar estadísticas más detalladas
        // Por ahora retornamos datos básicos

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_users' => \App\Models\User::count(),
                'users_completed_tutorial' => 0, // Implementar lógica real
                'completion_rate' => 0,
                'most_common_exit_points' => [],
            ]
        ]);
    }
}
