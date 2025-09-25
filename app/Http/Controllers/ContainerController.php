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
use App\Services\OrganizationDriveHelper;
use App\Traits\GoogleDriveHelpers;

class ContainerController extends Controller
{
    use GoogleDriveHelpers;

    protected $googleDriveService;
    protected OrganizationDriveHelper $organizationDriveHelper;

    public function __construct(GoogleDriveService $googleDriveService, OrganizationDriveHelper $organizationDriveHelper)
    {
        $this->googleDriveService = $googleDriveService;
        $this->organizationDriveHelper = $organizationDriveHelper;
    }

    private function userHasContainerPrivileges($user, $groupId = null, $containerUsername = null): bool
    {
        Log::info("Checking container privileges for user {$user->username} (ID: {$user->id}), Group ID: {$groupId}, Container Owner: {$containerUsername}");

        // Verificar si es el creador del contenedor
        if ($containerUsername && $containerUsername === $user->username) {
            Log::info("User is container owner - access granted");
            return true;
        }

        if ($groupId) {
            $role = DB::table('group_user')
                ->where('user_id', $user->id)
                ->where('id_grupo', $groupId)
                ->value('rol');

            Log::info("User role in group {$groupId}: " . ($role ?? 'null'));

            // Admite roles nuevos y legados
            $hasPrivileges = in_array($role, ['colaborador', 'administrador', 'full_meeting_access'], true);
            Log::info("User has group privileges: " . ($hasPrivileges ? 'true' : 'false'));
            return $hasPrivileges;
        }

        // Acciones generales: permitir si tiene algún grupo con rol distinto a invitado
        $hasGeneralPrivileges = DB::table('group_user')
            ->where('user_id', $user->id)
            ->whereIn('rol', ['colaborador', 'administrador', 'full_meeting_access'])
            ->exists();

        Log::info("User has general privileges: " . ($hasGeneralPrivileges ? 'true' : 'false'));
        return $hasGeneralPrivileges;
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

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'Usuario no autenticado'
                ], 401);
            }

            Log::info("Getting containers for user: {$user->username}");

            // Inicializar token de Google Drive de forma segura
            try {
                $this->setGoogleDriveToken($user);
                Log::info("Google Drive token set successfully for user: {$user->username}");
            } catch (\Exception $e) {
                Log::warning("No Google Drive token available for user {$user->username}: {$e->getMessage()}");
            }

            $containers = MeetingContentContainer::with('group')
                ->where('is_active', true)
                ->where(function ($q) use ($user) {
                    $q->where('username', $user->username)
                      ->orWhereIn('group_id', function ($sub) use ($user) {
                          $sub->select('id_grupo')
                              ->from('group_user')
                              ->where('user_id', $user->id);
                      });
                })
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
                        'group_name' => optional($container->group)->nombre_grupo,
                        'drive_folder_id' => $container->drive_folder_id,
                        'metadata' => $container->metadata,
                    ];
                });

            Log::info("Retrieved {$containers->count()} containers for user: {$user->username}");

            return response()->json([
                'success' => true,
                'containers' => $containers
            ]);

        } catch (\Exception $e) {
            Log::error("Error in getContainers: {$e->getMessage()}");
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
            Log::info("Store container request - User: {$user->username} (ID: {$user->id}), Group ID: " . ($validated['group_id'] ?? 'null'));

            // Para crear contenedores, permitir si:
            // 1. No tiene group_id (contenedor personal) - cualquier usuario puede crear
            // 2. Tiene group_id y el usuario tiene permisos en ese grupo
            $groupId = $validated['group_id'] ?? null;

            if ($groupId && !$this->userHasContainerPrivileges($user, $groupId)) {
                Log::warning("User {$user->username} denied container creation in group {$groupId} - insufficient privileges");
                return response()->json(['success' => false, 'message' => 'No tienes permisos para crear contenedores en este grupo'], 403);
            }

            Log::info("User {$user->username} authorized to create container");

            $driveFolderId = null;
            $metadata = null;
            $group = null;
            $organizationId = null;

            try {
                [$container, $group, $driveFolderId, $metadata, $organizationId] = DB::transaction(function () use ($validated, $user, $groupId) {
                    $container = MeetingContentContainer::create([
                        'name' => $validated['name'],
                        'description' => $validated['description'] ?? null,
                        'username' => $user->username,
                        'group_id' => $groupId,
                        'is_active' => true,
                    ]);

                    $group = null;
                    $driveFolderId = null;
                    $metadata = null;
                    $organizationId = null;

                    if ($container->group_id) {
                        $group = Group::find($container->group_id);
                        if (!$group) {
                            throw new \RuntimeException('El grupo indicado no existe.');
                        }

                        $organizationId = $group->id_organizacion;
                        $folderData = $this->organizationDriveHelper->ensureContainerFolder($group, $container);
                        $driveFolderId = $folderData['id'] ?? null;
                        $metadata = $folderData['metadata'] ?? null;

                        if ($driveFolderId) {
                            $container->forceFill([
                                'drive_folder_id' => $driveFolderId,
                                'metadata' => $metadata,
                            ])->save();
                        }
                    }

                    return [$container, $group, $driveFolderId, $metadata, $organizationId];
                });
            } catch (\Throwable $e) {
                Log::error('Error al crear la carpeta de Drive para el contenedor', [
                    'user_id' => $user->id,
                    'group_id' => $groupId,
                    'error' => $e->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'No se pudo crear la carpeta del contenedor en Drive. Inténtalo nuevamente más tarde.',
                ], 502);
            }

            OrganizationActivity::create([
                'organization_id' => $organizationId,
                'group_id' => $container->group_id,
                'container_id' => $container->id,
                'user_id' => $user->id,
                'action' => 'create',
                'description' => $user->full_name . ' creó el contenedor ' . $container->name,
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
                    'drive_folder_id' => $driveFolderId,
                    'metadata' => $metadata,
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
                'description' => $user->full_name . ' actualizó el contenedor ' . $container->name,
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
                    'drive_folder_id' => $container->drive_folder_id,
                    'metadata' => $container->metadata,
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
        Log::info("AddMeeting request - User: {$user->username} (ID: {$user->id}), Container ID: {$id}");

        $data = $request->validate([
            'meeting_id' => ['required', 'exists:transcriptions_laravel,id'],
        ]);

        // Buscar el contenedor sin restricción de username, verificaremos permisos después
        $container = MeetingContentContainer::findOrFail($id);
        Log::info("Container found: {$container->name}, Owner: {$container->username}, Group ID: {$container->group_id}");

        // Verificar permisos del usuario para este contenedor
        if (!$this->userHasContainerPrivileges($user, $container->group_id, $container->username)) {
            Log::warning("User {$user->username} denied access to container {$id} - insufficient privileges");
            return response()->json(['error' => 'No tienes permisos para añadir reuniones a este contenedor'], 403);
        }

        Log::info("User {$user->username} has privileges for container {$id}");

        // Buscar la reunión - por ahora solo permitir reuniones del usuario
        // TODO: En el futuro se podría implementar permisos de grupo para reuniones
        $meeting = TranscriptionLaravel::where('id', $data['meeting_id'])
            ->where('username', $user->username)
            ->firstOrFail();

        MeetingContentRelation::firstOrCreate([
            'container_id' => $container->id,
            'meeting_id' => $meeting->id,
        ]);

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
            'action' => 'add_meeting_to_container',
            'description' => $user->full_name . ' agregó la reunión ' . $meeting->meeting_name . ' al contenedor ' . $container->name,
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

            // Buscar el contenedor sin restricción de username, verificaremos permisos después
            $container = MeetingContentContainer::findOrFail($containerId);

            // Verificar permisos del usuario para este contenedor
            if (!$this->userHasContainerPrivileges($user, $container->group_id, $container->username)) {
                return response()->json(['error' => 'No tienes permisos para modificar este contenedor'], 403);
            }

            $meeting = TranscriptionLaravel::findOrFail($meetingId);

            MeetingContentRelation::where('container_id', $container->id)
                ->where('meeting_id', $meeting->id)
                ->delete();

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
                'action' => 'remove_meeting_from_container',
                'description' => $user->full_name . ' eliminó la reunión ' . $meeting->meeting_name . ' del contenedor ' . $container->name,
            ]);

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
        try {
            $user = Auth::user();
            Log::info("Getting meetings for container ID: {$id}, user: {$user->username}");

            // Inicializar token de Google Drive de forma segura
            try {
                $this->setGoogleDriveToken($user);
                Log::info("Google Drive token set successfully for user: {$user->username}");
            } catch (\Exception $e) {
                Log::warning("No Google Drive token available for user {$user->username}: {$e->getMessage()}");
            }

            $container = MeetingContentContainer::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $meetings = $container->meetings()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    $audioFolder = null;
                    $transcriptFolder = null;

                    // Obtener nombre de carpeta de audio de forma segura
                    if ($meeting->audio_drive_id) {
                        try {
                            $audioFolder = $this->getFolderName($meeting->audio_drive_id);
                        } catch (\Exception $e) {
                            Log::warning("Error getting audio folder name for meeting {$meeting->id}: {$e->getMessage()}");
                            $audioFolder = 'Error al cargar carpeta';
                        }
                    }

                    // Obtener nombre de carpeta de transcripción de forma segura
                    if ($meeting->transcript_drive_id) {
                        try {
                            $transcriptFolder = $this->getFolderName($meeting->transcript_drive_id);
                        } catch (\Exception $e) {
                            Log::warning("Error getting transcript folder name for meeting {$meeting->id}: {$e->getMessage()}");
                            $transcriptFolder = 'Error al cargar carpeta';
                        }
                    }

                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_folder' => $audioFolder,
                        'transcript_folder' => $transcriptFolder,
                    ];
                });

            Log::info("Retrieved {$meetings->count()} meetings for container {$id}");

            return response()->json([
                'success' => true,
                'meetings' => $meetings,
            ]);

        } catch (\Exception $e) {
            Log::error("Error in getMeetings: {$e->getMessage()}");
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar las reuniones del contenedor: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las reuniones de un contenedor específico
     */
    public function getContainerMeetings($id): JsonResponse
    {
        try {
            $user = Auth::user();
            Log::info("Getting container meetings for container ID: {$id}, user: {$user->username}");

            // Inicializar token de Google Drive de forma segura
            try {
                $this->setGoogleDriveToken($user);
                Log::info("Google Drive token set successfully for user: {$user->username}");
            } catch (\Exception $e) {
                Log::warning("No Google Drive token available for user {$user->username}: {$e->getMessage()}");
            }

            // Acceso de lectura a reuniones del contenedor:
            // - Creador del contenedor (username)
            // - Miembro del grupo (cualquier rol, incluso invitado)
            // - Dueño de la organización
            $container = MeetingContentContainer::with(['group', 'group.organization'])
                ->where('id', $id)
                ->where('is_active', true)
                ->firstOrFail();

            $isCreator = $container->username === $user->username;
            $isMember = $container->group_id
                ? DB::table('group_user')
                    ->where('id_grupo', $container->group_id)
                    ->where('user_id', $user->id)
                    ->exists()
                : false;
            $isOrgOwner = $container->group && $container->group->organization
                ? $container->group->organization->admin_id === $user->id
                : false;

            if (!($isCreator || $isMember || $isOrgOwner)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para ver las reuniones de este contenedor'
                ], 403);
            }

            Log::info("Container found: " . json_encode($container->toArray()));

            // Obtener las reuniones del contenedor usando la relación del modelo
            $meetings = $container->meetings()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    $audioFolder = null;
                    $transcriptFolder = null;

                    // Obtener nombre de carpeta de audio de forma segura
                    if ($meeting->audio_drive_id) {
                        try {
                            $audioFolder = $this->getFolderName($meeting->audio_drive_id);
                        } catch (\Exception $e) {
                            Log::warning("Error getting audio folder name for meeting {$meeting->id}: {$e->getMessage()}");
                            $audioFolder = 'Error al cargar carpeta';
                        }
                    }

                    // Obtener nombre de carpeta de transcripción de forma segura
                    if ($meeting->transcript_drive_id) {
                        try {
                            $transcriptFolder = $this->getFolderName($meeting->transcript_drive_id);
                        } catch (\Exception $e) {
                            Log::warning("Error getting transcript folder name for meeting {$meeting->id}: {$e->getMessage()}");
                            $transcriptFolder = 'Error al cargar carpeta';
                        }
                    }

                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_folder' => $audioFolder,
                        'transcript_folder' => $transcriptFolder,
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

            Log::info("Response data prepared for container {$id}");

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
            Log::info("Delete container request - User: {$user->username} (ID: {$user->id}), Container ID: {$id}");

            // Buscar el contenedor sin restricción de username, verificaremos permisos después
            $container = MeetingContentContainer::findOrFail($id);
            Log::info("Container found: {$container->name}, Owner: {$container->username}, Group ID: {$container->group_id}");

            // Verificar permisos del usuario para este contenedor
            if (!$this->userHasContainerPrivileges($user, $container->group_id, $container->username)) {
                Log::warning("User {$user->username} denied delete access to container {$id} - insufficient privileges");
                return response()->json(['success' => false, 'message' => 'No tienes permisos para eliminar este contenedor'], 403);
            }

            Log::info("User {$user->username} authorized to delete container {$id}");

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
                'description' => $user->full_name . ' eliminó el contenedor ' . $container->name,
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
