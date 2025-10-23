<?php

namespace App\Http\Controllers;

use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\TranscriptionLaravel;
use App\Models\OrganizationActivity;
use App\Models\Group;
use App\Models\OrganizationContainerFolder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use App\Traits\MeetingContentParsing;

class ContainerController extends Controller
{
    use GoogleDriveHelpers;
    use MeetingContentParsing;

    // Se elimina decryptJuFile duplicado; se usa el del trait MeetingContentParsing


    /**
     * Acepta un ID directo o una URL de Google Drive y devuelve el fileId.
     */
    private function normalizeDriveId(string $maybeId): string
    {
        // Si parece URL con /file/d/{id}/
        if (preg_match('#/file/d/([^/]+)/#', $maybeId, $m)) {
            return $m[1];
        }
        // URL tipo uc?export=download&id={id}
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $maybeId, $m)) {
            return $m[1];
        }
        // Ya es un ID
        return $maybeId;
    }
    use GoogleDriveHelpers;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
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
                        'group_name' => $container->group->nombre_grupo ?? null,
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

            if (!$groupId) {
                $planCode = strtolower((string) ($user->plan_code ?? 'free'));
                $role = strtolower((string) ($user->roles ?? 'free'));
                $isBasicPlan = $role === 'basic' || in_array($planCode, ['basic', 'basico'], true) || str_contains($planCode, 'basic');
                $isBusinessPlan = $role === 'negocios'
                    || in_array($planCode, ['negocios', 'business', 'buisness'], true)
                    || str_contains($planCode, 'negocio')
                    || str_contains($role, 'business');
                $isEnterprisePlan = $role === 'enterprise'
                    || in_array($planCode, ['enterprise', 'empresas', 'empresa', 'enterprice'], true)
                    || str_contains($planCode, 'enterprise')
                    || str_contains($planCode, 'empresa')
                    || str_contains($role, 'enterprise')
                    || str_contains($role, 'empresa');

                $maxPersonalContainers = null;
                $planLabel = null;

                if ($isBasicPlan) {
                    $maxPersonalContainers = 3;
                    $planLabel = 'Plan Basic';
                } elseif ($isBusinessPlan) {
                    $maxPersonalContainers = 10;
                    $planLabel = 'Plan Business';
                } elseif ($isEnterprisePlan) {
                    $maxPersonalContainers = 10;
                    $planLabel = 'Plan Enterprise';
                }

                if ($maxPersonalContainers !== null) {
                    $personalContainers = MeetingContentContainer::where('username', $user->username)
                        ->whereNull('group_id')
                        ->where('is_active', true)
                        ->count();

                    if ($personalContainers >= $maxPersonalContainers) {
                        return response()->json([
                            'success' => false,
                            'message' => sprintf('%s permite crear hasta %d contenedores personales.', $planLabel, $maxPersonalContainers),
                        ], 403);
                    }
                }
            }

            $container = MeetingContentContainer::create([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'username' => $user->username,
                'group_id' => $groupId,
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
                'group_id' => $group ? $group->id : null,
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

        // Verificar límites de reuniones por contenedor para Plan Basic
        $planCode = strtolower((string) ($user->plan_code ?? 'free'));
        $role = strtolower((string) ($user->roles ?? 'free'));
        $isBasicPlan = $role === 'basic' || in_array($planCode, ['basic', 'basico'], true) || str_contains($planCode, 'basic');

        $isBusinessPlan = $role === 'negocios'
            || in_array($planCode, ['negocios', 'business', 'buisness'], true)
            || str_contains($planCode, 'negocio')
            || str_contains($role, 'business');
        $isEnterprisePlan = $role === 'enterprise'
            || in_array($planCode, ['enterprise', 'empresas', 'empresa', 'enterprice'], true)
            || str_contains($planCode, 'enterprise')
            || str_contains($planCode, 'empresa')
            || str_contains($role, 'enterprise')
            || str_contains($role, 'empresa');

        if (!$container->group_id) { // Solo aplicar límite a contenedores personales
            $maxMeetingsPerContainer = null;
            $planLabel = null;

            if ($isBasicPlan) {
                $maxMeetingsPerContainer = 10;
                $planLabel = 'Plan Basic';
            } elseif ($isBusinessPlan) {
                $maxMeetingsPerContainer = 10;
                $planLabel = 'Plan Business';
            } elseif ($isEnterprisePlan) {
                $maxMeetingsPerContainer = 15;
                $planLabel = 'Plan Enterprise';
            }

            if ($maxMeetingsPerContainer !== null) {
                $currentMeetingsCount = MeetingContentRelation::where('container_id', $container->id)->count();

                if ($currentMeetingsCount >= $maxMeetingsPerContainer) {
                    Log::info("{$planLabel} meeting limit reached for container {$id} - current count: {$currentMeetingsCount}");
                    return response()->json([
                        'success' => false,
                        'message' => sprintf('%s permite máximo %d reuniones por contenedor.', $planLabel, $maxMeetingsPerContainer)
                    ], 403);
                }
            }
        } else {
            if ($isEnterprisePlan) {
                $currentMeetingsCount = MeetingContentRelation::where('container_id', $container->id)->count();

                if ($currentMeetingsCount >= 10) {
                    Log::info("Plan Enterprise organization meeting limit reached for container {$id} - current count: {$currentMeetingsCount}");
                    return response()->json([
                        'success' => false,
                        'message' => 'El Plan Enterprise permite máximo 10 reuniones por contenedor organizacional.'
                    ], 403);
                }
            }
        }

        // Verificar si la reunión ya está en el contenedor
        $existingRelation = MeetingContentRelation::where([
            'container_id' => $container->id,
            'meeting_id' => $meeting->id,
        ])->first();

        if ($existingRelation) {
            return response()->json([
                'success' => false,
                'message' => 'Esta reunión ya está en el contenedor.'
            ], 400);
        }

        MeetingContentRelation::create([
            'container_id' => $container->id,
            'meeting_id' => $meeting->id,
        ]);

        // Notificaciones
        $this->notifyContainerMeetingAction(
            action: 'added',
            actor: $user,
            container: $container,
            meeting: $meeting,
            group: isset($group) ? $group : ($container->group_id ? Group::find($container->group_id) : null)
        );

        $organizationId = null;
        if ($container->group_id) {
            $group = Group::find($container->group_id);
            $organizationId = $group ? $group->id_organizacion : null;
        }

        OrganizationActivity::create([
            'organization_id' => $organizationId,
            'group_id' => $group ? $group->id : null,
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
                'group_id' => $group ? $group->id : null,
                'container_id' => $container->id,
                'user_id' => $user->id,
                'action' => 'remove_meeting_from_container',
                'description' => $user->full_name . ' eliminó la reunión ' . $meeting->meeting_name . ' del contenedor ' . $container->name,
            ]);

            // Notificaciones
            $this->notifyContainerMeetingAction(
                action: 'removed',
                actor: $user,
                container: $container,
                meeting: $meeting,
                group: $group ?? null
            );

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
                ->map(function ($meeting) use ($user) {
                    $audioFolder = null;
                    $transcriptFolder = null;
                    $segments = [];
                    $summary = null;
                    $keyPoints = [];
                    $transcription = '';
                    $speakers = [];
                    $needsEncryption = false;

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

                        // --- INICIO LÓGICA ROBUSTA DESCARGA .JU CON LOGS ---
                        $transcriptContent = null;
                        $normalizedJuId = $this->normalizeDriveId($meeting->transcript_drive_id);
                        Log::info('getContainerMeetings(): Intentando descargar .ju', ['meeting_id' => $meeting->id, 'file_id' => $normalizedJuId]);
                        // 1) Service Account impersonando al propietario (si existe)
                        try {
                            if (method_exists($this, 'getMeetingOwnerEmail')) {
                                $ownerEmail = $this->getMeetingOwnerEmail($meeting);
                                Log::info('getContainerMeetings(): Owner email detectado', ['meeting_id' => $meeting->id, 'owner_email' => $ownerEmail]);
                            } else {
                                $ownerEmail = null;
                                Log::info('getContainerMeetings(): No se encontró método getMeetingOwnerEmail', ['meeting_id' => $meeting->id]);
                            }
                            /** @var \App\Services\GoogleServiceAccount $sa */
                            $sa = app(\App\Services\GoogleServiceAccount::class);
                            if ($ownerEmail) { $sa->impersonate($ownerEmail); }
                            $transcriptContent = $sa->downloadFile($normalizedJuId);
                            Log::info('getContainerMeetings(): .ju descargado con SA impersonate', ['meeting_id' => $meeting->id]);
                        } catch (\Throwable $e) {
                            Log::warning('getContainerMeetings(): fallo SA con impersonate al descargar .ju, intentando sin impersonate', [
                                'meeting_id' => $meeting->id,
                                'file_id' => $normalizedJuId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        // 2) SA sin impersonate
                        if ($transcriptContent === null) {
                            try {
                                $sa = app(\App\Services\GoogleServiceAccount::class);
                                $transcriptContent = $sa->downloadFile($normalizedJuId);
                                Log::info('getContainerMeetings(): .ju descargado con SA sin impersonate', ['meeting_id' => $meeting->id]);
                            } catch (\Throwable $e2) {
                                Log::warning('getContainerMeetings(): fallo SA sin impersonate al descargar .ju, intentando token del usuario', [
                                    'meeting_id' => $meeting->id,
                                    'file_id' => $normalizedJuId,
                                    'error' => $e2->getMessage(),
                                ]);
                            }
                        }
                        // 3) Token del usuario (último intento)
                        if ($transcriptContent === null) {
                            try {
                                $this->setGoogleDriveToken($user);
                                $transcriptContent = $this->downloadFromDrive($normalizedJuId);
                                Log::info('getContainerMeetings(): .ju descargado con token del usuario', ['meeting_id' => $meeting->id]);
                            } catch (\Throwable $e3) {
                                Log::error('getContainerMeetings(): no fue posible descargar el .ju con ningún método', [
                                    'meeting_id' => $meeting->id,
                                    'file_id' => $normalizedJuId,
                                    'error' => $e3->getMessage(),
                                ]);
                            }
                        }

                        // Si no se pudo obtener contenido, dejar vacío
                        if ($transcriptContent) {
                            Log::info('getContainerMeetings(): .ju descargado, procesando contenido', ['meeting_id' => $meeting->id, 'content_length' => strlen($transcriptContent)]);
                            $transcriptResult = $this->decryptJuFile($transcriptContent);
                        } else {
                            Log::warning('getContainerMeetings(): No se pudo obtener contenido del .ju', ['meeting_id' => $meeting->id]);
                            $transcriptResult = ['data' => [], 'needs_encryption' => false];
                        }
                        $transcriptData = $transcriptResult['data'];
                        $needsEncryption = $transcriptResult['needs_encryption'];

                        $processedData = $this->processTranscriptData($transcriptData);
                        Log::info('getContainerMeetings(): Datos procesados del .ju', [
                            'meeting_id' => $meeting->id,
                            'segment_count' => is_array($processedData['segments'] ?? null) ? count($processedData['segments']) : 0,
                            'summary' => $processedData['summary'] ?? null,
                            'key_points_count' => is_array($processedData['key_points'] ?? null) ? count($processedData['key_points']) : 0
                        ]);
                        $segments = $processedData['segments'] ?? [];
                        $summary = $processedData['summary'] ?? null;
                        $keyPoints = $processedData['key_points'] ?? [];
                        $transcription = $processedData['transcription'] ?? '';
                        $speakers = $processedData['speakers'] ?? [];
                        // --- FIN LÓGICA ROBUSTA DESCARGA .JU CON LOGS ---
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
                        'segments' => $segments,
                        'summary' => $summary,
                        'key_points' => $keyPoints,
                        'transcription' => $transcription,
                        'speakers' => $speakers,
                        'needs_encryption' => $needsEncryption,
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

            // Antes de desactivar el contenedor, mover todas las reuniones de vuelta a la lista general
            $meetingRelations = MeetingContentRelation::where('container_id', $container->id)->get();
            $movedMeetings = $meetingRelations->count();

            // Loguear las reuniones que se van a mover
            foreach ($meetingRelations as $relation) {
                Log::info("Meeting moved back to general list", [
                    'meeting_id' => $relation->meeting_id,
                    'container_id' => $container->id,
                    'container_name' => $container->name
                ]);
            }

            // Eliminar todas las relaciones contenedor-reunión de una vez
            MeetingContentRelation::where('container_id', $container->id)->delete();

            Log::info("Container deletion: moved {$movedMeetings} meetings back to general list", [
                'container_id' => $container->id,
                'container_name' => $container->name,
                'moved_meetings' => $movedMeetings
            ]);

            // Eliminar la subcarpeta de documentos del contenedor en Google Drive
            $containerFolder = OrganizationContainerFolder::where('container_id', $container->id)->first();
            if ($containerFolder) {
                $this->setGoogleDriveToken($user);

                // Usar el método robusta para eliminar la carpeta
                $driveDeleteSuccess = $this->googleDriveService->deleteFolderResilient(
                    $containerFolder->google_id,
                    $user->email
                );

                if ($driveDeleteSuccess) {
                    Log::info("Container folder deleted from Google Drive", [
                        'container_id' => $container->id,
                        'container_name' => $container->name,
                        'folder_id' => $containerFolder->google_id,
                        'folder_name' => $containerFolder->name
                    ]);
                } else {
                    Log::error("Failed to delete container folder from Google Drive with all strategies", [
                        'container_id' => $container->id,
                        'container_name' => $container->name,
                        'folder_id' => $containerFolder->google_id ?? 'unknown',
                        'folder_name' => $containerFolder->name ?? 'unknown',
                        'recommendation' => 'Manual deletion may be required in Google Drive'
                    ]);
                }

                // Eliminar el registro de la base de datos independientemente del resultado en Drive
                // Esto evita datos huérfanos en la BD
                $containerFolder->delete();

                Log::info("Container folder record deleted from database", [
                    'container_id' => $container->id,
                    'folder_id' => $containerFolder->google_id,
                    'drive_deletion_success' => $driveDeleteSuccess
                ]);
            }

            $container->update(['is_active' => false]);

            $organizationId = null;
            if ($container->group_id) {
                $group = Group::find($container->group_id);
                $organizationId = $group ? $group->id_organizacion : null;
            }

            OrganizationActivity::create([
                // Solo registrar group_id si el grupo realmente existe para evitar violaciones de FK
                'organization_id' => $organizationId,
                'group_id' => isset($group) && $group ? $group->id : null,
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

    // --------------------------------------------------
    // Helpers
    // --------------------------------------------------

    /**
     * Crea notificaciones para actor y miembros del grupo (si aplica) al añadir o remover una reunión.
     */
    protected function notifyContainerMeetingAction(string $action, $actor, $container, $meeting, $group = null): void
    {
        try {
            $verb = $action === 'added' ? 'Moviste' : 'Removiste';
            $type = $action === 'added' ? 'container_meeting_added' : 'container_meeting_removed';
            $title = $action === 'added' ? 'Reunión añadida al contenedor' : 'Reunión removida del contenedor';
            $message = $verb . ' la reunión "' . $meeting->meeting_name . '" ' . ($action === 'added' ? 'al' : 'del') . ' contenedor "' . $container->name . '"';

            // Notificación para el actor (historial propio)
            \App\Models\Notification::create([
                'user_id' => $actor->id,
                'from_user_id' => $actor->id,
                'type' => $type,
                'title' => $title,
                'message' => $message,
                'data' => [
                    'meeting_id' => $meeting->id,
                    'container_id' => $container->id,
                    'container_name' => $container->name,
                    'action' => $action,
                ],
                'read' => false,
            ]);

            // Notificar a miembros del grupo (excluyendo actor) si es contenedor de grupo
            if ($group) {
                $memberIds = DB::table('group_user')
                    ->where('id_grupo', $group->id)
                    ->pluck('user_id')
                    ->filter(fn($id) => $id != $actor->id)
                    ->values();
                foreach ($memberIds as $uid) {
                    \App\Models\Notification::create([
                        'user_id' => $uid,
                        'from_user_id' => $actor->id,
                        'type' => $type,
                        'title' => $title,
                        'message' => $actor->full_name . ' ' . strtolower($message),
                        'data' => [
                            'meeting_id' => $meeting->id,
                            'container_id' => $container->id,
                            'container_name' => $container->name,
                            'action' => $action,
                            'actor' => $actor->username,
                        ],
                        'read' => false,
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('notifyContainerMeetingAction failed', [
                'action' => $action,
                'container_id' => $container->id ?? null,
                'meeting_id' => $meeting->id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

}
