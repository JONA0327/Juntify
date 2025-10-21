<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Group;
use App\Models\User;
use App\Models\OrganizationActivity;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\OrganizationGroupFolder;
use App\Models\OrganizationContainerFolder;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationController extends Controller
{
    use GoogleDriveHelpers;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }
    public function driveSettings(Organization $organization)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Permitir solo al owner o administradores de algún grupo de la organización
        $isOwner = $organization->admin_id === $user->id;
        $hasAdminRole = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'administrador');
        })->exists();

        if (!($isOwner || $hasAdminRole)) {
            abort(403, 'No tienes permisos para configurar Drive de esta organización');
        }

        $organization->load(['folder', 'subfolders']);

        return view('organization.drive', [
            'organization' => $organization,
            'user' => $user,
        ]);
    }
    public function publicIndex()
    {
        $organizations = Organization::query()
            ->select('id', 'nombre_organizacion', 'descripcion', 'imagen', 'num_miembros')
            ->get()
            ->makeHidden(['created_at', 'updated_at', 'admin_id']);

        return response()->json($organizations);
    }

    public function publicShow($organization)
    {
        $organization = Organization::query()
            ->select('id', 'nombre_organizacion', 'descripcion', 'imagen', 'num_miembros')
            ->findOrFail($organization)
            ->makeHidden(['created_at', 'updated_at', 'admin_id']);

        return response()->json($organization);
    }

    public function index(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Obtener organizaciones del usuario: por pertenencia directa (pivot organization_user)
        // o por pertenencia a algún grupo de la organización.
        $organizations = Organization::query()
            ->whereHas('users', function ($q) use ($user) {
                $q->where('users.id', $user->id);
            })
            ->orWhereHas('groups', function ($query) use ($user) {
                $query->whereHas('users', function ($subQuery) use ($user) {
                    $subQuery->where('users.id', $user->id);
                });
            })
            ->orWhere('admin_id', $user->id)
            ->with([
                // Cargar todos los grupos de la organización y el rol del usuario en cada uno
                'groups' => function ($query) use ($user) {
                    $query->with([
                        'users' => function ($subQuery) use ($user) {
                            $subQuery->where('users.id', $user->id);
                        },
                        'code',
                    ]);
                },
                // Cargar relación users filtrada al usuario actual para leer el rol del pivot
                'users' => function ($q) use ($user) {
                    $q->where('users.id', $user->id);
                },
            ])
            ->get();

        // Marcar si el usuario es propietario de la organización, obtener su rol más alto
        // y anotar el rol del usuario dentro de cada grupo
        $organizations->each(function ($organization) use ($user) {
            $organization->setAttribute('is_owner', $organization->admin_id === $user->id);

            // Rol desde el pivot organization_user (si existe)
            $orgUser = $organization->users->firstWhere('id', $user->id);
            $orgPivotRole = $orgUser ? $orgUser->pivot->rol : null;

            // Roles desde los grupos en los que participa el usuario dentro de la organización
            $userRoles = $organization->groups->flatMap->users
                ->where('id', $user->id)
                ->pluck('pivot.rol')
                ->unique();

            // Ranking de roles
            $rank = ['invitado' => 0, 'colaborador' => 1, 'administrador' => 2];
            $candidates = [];

            if ($orgPivotRole && isset($rank[$orgPivotRole])) {
                $candidates[] = $orgPivotRole;
            }

            if ($userRoles->contains('administrador')) {
                $candidates[] = 'administrador';
            } elseif ($userRoles->contains('colaborador')) {
                $candidates[] = 'colaborador';
            }

            if ($organization->getAttribute('is_owner')) {
                $candidates[] = 'administrador';
            }

            // Elegir el más alto; por defecto 'invitado'
            $finalRole = 'invitado';
            foreach ($candidates as $candidate) {
                if ($rank[$candidate] > $rank[$finalRole]) {
                    $finalRole = $candidate;
                }
            }

            $organization->setAttribute('user_role', $finalRole);

            // Rol del usuario en cada grupo
            $organization->groups->each(function ($group) {
                $membership = $group->users->first();
                $group->setAttribute('user_role', $membership ? $membership->pivot->rol : null);
            });
        });

        // Verificar si el usuario es solo invitado en todas las organizaciones
        $isOnlyGuest = $organizations->every(function($org) {
            return $org->user_role === 'invitado';
        });

        // Si es una petición AJAX/API, devolver JSON
        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'organizations' => $organizations,
                'user' => $user,
                'isOnlyGuest' => $isOnlyGuest,
            ]);
        }

        return view('organization.index', [
            'organizations' => $organizations,
            'user' => $user,
            'isOnlyGuest' => $isOnlyGuest,
        ]);
    }

    public function store(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Log inicial de intento de creación
        Log::info('[OrganizationController@store] Intento de crear organización', [
            'user_id' => $user->id,
            'user_role_string' => $user->roles,
            'current_organization_id' => $user->current_organization_id,
        ]);

        $role = strtolower((string) ($user->roles ?? ''));
        $planCode = strtolower((string) ($user->plan_code ?? ''));
        $blockedRoles = ['free', 'freemium', 'basic', 'basico', 'negocios', 'business', 'buisness'];
        $blockedPlanCodes = ['basic', 'basico', 'negocios', 'business', 'buisness'];

        if (in_array($role, $blockedRoles, true) || in_array($planCode, $blockedPlanCodes, true)) {
            Log::warning('[OrganizationController@store] Bloqueado por plan', [
                'user_id' => $user->id,
                'role' => $user->roles,
                'plan_code' => $user->plan_code,
            ]);
            return response()->json(['message' => 'Tu plan actual no permite crear organizaciones'], 403);
        }

        // Verificar si el usuario ya pertenece (pivot) a alguna organización
        $hasOrganization = Organization::whereHas('users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->exists();

        if ($hasOrganization) {
            Log::info('[OrganizationController@store] Usuario ya pertenece a una organización (pivot encontrado)', ['user_id' => $user->id]);
            return response()->json(['message' => 'Ya perteneces a una organización'], 403);
        }

        // Edge case: pivot fue borrado pero current_organization_id sigue apuntando a una organización inexistente
        if ($user->current_organization_id) {
            $orgExists = Organization::where('id', $user->current_organization_id)->exists();
            if (!$orgExists) {
                Log::warning('[OrganizationController@store] current_organization_id huérfano detectado, reseteando', [
                    'user_id' => $user->id,
                    'stale_org_id' => $user->current_organization_id
                ]);
                $user->current_organization_id = null;
                $user->save();
            } else {
                Log::info('[OrganizationController@store] Usuario mantiene current_organization_id válido', [
                    'user_id' => $user->id,
                    'org_id' => $user->current_organization_id
                ]);
                return response()->json(['message' => 'Ya perteneces a una organización (current_organization_id asignado)'], 403);
            }
        }

        $validated = $request->validate([
            'nombre_organizacion' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|string',
        ]);

        $organization = Organization::create($validated + [
            'num_miembros' => 0,
            'admin_id' => $user->id
        ]);

        Log::info('[OrganizationController@store] Organización creada', [
            'org_id' => $organization->id,
            'user_id' => $user->id
        ]);

        $organization->users()->attach($user->id, ['rol' => 'administrador']);
        $organization->refreshMemberCount();

        // Actualizar current_organization_id del usuario
        User::where('id', $user->id)->update(['current_organization_id' => $organization->id]);
        Log::info('[OrganizationController@store] current_organization_id actualizado', [
            'user_id' => $user->id,
            'org_id' => $organization->id
        ]);

        return response()->json($organization, 201);
    }

    public function join(Request $request, $token)
    {
        $organization = Organization::where('id', $token)->firstOrFail();
        $user = $request->user();

        // Verificar si el usuario ya pertenece a alguna organización
        $alreadyMember = Organization::whereHas('users', function($query) use ($user) {
            $query->where('users.id', $user->id);
        })->exists();

        if ($alreadyMember) {
            return response()->json([
                'message' => 'El usuario ya pertenece a una organización y no puede unirse a otra'
            ], 409);
        }

        // Buscar el grupo principal de la organización
        $mainGroup = $organization->groups()->first();
        if (!$mainGroup) {
            return response()->json([
                'message' => 'No existe un grupo al que unirse'
            ], 404);
        }

    // Agregar usuario al grupo principal con rol invitado por defecto
    $mainGroup->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
    $mainGroup->update(['miembros' => $mainGroup->users()->count()]);
    // Asegurar registro en la organización y refrescar contador
    $organization->users()->syncWithoutDetaching([$user->id => ['rol' => 'invitado']]);
    $organization->refreshMemberCount();

        // Actualizar current_organization_id del usuario
        User::where('id', $user->id)->update(['current_organization_id' => $organization->id]);

        OrganizationActivity::create([
            'organization_id' => $organization->id,
            'group_id' => $mainGroup->id,
            'user_id' => $user->id,
            'target_user_id' => $user->id,
            'action' => 'join_org',
            'description' => $user->full_name . ' se unió a la organización',
        ]);

        return response()->json(['joined' => true]);
    }

    public function leave(Request $request)
    {
        $user = auth()->user();
        if (!$user) {
            abort(403);
        }

        // Permitir abandonar una organización específica si se envía organization_id
        $targetOrgId = $request->input('organization_id');

        $query = Organization::whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        });
        if ($targetOrgId) {
            $query->where('id', $targetOrgId);
        }
        $organizations = $query->get();

        if ($organizations->isEmpty()) {
            return response()->json([
                'message' => 'No perteneces a la organización especificada'
            ], 404);
        }

        $blocked = [];
        Log::info('Org leave attempt', [
            'user_id' => $user->id,
            'target_org_id' => $targetOrgId,
            'org_count' => $organizations->count()
        ]);
        foreach ($organizations as $organization) {
            if ($organization->admin_id === $user->id) {
                // No permitir salir si es administrador de esa organización
                $blocked[] = $organization->id;
                continue;
            }

            // Detach de grupos
            $organization->loadMissing('groups');
            foreach ($organization->groups as $group) {
                $group->users()->detach($user->id);
                $group->update(['miembros' => $group->users()->count()]);
            }

            // Detach de la organización
            $organization->users()->detach($user->id);
            $organization->refreshMemberCount();
        }

        // Recalcular current_organization_id: asignar otra si existe, si no nullear
        $remainingOrg = Organization::whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->first();

        // Si todas las organizaciones seleccionadas quedaron bloqueadas (es admin) y no se logró salir de ninguna
        if ($blocked && count($blocked) === $organizations->count()) {
            return response()->json([
                'left' => false,
                'blocked_admin_of' => $blocked,
                'message' => 'No puedes salir porque administras la organización'
            ], 403);
        }

        if ($remainingOrg) {
            User::where('id', $user->id)->update(['current_organization_id' => $remainingOrg->id]);
        } else {
            User::where('id', $user->id)->update(['current_organization_id' => null]);
        }

        return response()->json([
            'left' => true,
            'blocked_admin_of' => $blocked,
            'current_organization_id' => $remainingOrg?->id,
            'remaining_org' => $remainingOrg?->id,
        ]);
    }

    public function update(Request $request, Organization $organization)
    {
        $user = auth()->user();
        if (!$user) {
            Log::error('Organization update: Usuario no autenticado');
            abort(403, 'Usuario no autenticado');
        }

        Log::info('Organization update attempt', [
            'user_id' => $user->id,
            'org_id' => $organization->id,
            'org_admin_id' => $organization->admin_id,
            'user_roles' => $user->roles
        ]);

        // Verificar si el usuario es el administrador de la organización
        $isOwner = $organization->admin_id === $user->id;

        // También verificar si tiene rol de administrador en algún grupo de la organización
        $hasAdminRole = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'administrador');
        })->exists();

        Log::info('Permission check', [
            'isOwner' => $isOwner,
            'hasAdminRole' => $hasAdminRole
        ]);

        if (!$isOwner && !$hasAdminRole) {
            Log::error('Organization update: Permisos insuficientes', [
                'user_id' => $user->id,
                'org_id' => $organization->id,
                'isOwner' => $isOwner,
                'hasAdminRole' => $hasAdminRole
            ]);
            abort(403, 'No tienes permisos para editar esta organización');
        }

        $validated = $request->validate([
            'nombre_organizacion' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'imagen' => 'nullable|string',
        ]);

        $organization->update($validated);

        Log::info('Organization updated successfully', ['org_id' => $organization->id]);

        // Cargar la organización con sus relaciones para mantener la integridad de los datos
        $organizationWithRelations = $organization->fresh()->load([
            'groups' => function ($query) {
                $query->withCount('users');
            }
        ]);

        Log::info('Organization with relations loaded', [
            'org_id' => $organizationWithRelations->id,
            'groups_count' => $organizationWithRelations->groups->count(),
            'groups' => $organizationWithRelations->groups->pluck('id', 'nombre_grupo')
        ]);

        return response()->json($organizationWithRelations);
    }

    public function show(Organization $organization)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $organization->load(['groups', 'users']);

        return response()->json($organization);
    }

    public function destroy(Organization $organization)
    {
        $user = auth()->user();
        if (!$user || in_array($user->roles, ['free', 'basic'])) {
            abort(403);
        }

        $isOwner = $organization->admin_id === $user->id;
        $hasAdminRole = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->where('group_user.rol', 'administrador');
        })->exists();

        if (!$isOwner && !$hasAdminRole) {
            abort(403);
        }

        $orgId = $organization->id;
        $orgName = $organization->nombre_organizacion;

        Log::info('Iniciando eliminación completa de organización', [
            'org_id' => $orgId,
            'org_name' => $orgName,
            'by_user' => $user->id
        ]);

        // 1. Eliminar todas las carpetas de Google Drive ANTES de eliminar registros BD
        $this->deleteOrganizationFoldersFromDrive($organization, $user);

        // 2. Detach usuarios de grupos y organizacion
        foreach ($organization->groups as $group) {
            $group->users()->detach();
        }
        $organization->users()->detach();

        // 3. Reset current_organization_id de usuarios que apuntan a esta organización
        \App\Models\User::where('current_organization_id', $organization->id)
            ->update(['current_organization_id' => null]);

        // 4. Eliminar la organización (las FK cascade eliminarán automáticamente los registros relacionados)
        $organization->delete();

        Log::info('Organización eliminada completamente', [
            'org_id' => $orgId,
            'org_name' => $orgName,
            'by_user' => $user->id
        ]);

        return response()->json(['deleted' => true, 'organization_id' => $orgId]);
    }

    /**
     * Elimina todas las carpetas de Google Drive asociadas a una organización
     *
     * @param Organization $organization
     * @param User $user
     * @return void
     */
    private function deleteOrganizationFoldersFromDrive(Organization $organization, User $user): void
    {
        $orgId = $organization->id;

        try {
            // Configurar token de Google Drive con lógica mejorada
            $tokenConfigured = false;

            // Intentar usar token de organización si está disponible
            if ($organization->googleToken && $organization->googleToken->isConnected()) {

                try {
                    $client = $this->googleDriveService->getClient();
                    $orgToken = $organization->googleToken;

                    // Si el token está expirado, intentar refrescarlo
                    if ($orgToken->isExpired() && $orgToken->refresh_token) {
                        Log::info('Organization token expired, attempting refresh', [
                            'org_id' => $orgId,
                            'expiry_date' => $orgToken->expiry_date
                        ]);

                        $client->refreshToken($orgToken->refresh_token);
                        $newToken = $client->getAccessToken();

                        // Actualizar token en base de datos
                        $orgToken->access_token = $newToken;
                        if (isset($newToken['created']) && isset($newToken['expires_in'])) {
                            $orgToken->expiry_date = now()->addSeconds($newToken['expires_in']);
                        }
                        $orgToken->save();

                        Log::info('Organization token refreshed successfully', [
                            'org_id' => $orgId,
                            'new_expiry' => $orgToken->expiry_date
                        ]);
                    } else {
                        // Usar token existente
                        $client->setAccessToken($orgToken->access_token);
                    }

                    $tokenConfigured = true;

                    Log::info('Using organization token for Drive operations', [
                        'org_id' => $orgId,
                        'token_expiry' => $orgToken->expiry_date
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Failed to configure organization token, falling back to user token', [
                        'org_id' => $orgId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Si no se pudo configurar token de organización, usar token del usuario
            if (!$tokenConfigured) {
                $this->setGoogleDriveToken($user);
                Log::info('Using user token for Drive operations', [
                    'org_id' => $orgId,
                    'user_email' => $user->email
                ]);
            }

            $deletedFolders = 0;
            $failedFolders = 0;

            // 1. Eliminar carpetas de contenedores
            $containerFolders = OrganizationContainerFolder::where('organization_id', $orgId)->get();
            foreach ($containerFolders as $containerFolder) {
                $success = $this->googleDriveService->deleteFolderResilient($containerFolder->google_id, $user->email);
                if ($success) {
                    $deletedFolders++;
                    Log::info('Container folder deleted during organization deletion', [
                        'org_id' => $orgId,
                        'container_id' => $containerFolder->container_id,
                        'folder_id' => $containerFolder->google_id,
                        'folder_name' => $containerFolder->name
                    ]);
                } else {
                    $failedFolders++;
                    Log::error('Failed to delete container folder during organization deletion', [
                        'org_id' => $orgId,
                        'container_id' => $containerFolder->container_id,
                        'folder_id' => $containerFolder->google_id,
                        'folder_name' => $containerFolder->name
                    ]);
                }
            }

            // 2. Eliminar carpetas de grupos
            $groupFolders = OrganizationGroupFolder::where('organization_id', $orgId)->get();
            foreach ($groupFolders as $groupFolder) {
                $success = $this->googleDriveService->deleteFolderResilient($groupFolder->google_id, $user->email);
                if ($success) {
                    $deletedFolders++;
                    Log::info('Group folder deleted during organization deletion', [
                        'org_id' => $orgId,
                        'group_id' => $groupFolder->group_id,
                        'folder_id' => $groupFolder->google_id,
                        'folder_name' => $groupFolder->name
                    ]);
                } else {
                    $failedFolders++;
                    Log::error('Failed to delete group folder during organization deletion', [
                        'org_id' => $orgId,
                        'group_id' => $groupFolder->group_id,
                        'folder_id' => $groupFolder->google_id,
                        'folder_name' => $groupFolder->name
                    ]);
                }
            }

            // 3. Eliminar subcarpetas organizacionales
            $subfolders = OrganizationSubfolder::whereHas('folder', function($query) use ($orgId) {
                $query->where('organization_id', $orgId);
            })->get();

            foreach ($subfolders as $subfolder) {
                $success = $this->googleDriveService->deleteFolderResilient($subfolder->google_id, $user->email);
                if ($success) {
                    $deletedFolders++;
                    Log::info('Subfolder deleted during organization deletion', [
                        'org_id' => $orgId,
                        'subfolder_id' => $subfolder->id,
                        'folder_id' => $subfolder->google_id,
                        'folder_name' => $subfolder->name
                    ]);
                } else {
                    $failedFolders++;
                    Log::error('Failed to delete subfolder during organization deletion', [
                        'org_id' => $orgId,
                        'subfolder_id' => $subfolder->id,
                        'folder_id' => $subfolder->google_id,
                        'folder_name' => $subfolder->name
                    ]);
                }
            }

            // 4. Eliminar carpetas principales de la organización
            $organizationFolders = OrganizationFolder::where('organization_id', $orgId)->get();
            foreach ($organizationFolders as $orgFolder) {
                $success = $this->googleDriveService->deleteFolderResilient($orgFolder->google_id, $user->email);
                if ($success) {
                    $deletedFolders++;
                    Log::info('Organization main folder deleted', [
                        'org_id' => $orgId,
                        'org_folder_id' => $orgFolder->id,
                        'folder_id' => $orgFolder->google_id,
                        'folder_name' => $orgFolder->name
                    ]);
                } else {
                    $failedFolders++;
                    Log::error('Failed to delete organization main folder', [
                        'org_id' => $orgId,
                        'org_folder_id' => $orgFolder->id,
                        'folder_id' => $orgFolder->google_id,
                        'folder_name' => $orgFolder->name
                    ]);
                }
            }

            Log::info('Organization Drive folders deletion summary', [
                'org_id' => $orgId,
                'deleted_folders' => $deletedFolders,
                'failed_folders' => $failedFolders,
                'total_attempted' => $deletedFolders + $failedFolders
            ]);

        } catch (\Exception $e) {
            Log::error('Error during organization Drive folders deletion', [
                'org_id' => $orgId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            // No fallar la eliminación de la organización por problemas con Drive
        }
    }
}
