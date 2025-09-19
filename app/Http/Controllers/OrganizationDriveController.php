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
        $orgRole = $organization->users()
            ->where('users.id', $user->id)
            ->wherePivotIn('rol', ['colaborador', 'administrador'])
            ->exists();
        if ($orgRole) return true;
        // Colaborador o Administrador en cualquier grupo de la organización
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

        $parentFolderId = config('drive.root_folder_id');
        if (empty($parentFolderId)) {
            Log::error('Google Drive root folder ID is not configured.');

            return response()->json([
                'message' => 'El ID de la carpeta raíz de Google Drive no está configurado.',
            ], 500);
        }
        $token = $this->initDrive($organization);

        // Create using Service Account for homogeneity; impersonate org admin if needed
        $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
        $folderId = null;
        $serviceAccountError = null;
        $impersonated = false;

        try {
            $folderId = $serviceAccount->createFolder($organization->nombre_organizacion, $parentFolderId);
        } catch (\Throwable $e) {
            $serviceAccountError = $e;
            $ownerEmail = optional($organization->admin)->email;
            if ($ownerEmail) {
                try {
                    $serviceAccount->impersonate($ownerEmail);
                    $impersonated = true;
                    $folderId = $serviceAccount->createFolder($organization->nombre_organizacion, $parentFolderId);
                } catch (\Throwable $impersonationError) {
                    $serviceAccountError = $impersonationError;
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
                $folderId = $this->drive->createFolder($organization->nombre_organizacion, $parentFolderId);
                $usedOAuthFallback = true;
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

        $serviceEmail = config('services.google.service_account_email');
        if ($usedOAuthFallback) {
            if ($serviceEmail) {
                try {
                    $this->drive->shareFolder($folderId, $serviceEmail);
                } catch (\Throwable $shareError) {
                    Log::warning('createRootFolder (org) failed to share via OAuth fallback', [
                        'org' => $organization->id,
                        'error' => $shareError->getMessage(),
                    ]);
                }
            }
        } else {
            try {
                $serviceAccount->shareFolder($folderId, $serviceEmail);
            } catch (\Throwable $shareError) {
                Log::warning('createRootFolder (org) failed to share via service account', [
                    'org' => $organization->id,
                    'error' => $shareError->getMessage(),
                ]);
            }
        }
        // Ensure standard subfolders exist for organization root
        try {
            $serviceEmail = config('services.google.service_account_email');
            $needed = ['Audios', 'Transcripciones', 'Audios Pospuestos'];
            foreach ($needed as $name) {
                try {
                    $subId = $serviceAccount->createFolder($name, $folderId);
                    \App\Models\OrganizationSubfolder::firstOrCreate([
                        'organization_folder_id' => $folder->id,
                        'google_id'              => $subId,
                    ], ['name' => $name]);
                    try { $serviceAccount->shareFolder($subId, $serviceEmail); } catch (\Throwable $e) { /* ignore */ }
                } catch (\Throwable $e) {
                    Log::warning('OrganizationDriveController: failed to create standard subfolder', [
                        'name' => $name,
                        'parent' => $folderId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('OrganizationDriveController: ensure standard subfolders failed', ['error' => $e->getMessage()]);
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
        // Eliminar primero en Drive, luego en BD
        $this->drive->deleteFile($subfolder->google_id);
        $subfolder->delete();

        return response()->json(['deleted' => true]);
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

        if ($connected && $root) {
            try {
                // Initialize drive client and list subfolders
                $this->initDrive($organization);
                $files = $this->drive->listSubfolders($root->google_id);
                foreach ($files as $file) {
                    $subfolders[] = OrganizationSubfolder::updateOrCreate(
                        [
                            'organization_folder_id' => $root->id,
                            'google_id'              => $file->getId(),
                        ],
                        ['name' => $file->getName()]
                    );
                }
            } catch (\Exception $e) {
                // Si hay problemas con Drive, marcar como desconectado
                Log::warning("Error accessing Drive for organization {$organization->id}: " . $e->getMessage());
                $connected = false;
            }
        }

        return response()->json([
            'connected'   => $connected,
            'root_folder' => $root,
            'subfolders'  => $subfolders,
        ]);
    }
}
