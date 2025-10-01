<?php

namespace App\Http\Controllers;

use App\Models\MeetingContentContainer;
use App\Models\OrganizationContainerFolder;
use App\Services\GoogleDriveService;
use App\Services\OrganizationDriveHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContainerFileController extends Controller
{
    public function __construct(
        protected GoogleDriveService $driveService,
        protected OrganizationDriveHierarchyService $hierarchyService
    ) {}
    /**
     * List files for a container (JSON)
     */
    public function index(MeetingContentContainer $container)
    {
        $this->authorizeAccess($container);
        // Asegurar token de Google para el usuario (si existe) antes de llamar a Drive
        $this->setUserDriveToken();
        // Obtener/crear carpeta del contenedor
        $folder = OrganizationContainerFolder::where('container_id', $container->id)->first();
        if (!$folder || !$folder->google_id) {
            try {
                $group = $container->group; $org = $group?->organization;
                if ($group && $org) {
                    $ensured = $this->hierarchyService->ensureContainerFolder($org, $group, $container);
                    $folder = $ensured ?: $folder;
                }
            } catch (\Throwable $e) {
                Log::warning('index: ensureContainerFolder failed', [ 'container_id'=>$container->id, 'error'=>$e->getMessage() ]);
            }
        }
        if (!$folder || !$folder->google_id) {
            return response()->json(['data' => []]);
        }
        $files = [];
        try {
            $drive = $this->driveService->getDrive();
            $resp = $drive->files->listFiles([
                'q' => sprintf("'%s' in parents and trashed=false", $folder->google_id),
                'fields' => 'files(id,name,mimeType,size,createdTime)',
                'supportsAllDrives' => true,
                'includeItemsFromAllDrives' => true,
            ]);
            foreach ($resp->getFiles() as $f) {
                $files[] = [
                    'id' => $f->getId(),
                    'name' => $f->getName(),
                    'size' => (int)($f->getSize() ?? 0),
                    'mime' => $f->getMimeType(),
                    'uploaded_at' => $f->getCreatedTime(),
                    'url' => route('containers.files.download', [$container->id, $f->getId()])
                ];
            }
        } catch (\Throwable $e) {
            Log::error('index: drive list failed', [ 'container_id'=>$container->id, 'error'=>$e->getMessage() ]);
        }
        return response()->json(['data' => $files]);
    }

    /**
     * Store (upload) a file (max 150MB)
     */
    public function store(Request $request, MeetingContentContainer $container)
    {
        $this->authorizeAccess($container);
        $this->setUserDriveToken();
        $validated = $request->validate([
            'file' => 'required|file|max:153600',
        ], [ 'file.max' => 'El archivo supera el límite de 150MB.' ]);

        $uploaded = $validated['file'];
        $originalName = $uploaded->getClientOriginalName();
        $mimeType = $uploaded->getMimeType() ?: 'application/octet-stream';
        $size = (int)$uploaded->getSize();

        $folder = OrganizationContainerFolder::where('container_id', $container->id)->first();
        if (!$folder || !$folder->google_id) {
            try {
                $group = $container->group; $org = $group?->organization;
                if ($group && $org) {
                    $ensured = $this->hierarchyService->ensureContainerFolder($org, $group, $container);
                    if ($ensured && $ensured->google_id) $folder = $ensured;
                }
            } catch (\Throwable $e) {
                Log::error('store: ensureContainerFolder failed', ['container_id'=>$container->id,'error'=>$e->getMessage()]);
            }
        }
        if (!$folder || !$folder->google_id) {
            return response()->json(['message' => 'No se pudo asegurar carpeta del contenedor'], 500);
        }
        try {
            $contents = file_get_contents($uploaded->getRealPath());
            $driveFileId = $this->driveService->uploadFile($originalName, $mimeType, $folder->google_id, $contents);
            // Recuperar metadata del archivo recién subido
            $meta = $this->driveService->getDrive()->files->get($driveFileId, [
                'fields' => 'id,name,mimeType,size,createdTime'
            ]);
            $filePayload = [
                'id' => $meta->getId(),
                'name' => $meta->getName(),
                'size' => (int)($meta->getSize() ?? $size),
                'mime' => $meta->getMimeType(),
                'uploaded_at' => $meta->getCreatedTime(),
                'url' => route('containers.files.download', [$container->id, $meta->getId()]),
                'storage' => 'drive'
            ];
            Log::info('store: drive upload ok', [
                'container_id' => $container->id,
                'group_id' => $container->group_id,
                'drive_folder_id' => $folder->google_id,
                'drive_file_id' => $driveFileId,
                'size' => $filePayload['size']
            ]);
            return response()->json(['message' => 'Archivo subido correctamente', 'file' => $filePayload], 201);
        } catch (\Throwable $e) {
            Log::error('store: drive upload failed', [
                'container_id' => $container->id,
                'drive_folder_id' => $folder->google_id ?? null,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error al subir a Drive', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Download / stream the file
     */
    /**
     * Download file for container.
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function download(MeetingContentContainer $container, string $file)
    {
        $this->authorizeAccess($container);
        try {
            $contents = $this->driveService->downloadFileContent($file);
            if ($contents === null) abort(404);
            // Intentar obtener metadata para nombre/mime
            $meta = null;
            try {
                $meta = $this->driveService->getDrive()->files->get($file, ['fields' => 'id,name,mimeType']);
            } catch (\Throwable $e) {
                Log::warning('download: could not fetch metadata', ['file_id'=>$file,'error'=>$e->getMessage()]);
            }
            $name = $meta?->getName() ?: ($file.'.bin');
            $mime = $meta?->getMimeType() ?: 'application/octet-stream';
            return response()->streamDownload(function() use ($contents) { echo $contents; }, $name, [ 'Content-Type' => $mime ]);
        } catch (\Throwable $e) {
            Log::error('download: failed', ['file_id'=>$file,'error'=>$e->getMessage()]);
            abort(404);
        }
    }

    protected function authorizeAccess(MeetingContentContainer $container): void
    {
        $user = auth()->user();
        if (!$user) abort(401);
        $group = $container->group;
        if (!$group) abort(404);
        $isMember = $group->users()->where('user_id', $user->id)->exists();
        if (!$isMember) abort(403, 'No perteneces a este grupo');
    }

    /**
     * Establece el token de Google Drive para el usuario autenticado si está disponible.
     * Si falla, se loguea advertencia y se continúa (podría usar service account si configurado).
     */
    protected function setUserDriveToken(): void
    {
        $user = auth()->user();
        if (!$user) return;
        try {
            $tokenModel = $user->googleToken; // relación por username
            if (!$tokenModel) {
                Log::warning('setUserDriveToken: user has no googleToken', ['user_id'=>$user->id]);
                return;
            }
            $raw = $tokenModel->access_token ?? $tokenModel->access_token_json;
            if (!$raw) {
                Log::warning('setUserDriveToken: token model missing access token', ['user_id'=>$user->id]);
                return;
            }
            $tokenData = $raw;
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $tokenData = $decoded;
                } else {
                    $tokenData = ['access_token' => $raw];
                }
            }
            $this->driveService->setAccessToken($tokenData);
            if ($this->driveService->getClient()->isAccessTokenExpired()) {
                if (!empty($tokenModel->refresh_token)) {
                    try {
                        $new = $this->driveService->refreshToken($tokenModel->refresh_token);
                        $tokenModel->update([
                            'access_token' => $new,
                            'expiry_date' => now()->addSeconds($new['expires_in'] ?? 3600),
                        ]);
                        Log::info('setUserDriveToken: token refreshed', ['user_id'=>$user->id]);
                    } catch (\Throwable $e) {
                        Log::error('setUserDriveToken: refresh failed', ['user_id'=>$user->id,'error'=>$e->getMessage()]);
                    }
                } else {
                    Log::warning('setUserDriveToken: token expired and no refresh token', ['user_id'=>$user->id]);
                }
            }
        } catch (\Throwable $e) {
            Log::error('setUserDriveToken: unexpected error', ['error'=>$e->getMessage()]);
        }
    }
}
