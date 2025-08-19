<?php

namespace App\Http\Controllers;

use App\Models\MeetingContentContainer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ContainerController extends Controller
{
    /**
     * Muestra la vista principal de contenedores
     */
    public function index(): View
    {
        $user = Auth::user();
        $containers = MeetingContentContainer::where('username', $user->username)
            ->where('is_active', true)
            ->orderBy('created_at', 'desc')
            ->get();

        return view('containers.index', compact('containers'));
    }

    /**
     * Obtiene todos los contenedores del usuario autenticado
     */
    public function getContainers(): JsonResponse
    {
        try {
            $user = Auth::user();

            $containers = MeetingContentContainer::where('username', $user->username)
                ->where('is_active', true)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($container) {
                    return [
                        'id' => $container->id,
                        'name' => $container->name,
                        'description' => $container->description,
                        'created_at' => $container->created_at->format('d/m/Y H:i'),
                        'meetings_count' => $container->meetingRelations()->count(),
                    ];
                });

            return response()->json([
                'success' => true,
                'containers' => $containers
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar contenedores: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crea un nuevo contenedor
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $user = Auth::user();

            $container = MeetingContentContainer::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'username' => $user->username,
                'is_active' => true,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contenedor creado exitosamente',
                'container' => [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                    'created_at' => $container->created_at->format('d/m/Y H:i'),
                    'meetings_count' => 0,
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al crear el contenedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza un contenedor existente
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
            ]);

            $user = Auth::user();
            $container = MeetingContentContainer::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $container->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Contenedor actualizado exitosamente',
                'container' => [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                    'created_at' => $container->created_at->format('d/m/Y H:i'),
                    'meetings_count' => $container->meetingRelations()->count(),
                ]
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el contenedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Elimina un contenedor (soft delete)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $container = MeetingContentContainer::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $container->update(['is_active' => false]);

            return response()->json([
                'success' => true,
                'message' => 'Contenedor eliminado exitosamente'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar el contenedor: ' . $e->getMessage()
            ], 500);
        }
    }
}
