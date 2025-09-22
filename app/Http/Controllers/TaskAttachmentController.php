<?php

namespace App\Http\Controllers;

use App\Models\ArchivoReunion;
use App\Models\AiDocument;
use App\Models\AiTaskDocument;
use App\Models\AiMeetingDocument;
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
        TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();

        $files = ArchivoReunion::where('task_id', $taskId)->orderBy('created_at', 'desc')->get();

        return response()->json(['success' => true, 'files' => $files]);
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
        $task = TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();
        $this->setGoogleDriveToken($request);

        $data = $request->validate([
            'folder_id' => 'nullable|string',
            'file' => 'required|file|max:102400', // 100MB
        ]);

        $uploadedFile = $data['file'];
        $name = $uploadedFile->getClientOriginalName();
        $mime = $uploadedFile->getMimeType();
        $bytes = file_get_contents($uploadedFile->getRealPath());

        // Si no se especifica carpeta, usar/crear 'Documentos' bajo la carpeta raíz del usuario
        $folderId = $data['folder_id'] ?? $this->ensureDocumentsFolderForUser($user);

        $fileId = $this->googleDriveService->uploadFile($name, $mime, $folderId, $bytes);
        $webLink = $this->googleDriveService->getFileLink($fileId);

        $rec = ArchivoReunion::create([
            'task_id' => $task->id,
            'username' => $user->username,
            'name' => $name,
            'mime_type' => $mime,
            'size' => $uploadedFile->getSize(),
            'drive_file_id' => $fileId,
            'drive_folder_id' => $folderId,
            'drive_web_link' => $webLink,
        ]);

        // Registrar como AiDocument y asociar a la tarea y la reunión
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
                'drive_type' => 'personal',
                'processing_status' => 'pending',
                'document_metadata' => ['web_link' => $webLink],
            ]);

            // Asociar a tarea
            AiTaskDocument::create([
                'document_id' => $aiDoc->id,
                'task_id' => (string) $task->id,
                'assigned_by_username' => $user->username,
                'assignment_note' => 'Subido desde tareas',
            ]);

            // Asociar a reunión (si existe en la tarea)
            if (!empty($task->meeting_id)) {
                AiMeetingDocument::create([
                    'document_id' => $aiDoc->id,
                    'meeting_id' => (string) $task->meeting_id,
                    'meeting_type' => AiMeetingDocument::MEETING_TYPE_LEGACY,
                    'assigned_by_username' => $user->username,
                    'assignment_note' => 'Archivo de tarea asociado a reunión',
                ]);
            }

            // Procesamiento en background (OCR/extracción)
            \App\Jobs\ProcessAiDocumentJob::dispatch($aiDoc->id);
        } catch (\Throwable $e) {
            Log::warning('No se pudo registrar AiDocument para archivo de tarea', [
                'task_id' => $task->id,
                'error' => $e->getMessage(),
            ]);
        }

        return response()->json(['success' => true, 'file' => $rec]);
    }

    public function download(Request $request, int $fileId)
    {
        $user = Auth::user();
        $file = ArchivoReunion::findOrFail($fileId);
        // Ensure the owner can access
        TaskLaravel::where('id', $file->task_id)->where('username', $user->username)->firstOrFail();

        $this->setGoogleDriveToken($request);
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

