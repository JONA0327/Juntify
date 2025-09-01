<?php

namespace App\Http\Controllers;

use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\TranscriptionLaravel;
use App\Models\OrganizationActivity;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
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

    private function userHasContainerPrivileges($user, $groupId = null): bool
    {
        if ($groupId) {
            $role = DB::table('group_user')
                ->where('user_id', $user->id)
                ->where('id_grupo', $groupId)
                ->value('rol');
            // Admite roles nuevos y legados
            return in_array($role, ['colaborador', 'administrador', 'full_meeting_access'], true);
        }

        // Acciones generales: permitir si tiene algún grupo con rol distinto a invitado
        return DB::table('group_user')
            ->where('user_id', $user->id)
            ->whereIn('rol', ['colaborador', 'administrador', 'full_meeting_access'])
            ->exists();
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

            $containers = MeetingContentContainer::with('group')
                ->where('username', $user->username)
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
                        'is_company' => $container->group_id !== null,
                        'group_name' => $container->group->nombre_grupo ?? null,
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

            if (! $this->userHasContainerPrivileges($user, $validated['group_id'] ?? null)) {
                return response()->json(['success' => false, 'message' => 'No tienes permisos para crear contenedores en este grupo'], 403);
            }

            $container = MeetingContentContainer::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'username' => $user->username,
                'group_id' => $validated['group_id'] ?? null,
                'is_active' => true,
            ]);

            $group = null;
            $organizationId = null;
            if ($container->group_id) {
                $group = Group::find($container->group_id);
                $organizationId = $group ? $group->id_organizacion : null;
            }

            OrganizationActivity::create([
                'organization_id' => $organizationId,
                'group_id' => $container->group_id,
                'container_id' => $container->id,
                'user_id' => $user->id,
                'action' => 'create',
                'description' => $user->name . ' creó el contenedor ' . $container->name,
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
                    'is_company' => $container->group_id !== null,
                    'group_name' => $group->nombre_grupo ?? null,
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

            if (! $this->userHasContainerPrivileges($user, $container->group_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $container->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
            ]);

            $organizationId = null;
            $group = null;
            if ($container->group_id) {
                $group = Group::find($container->group_id);
                $organizationId = $group ? $group->id_organizacion : null;
            }

            OrganizationActivity::create([
                'organization_id' => $organizationId,
                'group_id' => $container->group_id,
                'container_id' => $container->id,
                'user_id' => $user->id,
                'action' => 'update',
                'description' => $user->name . ' actualizó el contenedor ' . $container->name,
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
                    'is_company' => $container->group_id !== null,
                    'group_name' => $group->nombre_grupo ?? null,
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
            $container = MeetingContentContainer::with('group')
                ->where('id', $id)
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
                        'has_transcript' => !empty($meeting->transcript_drive_id),
                    ];
                });

            Log::info("Meetings found: " . $meetings->count());

            $response = [
                'success' => true,
                'container' => [
                    'id' => $container->id,
                    'name' => $container->name,
                    'description' => $container->description,
                    'is_company' => $container->group_id !== null,
                    'group_name' => $container->group->nombre_grupo ?? null,
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

            if (! $this->userHasContainerPrivileges($user, $container->group_id)) {
                return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
            }

            $container->update(['is_active' => false]);

            $organizationId = null;
            if ($container->group_id) {
                $group = Group::find($container->group_id);
                $organizationId = $group ? $group->id_organizacion : null;
            }

            OrganizationActivity::create([
                'organization_id' => $organizationId,
                'group_id' => $container->group_id,
                'container_id' => $container->id,
                'user_id' => $user->id,
                'action' => 'delete',
                'description' => $user->name . ' eliminó el contenedor ' . $container->name,
            ]);

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
