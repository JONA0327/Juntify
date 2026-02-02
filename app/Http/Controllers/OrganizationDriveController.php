<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationGoogleToken;
use App\Models\OrganizationSubfolder;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class OrganizationDriveController extends Controller
{
    protected GoogleDriveService $drive;

    public function __construct(GoogleDriveService $drive)
    {
        $this->drive = $drive;
    }

    protected function userCanManage(Organization $organization): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($organization->admin_id === $user->id) return true;
        // Colaborador o Administrador a nivel de organización
        $orgRole = $organization->users() // Usuarios directamente asociados a la organizacion
            ->where('users.id', $user->id)
            ->wherePivotIn('rol', ['colaborador', 'administrador'])
            ->exists();
        if ($orgRole) return true;
        // Colaborador o Administrador en cualquier grupo de la organización
        // si el usuario es colaborador o administrador en algun grupo de la organizacion
        return $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id)->whereIn('group_user.rol', ['colaborador', 'administrador']);
        })->exists();
    }

    protected function userIsMember(Organization $organization): bool
    {
        $user = auth()->user();
        if (!$user) return false;
        if ($organization->admin_id === $user->id) return true;
        // miembro por pertenencia a la organización o a cualquier grupo
        $inOrg = $organization->users()->where('users.id', $user->id)->exists();
        $inGroup = $organization->groups()->whereHas('users', function($q) use ($user) {
            $q->where('users.id', $user->id);
        })->exists();
        return $inOrg || $inGroup;
    }

    protected function initDrive(Organization $organization): OrganizationGoogleToken
    {
        $token = $organization->googleToken;

        if (!$token) {
            throw new \Exception("La organización no tiene configurado un token de Google Drive");
        }

        if (!$token->isConnected()) {
            throw new \Exception("El token de Google Drive no está configurado correctamente");
        }

        $client = $this->drive->getClient();
        $client->setAccessToken([
            'access_token'  => $token->access_token,
            'refresh_token' => $token->refresh_token,
            'expiry_date'   => $token->expiry_date,
        ]);

        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            } else {
                $err = strtolower($new['error'] ?? '');
                if (str_contains($err, 'invalid_grant')) {
                    // Marcar en memoria para status
                    $token->forceFill(['access_token' => null])->save();
                    throw new \Exception('invalid_grant');
                }
                throw new \Exception("No se pudo renovar el token de Google Drive: " . ($new['error'] ?? 'Error desconocido'));
            }
        }

        return $token;
    }

    public function createRootFolder(Organization $organization)
    {
        if (!$this->userCanManage($organization)) {
            abort(403, 'No autorizado');
        }

        // Prefer parentId from request; if none, we will create under admin's My Drive using impersonation
        $parentFolderId = (string) request('parentId');
        if (!empty($parentFolderId)) {
            Log::info('Organization createRootFolder: using explicit parent', ['parentId' => $parentFolderId, 'org' => $organization->id]);
        } else {
            Log::info('Organization createRootFolder: no parent provided; will create at admin My Drive root via impersonation', ['org' => $organization->id]);
        }
        $token = $this->initDrive($organization);

        // Create using Service Account for homogeneity; impersonate org admin if needed
        $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
        $folderId = null;
        $serviceAccountError = null;
        $impersonated = false;
        $createdViaImpersonation = false;
        $sharedWithSA = false;

        try {
            if (!empty($parentFolderId)) {
                // Create under provided parent with SA
                $folderId = $serviceAccount->createFolder($organization->nombre_organizacion, $parentFolderId, false);
            } else {
                // Create at admin's My Drive root via impersonation
                $ownerEmail = optional($organization->admin)->email;
                if ($ownerEmail) {
                    $serviceAccount->impersonate($ownerEmail);
                    $impersonated = true;
                    $createdViaImpersonation = true;
                    $folderId = $serviceAccount->createFolder($organization->nombre_organizacion, null, false);
                } else {
                    throw new \RuntimeException('No se pudo determinar el email del administrador de la organización');
                }
            }
            
            // Siempre compartir con la cuenta de servicio después de crear
            if ($folderId) {
                $serviceEmail = config('services.google.service_account_email');
                if ($serviceEmail) {
                    try { 
                        $serviceAccount->shareFolder($folderId, $serviceEmail); 
                        $sharedWithSA = true;
                        Log::info('Carpeta raíz de organización compartida con cuenta de servicio', [
                            'org' => $organization->id,
                            'folder_id' => $folderId,
                            'service_email' => $serviceEmail
                        ]);
                    } catch (\Throwable $e) { 
                        Log::warning('No se pudo compartir carpeta raíz de organización con cuenta de servicio', [
                            'org' => $organization->id,
                            'folder_id' => $folderId,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            $serviceAccountError = $e;
            // If we initially tried with SA under a parent and failed, try impersonation fallback
            if (!$impersonated) {
                $ownerEmail = optional($organization->admin)->email;
                if ($ownerEmail) {
                    try {
                        $serviceAccount->impersonate($ownerEmail);
                        $impersonated = true;
                        $createdViaImpersonation = true;
                        $folderId = !empty($parentFolderId)
                            ? $serviceAccount->createFolder($organization->nombre_organizacion, $parentFolderId, false)
                            : $serviceAccount->createFolder($organization->nombre_organizacion, null, false);
                        // Share with SA while impersonating
                        $serviceEmail = config('services.google.service_account_email');
                        if ($serviceEmail) { 
                            try { 
                                $serviceAccount->shareFolder($folderId, $serviceEmail); 
                                $sharedWithSA = true; 
                                Log::info('Carpeta raíz de organización (impersonación fallback) compartida con cuenta de servicio', [
                                    'org' => $organization->id,
                                    'folder_id' => $folderId
                                ]);
                            } catch (\Throwable $e2) { 
                                Log::warning('No se pudo compartir carpeta raíz (impersonación fallback) con cuenta de servicio', [
                                    'org' => $organization->id,
                                    'error' => $e2->getMessage()
                                ]);
                            } 
                        }
                    } catch (\Throwable $impersonationError) {
                        $serviceAccountError = $impersonationError;
                    }

                }
            }
        } finally {
            if ($impersonated) {
                try { $serviceAccount->impersonate(null); } catch (\Throwable $e) { /* ignore */ }
            }
        }

        $usedOAuthFallback = false;
        if (!$folderId) {
            Log::warning('createRootFolder (org) falling back to OAuth client', [
                'org' => $organization->id,
                'error' => $serviceAccountError?->getMessage(),
            ]);

            try {
                $folderId = $this->drive->createFolder($organization->nombre_organizacion, $parentFolderId, false);
                $usedOAuthFallback = true;
                
                // Compartir con la cuenta de servicio usando OAuth
                $serviceEmail = config('services.google.service_account_email');
                if ($serviceEmail) {
                    try {
                        $this->drive->shareFolder($folderId, $serviceEmail);
                        Log::info('Carpeta raíz de organización (OAuth fallback) compartida con cuenta de servicio', [
                            'org' => $organization->id,
                            'folder_id' => $folderId
                        ]);
                    } catch (\Throwable $shareError) {
                        Log::warning('No se pudo compartir carpeta raíz (OAuth fallback) con cuenta de servicio', [
                            'org' => $organization->id,
                            'error' => $shareError->getMessage()
                        ]);
                    }
                }
            } catch (\Throwable $oauthException) {
                Log::error('createRootFolder (org) failed even with OAuth fallback', [
                    'org' => $organization->id,
                    'service_error' => $serviceAccountError?->getMessage(),
                    'oauth_error' => $oauthException->getMessage(),
                ]);

                return response()->json([
                    'message' => 'Error creando la carpeta de organización en Drive: ' . $oauthException->getMessage(),
                ], 502);
            }
        }

        $folder = OrganizationFolder::create([
            'organization_id' => $organization->id,
            'organization_google_token_id' => $token->id,
            'google_id'       => $folderId,
            'name'            => $organization->nombre_organizacion,
        ]);

        // Asegurar permisos del admin sobre la carpeta raíz
        $adminEmail = optional($organization->admin)->email;
        if ($adminEmail && !$createdViaImpersonation) {
            try { 
                if ($usedOAuthFallback) {
                    $this->drive->shareFolder($folderId, $adminEmail); 
                } else {
                    $serviceAccount->shareItem($folderId, $adminEmail, 'writer'); 
                }
            } catch (\Throwable $e) { 
                Log::warning('No se pudo compartir carpeta raíz con admin', [
                    'org' => $organization->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Ensure standard subfolders exist for organization root (multi strategy)
        try {
            $serviceEmail = config('services.google.service_account_email');
            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos'];
            foreach ($needed as $name) {
                $subId = null;
                // Strategy 1: direct Service Account
                try { $subId = $serviceAccount->createFolder($name, $folderId); }
                catch (\Throwable $e1) {
                    Log::debug('Org std subfolder SA direct failed', ['org' => $organization->id, 'name' => $name, 'error' => $e1->getMessage()]);
                    // Strategy 2: impersonation (if not already impersonated and admin email available and impersonation not disabled)
                    if (!$impersonated && $adminEmail && !\App\Services\GoogleServiceAccount::impersonationDisabled()) {
                        try {
                            $serviceAccount->impersonate($adminEmail);
                            $impersonated = true; // mark so final cleanup resets
                            $subId = $serviceAccount->createFolder($name, $folderId);
                        } catch (\Throwable $eImp) {
                            Log::debug('Org std subfolder SA impersonation failed', ['org' => $organization->id, 'name' => $name, 'error' => $eImp->getMessage()]);
                        } finally {
                            try { $serviceAccount->impersonate(null); } catch (\Throwable $eR) { /* ignore */ }
                        }
                    }
                }
                // Strategy 3: OAuth fallback
                if (!$subId) {
                    try { $subId = $this->drive->createFolder($name, $folderId); }
                    catch (\Throwable $eOauth) {
                        Log::warning('OrganizationDriveController: failed to create standard subfolder (all strategies)', [
                            'org' => $organization->id,
                            'name' => $name,
                            'parent' => $folderId,
                            'error' => $eOauth->getMessage(),
                        ]);
                    }
                }
                if ($subId) {
                    try {
                        \App\Models\OrganizationSubfolder::firstOrCreate([
                            'organization_folder_id' => $folder->id,
                            'google_id'              => $subId,
                        ], ['name' => $name]);
                        // Share with SA (if created via OAuth or impersonation) and admin
                        if ($serviceEmail) { try { $serviceAccount->shareFolder($subId, $serviceEmail); } catch (\Throwable $eS) { /* ignore */ } }
                        if ($adminEmail) { try { $serviceAccount->shareItem($subId, $adminEmail, 'writer'); } catch (\Throwable $eU) { /* ignore */ } }
                    } catch (\Throwable $persistE) {
                        Log::warning('OrganizationDriveController: failed to persist standard subfolder', [
                            'org' => $organization->id,
                            'name' => $name,
                            'error' => $persistE->getMessage(),
                        ]);
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OrganizationDriveController: ensure standard subfolders failed (wrapper)', ['error' => $e->getMessage()]);
        }

        return response()->json(['id' => $folderId, 'folder' => $folder], 201);
    }

    public function createSubfolder(Request $request, Organization $organization)
    {
        if (!$this->userCanManage($organization)) {
            abort(403, 'No autorizado');
        }
        $request->validate(['name' => 'required|string']);

        $root = $organization->folder;
        if (!$root) {
            return response()->json(['message' => 'Root folder not found'], 404);
        }

        $this->initDrive($organization);

        $folderId = $this->drive->createFolder($request->input('name'), $root->google_id);

        OrganizationSubfolder::create([
            'organization_folder_id' => $root->id,
            'google_id'              => $folderId,
            'name'                   => $request->input('name'),
        ]);

        $this->drive->shareFolder(
            $folderId,
            config('services.google.service_account_email')
        );

        return response()->json(['id' => $folderId], 201);
    }

    public function listSubfolders(Organization $organization)
    {
        if (!$this->userIsMember($organization)) {
            abort(403, 'No autorizado');
        }
        $root = $organization->folder;
        if (!$root) {
            return response()->json(['message' => 'Root folder not found'], 404);
        }

        $this->initDrive($organization);

        $files = $this->drive->listSubfolders($root->google_id);
        $subfolders = [];
        foreach ($files as $file) {
            $subfolders[] = OrganizationSubfolder::updateOrCreate(
                [
                    'organization_folder_id' => $root->id,
                    'google_id'              => $file->getId(),
                ],
                ['name' => $file->getName()]
            );
        }

        return response()->json([
            'root_folder' => $root,
            'subfolders'  => $subfolders,
        ]);
    }

    public function renameSubfolder(Request $request, Organization $organization, OrganizationSubfolder $subfolder)
    {
        if (!$this->userCanManage($organization)) {
            abort(403, 'No autorizado');
        }
        // Validar pertenencia
        if ($subfolder->folder->organization_id !== $organization->id) {
            abort(404);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255'
        ]);

        $this->initDrive($organization);
        $this->drive->renameFile($subfolder->google_id, $validated['name']);

        $subfolder->update(['name' => $validated['name']]);

        return response()->json($subfolder->fresh());
    }

    public function deleteSubfolder(Organization $organization, OrganizationSubfolder $subfolder)
    {
        if (!$this->userCanManage($organization)) {
            abort(403, 'No autorizado');
        }
        // Validar pertenencia
        if ($subfolder->folder->organization_id !== $organization->id) {
            abort(404);
        }

        $this->initDrive($organization);
        $user = auth()->user();

        // Eliminar primero en Drive usando método robusta, luego en BD
        $deleteSuccess = $this->drive->deleteFileResilient($subfolder->google_id, $user->email);

        if ($deleteSuccess) {
            Log::info('Subcarpeta eliminada exitosamente de Google Drive', [
                'subfolder_id' => $subfolder->id,
                'google_id' => $subfolder->google_id,
                'organization_id' => $organization->id,
                'deleted_by' => $user->email
            ]);
        } else {
            Log::error('No se pudo eliminar la subcarpeta de Google Drive', [
                'subfolder_id' => $subfolder->id,
                'google_id' => $subfolder->google_id,
                'organization_id' => $organization->id,
                'deleted_by' => $user->email
            ]);
        }

        // Eliminar de la BD independientemente del resultado en Drive para evitar datos huérfanos
        $subfolder->delete();

        return response()->json([
            'deleted' => true,
            'drive_deleted' => $deleteSuccess
        ]);
    }

    /**
     * Returns Drive connection and folder status for an organization.
     * Always 200 with flags to avoid noisy 404s in the UI.
     */
    public function status(Organization $organization)
    {
        if (!$this->userIsMember($organization)) {
            abort(403, 'No autorizado');
        }

        // Check if organization has a connected Google token
        $token = $organization->googleToken;
        $connected = $token && $token->isConnected();

        $root = $organization->folder;
        $subfolders = [];
        $needsReconnect = false;
        $impersonationDisabled = \App\Services\GoogleServiceAccount::impersonationDisabled();

        if ($connected && $root) {
            try {
                try {
                    $this->initDrive($organization);
                } catch (\Exception $eInit) {
                    if ($eInit->getMessage() === 'invalid_grant') {
                        $needsReconnect = true;
                        $connected = false;
                        goto response_block;
                    }
                    throw $eInit;
                }
                $files = [];
                if ($root->google_id) {
                    try {
                        $files = $this->drive->listSubfolders($root->google_id);
                    } catch (\Throwable $eList) {
                        Log::warning('Organization status: listSubfolders failed', [
                            'org' => $organization->id,
                            'root_google_id' => $root->google_id,
                            'error' => $eList->getMessage(),
                        ]);
                    }
                    foreach ($files as $file) {
                        $subfolders[] = OrganizationSubfolder::updateOrCreate(
                            [
                                'organization_folder_id' => $root->id,
                                'google_id'              => $file->getId(),
                            ],
                            ['name' => $file->getName()]
                        );
                    }
                } else {
                    Log::warning('Organization status: root folder has null google_id; skipping listSubfolders', [
                        'org' => $organization->id,
                        'root_id' => $root->id,
                    ]);
                }
            } catch (\Exception $e) {
                // Si hay problemas con Drive, marcar como desconectado
                Log::warning("Error accessing Drive for organization {$organization->id}: " . $e->getMessage());
                $connected = false;
            }
        }

        response_block:
        return response()->json([
            'connected'   => $connected,
            'root_folder' => $root,
            'subfolders'  => $subfolders,
            'needs_reconnect' => $needsReconnect,
            'impersonation_disabled' => $impersonationDisabled,
            'root_missing' => $root && !$root->google_id,
        ]);
    }
}
