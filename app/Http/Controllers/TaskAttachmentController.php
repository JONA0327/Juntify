<?php

namespace App\Http\Controllers;

use App\Models\ArchivoReunion;
use App\Models\GoogleToken;
use App\Models\TaskLaravel;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

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

    public function store(Request $request, int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();
        $this->setGoogleDriveToken($request);

        $data = $request->validate([
            'file' => 'required|file|max:51200', // 50MB
        ]);

        $googleToken = $user->googleToken;
        if (!$googleToken) {
            throw ValidationException::withMessages([
                'google_drive' => 'El usuario no tiene configurada la integración con Google Drive.',
            ]);
        }

        $documentsFolderId = $this->ensureDocumentsFolder($googleToken);

        $uploadedFile = $data['file'];
        $name = $uploadedFile->getClientOriginalName();
        $mime = $uploadedFile->getMimeType();
        $bytes = file_get_contents($uploadedFile->getRealPath());

        $fileId = $this->googleDriveService->uploadFile($name, $mime, $documentsFolderId, $bytes);
        $webLink = $this->googleDriveService->getFileLink($fileId);

        $rec = ArchivoReunion::create([
            'task_id' => $task->id,
            'username' => $user->username,
            'name' => $name,
            'mime_type' => $mime,
            'size' => $uploadedFile->getSize(),
            'drive_file_id' => $fileId,
            'drive_folder_id' => $documentsFolderId,
            'drive_web_link' => $webLink,
        ]);

        return response()->json(['success' => true, 'file' => $rec]);
    }

    private function ensureDocumentsFolder(GoogleToken $googleToken): string
    {
        if (!$googleToken->recordings_folder_id) {
            throw ValidationException::withMessages([
                'google_drive' => 'No se encontró la carpeta raíz personal en Google Drive.',
            ]);
        }

        $folderName = 'Documentos';
        $existingId = $this->findFolderByName($folderName, $googleToken->recordings_folder_id);

        if ($existingId) {
            return $existingId;
        }

        return $this->googleDriveService->createFolder($folderName, $googleToken->recordings_folder_id);
    }

    private function findFolderByName(string $folderName, ?string $parentId = null): ?string
    {
        $escapedName = str_replace("'", "\\'", $folderName);
        $query = "mimeType='application/vnd.google-apps.folder' and trashed=false and name='{$escapedName}'";

        if ($parentId) {
            $query .= " and '{$parentId}' in parents";
        }

        $folders = $this->googleDriveService->listFolders($query);

        foreach ($folders as $folder) {
            if (strcasecmp($folder->getName(), $folderName) === 0) {
                return $folder->getId();
            }
        }

        return null;
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
}

