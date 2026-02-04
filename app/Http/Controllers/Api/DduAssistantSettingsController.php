<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DduAssistantSetting;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DduAssistantSettingsController extends Controller
{
    /**
     * Obtener configuración del asistente
     * GET /api/ddu/assistant-settings/{userId}
     */
    public function show(string $userId): JsonResponse
    {
        try {
            $settings = DduAssistantSetting::where('user_id', $userId)->first();

            if (!$settings) {
                return response()->json([
                    'success' => true,
                    'data' => null
                ]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $settings->id,
                    'user_id' => $settings->user_id,
                    'openai_api_key_configured' => $settings->hasApiKey(),
                    'enable_drive_calendar' => $settings->enable_drive_calendar,
                    'created_at' => $settings->created_at,
                    'updated_at' => $settings->updated_at,
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener configuración DDU Assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener configuración'
            ], 500);
        }
    }

    /**
     * Crear o actualizar configuración
     * POST /api/ddu/assistant-settings
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'user_id' => 'required|uuid|exists:users,id',
                'openai_api_key' => 'nullable|string|max:255',
                'enable_drive_calendar' => 'nullable|boolean',
            ]);

            // Preparar datos para actualizar
            $updateData = [
                'enable_drive_calendar' => $validated['enable_drive_calendar'] ?? true,
            ];

            // Solo actualizar API key si se proporciona en la petición
            if (array_key_exists('openai_api_key', $validated)) {
                $updateData['openai_api_key'] = $validated['openai_api_key'];
            }

            $settings = DduAssistantSetting::updateOrCreate(
                ['user_id' => $validated['user_id']],
                $updateData
            );

            return response()->json([
                'success' => true,
                'message' => 'Configuración guardada correctamente',
                'data' => [
                    'id' => $settings->id,
                    'user_id' => $settings->user_id,
                    'openai_api_key_configured' => $settings->hasApiKey(),
                    'enable_drive_calendar' => $settings->enable_drive_calendar,
                    'updated_at' => $settings->updated_at,
                ]
            ], $settings->wasRecentlyCreated ? 201 : 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Datos inválidos',
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error al guardar configuración DDU Assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al guardar configuración'
            ], 500);
        }
    }

    /**
     * Obtener API key desencriptada (uso interno)
     * GET /api/ddu/assistant-settings/{userId}/api-key
     */
    public function getApiKey(string $userId): JsonResponse
    {
        try {
            $settings = DduAssistantSetting::where('user_id', $userId)->first();

            if (!$settings || !$settings->hasApiKey()) {
                return response()->json([
                    'success' => false,
                    'message' => 'API key no configurada para este usuario'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'openai_api_key' => $settings->getDecryptedApiKey()
                ]
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al obtener API key DDU Assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener API key'
            ], 500);
        }
    }

    /**
     * Eliminar API key
     * DELETE /api/ddu/assistant-settings/{userId}/api-key
     */
    public function deleteApiKey(string $userId): JsonResponse
    {
        try {
            $settings = DduAssistantSetting::where('user_id', $userId)->first();

            if ($settings) {
                $settings->update(['openai_api_key' => null]);
            }

            return response()->json([
                'success' => true,
                'message' => 'API key eliminada correctamente'
            ]);
        } catch (\Throwable $e) {
            Log::error('Error al eliminar API key DDU Assistant: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar API key'
            ], 500);
        }
    }
}
