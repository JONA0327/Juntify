<?php

namespace App\Http\Controllers;

use App\Models\ArchivoReunion;
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
        $folders = $this->googleDriveService->listFolders("mimeType='application/vnd.google-apps.folder' and trashed=false");
        $out = array_map(fn($f) => ['id' => $f->getId(), 'name' => $f->getName()], $folders);
        return response()->json(['success' => true, 'folders' => $out]);
    }

    public function store(Request $request, int $taskId): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::where('id', $taskId)->where('username', $user->username)->firstOrFail();
        $this->setGoogleDriveToken($request);

        $data = $request->validate([
            'folder_id' => 'required|string',
            'file' => 'required|file|max:51200', // 50MB
        ]);

        $uploadedFile = $data['file'];
        $name = $uploadedFile->getClientOriginalName();
        $mime = $uploadedFile->getMimeType();
        $bytes = file_get_contents($uploadedFile->getRealPath());

        $fileId = $this->googleDriveService->uploadFile($name, $mime, $data['folder_id'], $bytes);
        $webLink = $this->googleDriveService->getFileLink($fileId);

        $rec = ArchivoReunion::create([
            'task_id' => $task->id,
            'username' => $user->username,
            'name' => $name,
            'mime_type' => $mime,
            'size' => $uploadedFile->getSize(),
            'drive_file_id' => $fileId,
            'drive_folder_id' => $data['folder_id'],
            'drive_web_link' => $webLink,
        ]);

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
}

