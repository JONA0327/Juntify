<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\OrganizationFolder;
use App\Models\OrganizationGoogleToken;
use App\Services\GoogleDriveService;
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
        $token = $this->initDrive($organization);

        $folderId = $this->drive->createFolder($organization->nombre_organizacion);

        $folder = OrganizationFolder::create([
            'organization_id' => $organization->id,
            'organization_google_token_id' => $token->id,
            'google_id'       => $folderId,
            'name'            => $organization->nombre_organizacion,
        ]);

        $this->drive->shareFolder(
            $folderId,
            config('services.google.service_account_email')
        );

        // Asegurar la creación de las carpetas estándar automatizadas
        try {
            DriveController::ensureStandardMeetingFolders($folder);
        } catch (\Throwable $e) {
            Log::warning('No se pudieron crear las carpetas estándar para la organización', [
                'organization' => $organization->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['id' => $folderId, 'folder' => $folder], 201);
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
        $standardFolders = [];

        if ($connected && $root) {
            try {
                $this->initDrive($organization);
                $folders = DriveController::ensureStandardMeetingFolders($root);
                $standardFolders = collect([
                    $folders['audio'] ?? null,
                    $folders['transcriptions'] ?? null,
                ])->filter()->map(fn ($item) => [
                    'name'      => $item['name'],
                    'google_id' => $item['google_id'],
                    'path'      => $item['path'],
                ])->values();
            } catch (\Throwable $e) {
                Log::warning("No se pudo verificar las carpetas estándar de la organización {$organization->id}: " . $e->getMessage());
            }
        }

        return response()->json([
            'connected'          => $connected,
            'root_folder'        => $root,
            'standard_subfolders'=> $standardFolders,
        ]);
    }
}
