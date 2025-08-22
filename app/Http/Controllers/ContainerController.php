<?php

namespace App\Http\Controllers;

use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\TranscriptionLaravel;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;

class ContainerController extends Controller
{
    use GoogleDriveHelpers;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }
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
                'group_id' => 'nullable|exists:groups,id',
            ]);

            $user = Auth::user();

            $container = MeetingContentContainer::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'username' => $user->username,
                'group_id' => $validated['group_id'] ?? null,
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
     * Agrega una reunión existente a un contenedor
     */
    public function addMeeting(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'meeting_id' => ['required', 'exists:transcriptions_laravel,id'],
        ]);

        $container = MeetingContentContainer::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        $meeting = TranscriptionLaravel::where('id', $data['meeting_id'])
            ->where('username', $user->username)
            ->firstOrFail();

        MeetingContentRelation::firstOrCreate([
            'container_id' => $container->id,
            'meeting_id' => $meeting->id,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Elimina una reunión de un contenedor
     */
    public function removeMeeting($containerId, $meetingId): JsonResponse
    {
        try {
            $user = Auth::user();

            $container = MeetingContentContainer::where('id', $containerId)
                ->where('username', $user->username)
                ->firstOrFail();

            $meeting = TranscriptionLaravel::where('id', $meetingId)
                ->where('username', $user->username)
                ->firstOrFail();

            MeetingContentRelation::where('container_id', $container->id)
                ->where('meeting_id', $meeting->id)
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reunión eliminada del contenedor'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reunión del contenedor: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Obtiene las reuniones asociadas a un contenedor
     */
    public function getMeetings($id): JsonResponse
    {
        $user = Auth::user();
        $this->setGoogleDriveToken($user);

        $container = MeetingContentContainer::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        $meetings = $container->meetings()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                    'audio_drive_id' => $meeting->audio_drive_id,
                    'transcript_drive_id' => $meeting->transcript_drive_id,
                    'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                    'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                ];
            });

        return response()->json([
            'success' => true,
            'meetings' => $meetings,
        ]);
    }

    /**
     * Obtiene las reuniones de un contenedor específico
     */
    public function getContainerMeetings($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);
            Log::info("Getting container meetings for container ID: {$id}, user: {$user->username}");

            // Verificar que el contenedor pertenece al usuario
            $container = MeetingContentContainer::where('id', $id)
                ->where('username', $user->username)
                ->where('is_active', true)
                ->firstOrFail();

            Log::info("Container found: " . json_encode($container->toArray()));

            // Obtener las reuniones del contenedor usando la relación del modelo
            $meetings = $container->meetings()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                        'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                    ];
                });

            Log::info("Meetings found: " . $meetings->count());

            $response = [
                'success' => true,
                'container' => [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                ],
                'meetings' => $meetings,
            ];

            Log::info("Response data: " . json_encode($response));

            return response()->json($response);

        } catch (\Exception $e) {
            Log::error("Error in getContainerMeetings: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las reuniones del contenedor: ' . $e->getMessage()
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
