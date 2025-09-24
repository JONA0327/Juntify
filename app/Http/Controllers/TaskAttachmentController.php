<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAiDocumentJob;
use App\Models\ArchivoReunion;
use App\Models\AiDocument;
use App\Models\AiTaskDocument;
use App\Models\AiMeetingDocument;
use App\Models\Organization;
use App\Models\OrganizationSubfolder;
use App\Models\TaskLaravel;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TaskAttachmentController extends Controller
{
    use GoogleDriveHelpers;

    protected GoogleDriveService $googleDriveService;

    public function __construct(GoogleDriveService $drive)
    {
        $this->googleDriveService = $drive;
    }

    public function index(int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($taskId);
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para ver los archivos de esta tarea');
        }

        $files = ArchivoReunion::where('task_id', $taskId)->orderBy('created_at', 'desc')->get();

        $driveOptions = $this->driveOptionsForUser($user);

        return response()->json([
            'success' => true,
            'files' => $files,
            'drive_options' => $driveOptions,
        ]);
    }

    public function folders(Request $request): JsonResponse
    {
        $this->setGoogleDriveToken($request);
        $parentId = $request->query('parents');
        if ($parentId) {
            $folders = $this->googleDriveService->listSubfolders($parentId);
        } else {
            $folders = $this->googleDriveService->listFolders("mimeType='application/vnd.google-apps.folder' and trashed=false");
        }
        $out = array_map(fn($f) => ['id' => $f->getId(), 'name' => $f->getName()], $folders);
        return response()->json(['success' => true, 'folders' => $out]);
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($taskId);
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para subir archivos a esta tarea');
        }

        $user->loadMissing('googleToken', 'organization.googleToken', 'organization.folder');

        $data = $request->validate([
            'folder_id' => 'nullable|string',
            'drive_type' => 'nullable|in:personal,organization',
            'file' => 'nullable|file|max:102400',
            'files' => 'nullable',
            'files.*' => 'file|max:102400',
        ]);

        $driveType = $data['drive_type'] ?? 'personal';

        $uploaded = collect();
        if ($request->hasFile('file') && $request->file('file')) {
            $uploaded->push($request->file('file'));
        }
        if ($request->hasFile('files')) {
            $filesInput = $request->file('files');
            if (is_array($filesInput)) {
                foreach ($filesInput as $fileCandidate) {
                    if ($fileCandidate) {
                        $uploaded->push($fileCandidate);
                    }
                }
            } elseif ($filesInput) {
                $uploaded->push($filesInput);
            }
        }
        $uploadedFiles = $uploaded->filter()->values();

        if ($uploadedFiles->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Selecciona un archivo para subir'], 422);
        }

        $availableOptions = collect($this->driveOptionsForUser($user))->pluck('value')->all();
        if (!in_array($driveType, $availableOptions, true)) {
            return response()->json(['success' => false, 'message' => 'No tienes permisos para subir a este Drive'], 403);
        }

        $folderId = $data['folder_id'] ?? null;
        $organizationId = null;

        try {
            if ($driveType === 'organization') {
                $organization = $user->organization;
                if (!$organization) {
                    return response()->json(['success' => false, 'message' => 'No tienes una organización activa configurada'], 422);
                }
                $this->setOrganizationDriveToken($organization);
                $folderId = $folderId ?: $this->ensureOrganizationDocumentsFolder($organization);
                $organizationId = $organization->id;
            } else {
                $this->setGoogleDriveToken($user);
                $folderId = $folderId ?: $this->ensureDocumentsFolderForUser($user);
            }
        } catch (\Throwable $exception) {
            Log::error('No se pudo preparar el entorno de Drive para subir archivos de tarea', [
                'user_id' => $user->id,
                'drive_type' => $driveType,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'No fue posible conectar con Google Drive. Verifica tu integración e inténtalo nuevamente.',
            ], 500);
        }

        $createdFiles = [];

        foreach ($uploadedFiles as $uploadedFile) {
            $name = $uploadedFile->getClientOriginalName();
            $mime = $uploadedFile->getMimeType();
            $bytes = file_get_contents($uploadedFile->getRealPath());

            $fileId = $this->googleDriveService->uploadFile($name, $mime, $folderId, $bytes);
            $webLink = $this->googleDriveService->getFileLink($fileId);

            $record = ArchivoReunion::create([
                'task_id' => $task->id,
                'username' => $user->username,
                'name' => $name,
                'mime_type' => $mime,
                'size' => $uploadedFile->getSize(),
                'drive_file_id' => $fileId,
                'drive_folder_id' => $folderId,
                'drive_web_link' => $webLink,
                'drive_type' => $driveType,
                'organization_id' => $organizationId,
            ]);

            $createdFiles[] = $record;

            try {
                $docType = $this->guessDocumentType($mime, $name);
                $aiDoc = AiDocument::create([
                    'username' => $user->username,
                    'name' => pathinfo($name, PATHINFO_FILENAME),
                    'original_filename' => $name,
                    'document_type' => $docType,
                    'mime_type' => $mime,
                    'file_size' => $uploadedFile->getSize(),
                    'drive_file_id' => $fileId,
                    'drive_folder_id' => $folderId,
                    'drive_type' => $driveType,
                    'processing_status' => 'pending',
                    'document_metadata' => [
                        'web_link' => $webLink,
                        'drive_type' => $driveType,
                        'organization_id' => $organizationId,
                    ],
                ]);

                AiTaskDocument::create([
                    'document_id' => $aiDoc->id,
                    'task_id' => (string) $task->id,
                    'assigned_by_username' => $user->username,
                    'assignment_note' => 'Subido desde tareas',
                ]);

                if (!empty($task->meeting_id)) {
                    AiMeetingDocument::create([
                        'document_id' => $aiDoc->id,
                        'meeting_id' => (string) $task->meeting_id,
                        'meeting_type' => AiMeetingDocument::MEETING_TYPE_LEGACY,
                        'assigned_by_username' => $user->username,
                        'assignment_note' => 'Archivo de tarea asociado a reunión',
                    ]);
                }

                ProcessAiDocumentJob::dispatch($aiDoc->id);
            } catch (\Throwable $e) {
                Log::warning('No se pudo registrar AiDocument para archivo de tarea', [
                    'task_id' => $task->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'files' => $createdFiles,
        ]);
    }

    public function download(Request $request, int $fileId)
    {
        $user = Auth::user();
        $file = ArchivoReunion::findOrFail($fileId);
        $task = TaskLaravel::with('user')->findOrFail($file->task_id);
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para descargar este archivo');
        }

        if (($file->drive_type ?? 'personal') === 'organization') {
            $organization = $this->resolveOrganizationForFile($file, $user, $task);
            if (!$organization) {
                abort(403, 'No se encontró la organización del archivo');
            }
            if ($task->username !== $user->username && $user->current_organization_id !== $organization->id) {
                abort(403, 'No perteneces a la organización propietaria de este archivo');
            }
            $this->setOrganizationDriveToken($organization);
        } else {
            $this->setGoogleDriveToken($user);
        }
        $contents = $this->googleDriveService->downloadFileContent($file->drive_file_id);
        if ($contents === null) {
            return response()->json(['success' => false, 'message' => 'Archivo no encontrado en Drive'], 404);
        }

        return response($contents, 200, [
            'Content-Type' => $file->mime_type ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="'.addslashes($file->name).'"',
        ]);
    }

    private function ensureDocumentsFolderForUser($user): string
    {
        // Intentar encontrar carpeta 'Documentos' bajo la raíz conocida del usuario
        $escaped = str_replace("'", "\\'", 'Documentos');
        $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and name='{$escaped}'";
        $folders = $this->googleDriveService->listFolders($query);
        foreach ($folders as $f) {
            if (strcasecmp($f->getName(), 'Documentos') === 0) {
                return $f->getId();
            }
        }
        // Si no existe, crearla en la raíz por defecto
        return $this->googleDriveService->createFolder('Documentos', null);
    }

    private function ensureOrganizationDocumentsFolder(Organization $organization): string
    {
        $organization->loadMissing('folder');
        $root = $organization->folder;
        if (!$root || empty($root->google_id)) {
            throw new \RuntimeException('La organización no tiene una carpeta raíz configurada en Drive.');
        }

        $existing = OrganizationSubfolder::where('organization_folder_id', $root->id)
            ->where('name', 'Documentos')
            ->first();
        if ($existing) {
            return $existing->google_id;
        }

        $folderId = $this->googleDriveService->createFolder('Documentos', $root->google_id);
        OrganizationSubfolder::create([
            'organization_folder_id' => $root->id,
            'google_id' => $folderId,
            'name' => 'Documentos',
        ]);

        return $folderId;
    }

    private function driveOptionsForUser($user): array
    {
        $user->loadMissing('googleToken', 'organization.googleToken', 'organization.folder');

        $options = [];
        if (!empty(optional($user->googleToken)->access_token)) {
            $options[] = [
                'value' => 'personal',
                'label' => 'Drive personal',
            ];
        }

        $organization = $user->organization;
        if ($organization && $organization->googleToken && $organization->googleToken->isConnected() && optional($organization->folder)->google_id) {
            $options[] = [
                'value' => 'organization',
                'label' => 'Drive de la organización',
                'organization_id' => $organization->id,
                'organization_name' => $organization->nombre_organizacion,
            ];
        }

        return $options;
    }

    private function setOrganizationDriveToken(Organization $organization): void
    {
        $token = $organization->googleToken;
        if (!$token || !$token->isConnected()) {
            throw new \RuntimeException('La organización no tiene Google Drive conectado.');
        }

        $tokenData = $token->access_token;
        if (is_array($tokenData)) {
            $accessPayload = $tokenData;
        } elseif (is_string($tokenData) && !empty($tokenData)) {
            $accessPayload = ['access_token' => $tokenData];
        } else {
            $accessPayload = ['access_token' => $tokenData];
        }

        $client = $this->googleDriveService->getClient();
        $client->setAccessToken(array_merge($accessPayload, [
            'refresh_token' => $token->refresh_token,
            'expiry_date' => optional($token->expiry_date)?->timestamp,
        ]));

        if ($client->isAccessTokenExpired()) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new,
                    'expiry_date' => now()->addSeconds($new['expires_in'] ?? 3600),
                ]);
                $client->setAccessToken($new);
            } else {
                throw new \RuntimeException('No se pudo renovar el token de Google Drive de la organización.');
            }
        }
    }

    private function resolveOrganizationForFile(ArchivoReunion $file, $user, TaskLaravel $task): ?Organization
    {
        if (!empty($file->organization_id)) {
            return Organization::find($file->organization_id);
        }

        if ($task->relationLoaded('user')) {
            $task->user->loadMissing('organization');
            if ($task->user->organization) {
                return $task->user->organization;
            }
        }

        return $user->organization;
    }

    private function guessDocumentType(string $mime, string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (str_contains($mime, 'pdf') || $ext === 'pdf') return 'pdf';
        if (str_contains($mime, 'word') || in_array($ext, ['doc', 'docx'])) return 'word';
        if (str_contains($mime, 'sheet') || str_contains($mime, 'excel') || in_array($ext, ['xls', 'xlsx', 'csv'])) return 'excel';
        if (str_contains($mime, 'presentation') || in_array($ext, ['ppt', 'pptx'])) return 'powerpoint';
        if (str_starts_with($mime, 'image/') || in_array($ext, ['jpg','jpeg','png','gif','bmp','tif','tiff','webp'])) return 'image';
        return 'text';
    }
}

