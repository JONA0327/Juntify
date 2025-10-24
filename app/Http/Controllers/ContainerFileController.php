<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\MeetingContentContainer;
use App\Models\OrganizationContainerFolder;
use App\Services\GoogleDriveService;
use App\Services\OrganizationDriveHierarchyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Google\Service\Exception as GoogleServiceException;

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
                'fields' => 'files(id,name,mimeType,size,createdTime,webViewLink,webContentLink)',
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
                    'url' => route('containers.files.download', [$container->id, $f->getId()]),
                    'webViewLink' => method_exists($f, 'getWebViewLink') ? $f->getWebViewLink() : null,
                    'webContentLink' => method_exists($f, 'getWebContentLink') ? $f->getWebContentLink() : null
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

            // Compartir el archivo con "anyone" como lector para previsualización pública
            try {
                $permission = new \Google\Service\Drive\Permission([
                    'type' => 'anyone',
                    'role' => 'reader',
                ]);
                $this->driveService->getDrive()->permissions->create($driveFileId, $permission, [
                    'supportsAllDrives' => true,
                ]);
            } catch (\Throwable $e) {
                Log::warning('No se pudo compartir el archivo como público', ['file_id' => $driveFileId, 'error' => $e->getMessage()]);
            }

            // Compartir el archivo con los miembros del grupo del contenedor
            try {
                $this->hierarchyService->shareContainerFileWithGroupMembers(
                    $container,
                    $driveFileId,
                    auth()->id()
                );
            } catch (\Throwable $e) {
                Log::warning('store: failed to share file with group members', [
                    'container_id' => $container->id,
                    'drive_file_id' => $driveFileId,
                    'error' => $e->getMessage(),
                ]);
            }

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

        Log::info('Download attempt started', [
            'container_id' => $container->id,
            'file_id' => $file,
            'user_id' => auth()->id()
        ]);

        // Asegurar token de Google
        $this->setUserDriveToken();

        try {
            // Primero verificar que el archivo existe y obtener metadata
            $meta = null;
            try {
                $meta = $this->driveService->getDrive()->files->get($file, [
                    'fields' => 'id,name,mimeType,size,parents,capabilities',
                    'supportsAllDrives' => true
                ]);

                Log::info('File metadata retrieved', [
                    'file_id' => $file,
                    'name' => $meta->getName(),
                    'size' => $meta->getSize(),
                    'mime' => $meta->getMimeType(),
                    'parents' => $meta->getParents()
                ]);

            } catch (\Throwable $e) {
                Log::error('Failed to get file metadata', [
                    'file_id' => $file,
                    'error' => $e->getMessage(),
                    'code' => $e->getCode()
                ]);
                abort(404, 'Archivo no encontrado o sin permisos');
            }

            if (!$meta) {
                Log::error('No metadata found for file', ['file_id' => $file]);
                abort(404, 'Archivo no encontrado');
            }

            // Intentar descargar el contenido
            $contents = $this->driveService->downloadFileContent($file);

            if ($contents === null) {
                Log::error('Download returned null content', ['file_id' => $file]);
                abort(404, 'No se pudo descargar el archivo');
            }

            Log::info('File content downloaded successfully', [
                'file_id' => $file,
                'content_length' => strlen($contents)
            ]);

            $name = $meta->getName() ?: ($file.'.bin');
            $mime = $meta->getMimeType() ?: 'application/octet-stream';

            return response()->streamDownload(function() use ($contents) {
                echo $contents;
            }, $name, [
                'Content-Type' => $mime,
                'Content-Length' => strlen($contents),
                'Content-Disposition' => 'attachment; filename="' . $name . '"'
            ]);

        } catch (\Throwable $e) {
            Log::error('Download failed with exception', [
                'file_id' => $file,
                'container_id' => $container->id,
                'error' => $e->getMessage(),
                'code' => $e->getCode(),
                'trace' => $e->getTraceAsString()
            ]);

            abort(500, 'Error al descargar el archivo: ' . $e->getMessage());
        }
    }

    /**
     * Debug download issues
     */
    public function debugDownload(MeetingContentContainer $container, string $file)
    {
        $this->authorizeAccess($container);
        $this->setUserDriveToken();

        $debug = [
            'container_id' => $container->id,
            'file_id' => $file,
            'user_id' => auth()->id(),
            'user_has_token' => auth()->user()->googleToken !== null,
            'client_has_token' => $this->driveService->getClient()->getAccessToken() !== null,
            'steps' => []
        ];

        try {
            $debug['steps'][] = 'Iniciando debug de descarga';

            // Verificar metadata del archivo
            $meta = $this->driveService->getDrive()->files->get($file, [
                'fields' => 'id,name,mimeType,size,parents,capabilities,permissions',
                'supportsAllDrives' => true
            ]);

            $debug['file_info'] = [
                'name' => $meta->getName(),
                'size' => $meta->getSize(),
                'mime' => $meta->getMimeType(),
                'parents' => $meta->getParents()
            ];

            $capabilities = $meta->getCapabilities();
            if ($capabilities) {
                $debug['capabilities'] = [
                    'canDownload' => $capabilities->canDownload,
                    'canRead' => $capabilities->canRead ?? null,
                    'canEdit' => $capabilities->canEdit ?? null
                ];
            }

            $debug['steps'][] = 'Metadata obtenida correctamente';

            // Intentar descarga
            $content = $this->driveService->downloadFileContent($file);

            $debug['download_result'] = [
                'success' => $content !== null,
                'content_length' => $content ? strlen($content) : 0,
                'content_type' => gettype($content)
            ];

            if ($content) {
                $debug['steps'][] = 'Descarga exitosa';
                $debug['sample_content'] = substr($content, 0, 100) . '...';
            } else {
                $debug['steps'][] = 'Descarga falló - contenido null';
            }

        } catch (\Throwable $e) {
            $debug['error'] = [
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
                'class' => get_class($e)
            ];
            $debug['steps'][] = 'Error durante debug: ' . $e->getMessage();
        }

        return response()->json($debug, 200, [], JSON_PRETTY_PRINT);
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
     * Comparte un archivo subido con todos los miembros del grupo del contenedor.
     */
    protected function shareFileWithGroupMembers(MeetingContentContainer $container, string $driveFileId): void
    {
        $group = $container->group;
        $organization = $group?->organization;

        if (!$group || !$organization) {
            return;
        }

        $members = $group->users()->withPivot('rol')->get();
        if ($members->isEmpty()) {
            return;
        }

        $currentUserId = auth()->id();

        foreach ($members as $member) {
            $email = $member->email;
            if (!$email) {
                continue;
            }

            if ($member->id === $currentUserId) {
                continue;
            }

            $role = match ($member->pivot?->rol) {
                Group::ROLE_ADMINISTRADOR, Group::ROLE_COLABORADOR => 'writer',
                default => 'reader',
            };

            try {
                $permission = new \Google\Service\Drive\Permission([
                    'type' => 'user',
                    'role' => $role,
                    'emailAddress' => $email,
                ]);

                $this->driveService->getDrive()->permissions->create($driveFileId, $permission, [
                    'supportsAllDrives' => true,
                    'sendNotificationEmail' => false,
                ]);
            } catch (GoogleServiceException $e) {
                $code = (int) $e->getCode();
                $message = strtolower($e->getMessage());
                $alreadyShared = $code === 409 || str_contains($message, 'already') || str_contains($message, 'duplicate');

                if ($alreadyShared) {
                    Log::info('shareFileWithGroupMembers: permission already exists', [
                        'drive_file_id' => $driveFileId,
                        'email' => $email,
                    ]);
                    continue;
                }

                Log::warning('shareFileWithGroupMembers: failed to share via user token', [
                    'drive_file_id' => $driveFileId,
                    'email' => $email,
                    'role' => $role,
                    'code' => $code,
                    'error' => $e->getMessage(),
                ]);
            } catch (\Throwable $e) {
                Log::warning('shareFileWithGroupMembers: unexpected error', [
                    'drive_file_id' => $driveFileId,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
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
