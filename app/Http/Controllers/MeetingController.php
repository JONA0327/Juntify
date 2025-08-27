<?php

namespace App\Http\Controllers;

use App\Models\TranscriptionLaravel;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\MeetingShare;
use App\Models\MeetingContentContainer;
use App\Models\Container;
use App\Models\TaskLaravel;
use App\Models\Meeting;
use App\Models\KeyPoint;
use App\Models\Transcription;
use App\Models\Task;
use Carbon\Carbon;
use App\Services\GoogleDriveService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Traits\GoogleDriveHelpers;
use App\Traits\MeetingContentParsing;

class MeetingController extends Controller
{
    use GoogleDriveHelpers, MeetingContentParsing;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    /**
     * Muestra la página principal de reuniones.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // Server-side render of meetings list (keeps JS fallback too)
        try {
            $user = Auth::user();

            // Verificar si el usuario es invitado en todas las organizaciones
            $organizations = \App\Models\Organization::whereHas('groups.users', function($query) use ($user) {
                $query->where('users.id', $user->id);
            })->with(['groups' => function($query) use ($user) {
                $query->whereHas('users', function($subQuery) use ($user) {
                    $subQuery->where('users.id', $user->id);
                });
            }])->get();

            $isOnlyGuest = $organizations->every(function($org) use ($user) {
                return $org->groups->every(function($group) use ($user) {
                    $userInGroup = $group->users->where('id', $user->id)->first();
                    return $userInGroup && $userInGroup->pivot->rol === 'invitado';
                });
            });

            if ($isOnlyGuest && $organizations->count() > 0) {
                // Redirigir a organizaciones si es solo invitado
                return redirect()->route('organization.index')
                    ->with('error', 'Los usuarios invitados no tienen acceso a esta sección');
            }

            $this->setGoogleDriveToken($user);

            $meetings = \App\Models\TranscriptionLaravel::where('username', $user->username)
                ->whereDoesntHave('containers')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                        'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                    ];
                });

            return view('reuniones', [ 'meetings' => $meetings ]);
        } catch (\Throwable $e) {
            // If anything fails, return view without meetings (JS will fetch)
            Log::warning('Meetings SSR failed: ' . $e->getMessage());
            return view('reuniones');
        }
    }

    /**
     * Obtiene todas las reuniones del usuario autenticado
     */
    public function getMeetings(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            $legacyMeetings = TranscriptionLaravel::where('username', $user->username)
                ->whereDoesntHave('containers')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at,
                        'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                        'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                        'is_legacy' => true,
                    ];
                });

            $modernMeetings = Meeting::where('username', $user->username)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->title,
                        'created_at' => $meeting->created_at ?? $meeting->date,
                        'audio_folder' => $this->getFolderName($meeting->recordings_folder_id),
                        'transcript_folder' => 'Base de datos',
                        'is_legacy' => false,
                    ];
                });

            $meetings = $legacyMeetings
                ->concat($modernMeetings)
                ->sortByDesc('created_at')
                ->map(function ($meeting) {
                    $meeting['created_at'] = $meeting['created_at'] instanceof Carbon
                        ? $meeting['created_at']->format('d/m/Y H:i')
                        : Carbon::parse($meeting['created_at'])->format('d/m/Y H:i');
                    return $meeting;
                })
                ->values();

            return response()->json([
                'success' => true,
                'meetings' => $meetings,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar reuniones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene las reuniones compartidas con el usuario autenticado
     */
    public function getSharedMeetings(): JsonResponse
    {
        try {
            $user = Auth::user();
            $meetingIds = MeetingShare::where('to_username', $user->username)->pluck('meeting_id');
            $meetings = TranscriptionLaravel::whereIn('id', $meetingIds)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                        'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                    ];
                });

            return response()->json([
                'success' => true,
                'meetings' => $meetings
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar reuniones compartidas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene los contenedores del usuario autenticado
     */
    public function getContainers(): JsonResponse
    {
        try {
            $user = Auth::user();
            $containers = Container::where('username', $user->username)
                ->withCount('meetings')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($container) {
                    return [
                        'id' => $container->id,
                        'name' => $container->name,
                        'created_at' => $container->created_at->format('d/m/Y H:i'),
                        'meetings_count' => $container->meetings_count,
                    ];
                });

            return response()->json([
                'success' => true,
                'containers' => $containers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar contenedores: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeContainer(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $container = Container::create([
            'username' => $user->username,
            'name' => $data['name'],
        ]);

        return response()->json([
            'success' => true,
            'container' => $container,
        ], 201);
    }

    public function addMeetingToContainer(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'meeting_id' => ['required', 'exists:transcriptions_laravel,id'],
        ]);

        $container = Container::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        $meeting = TranscriptionLaravel::where('id', $data['meeting_id'])
            ->where('username', $user->username)
            ->firstOrFail();

        $container->meetings()->syncWithoutDetaching([$meeting->id]);

        return response()->json(['success' => true]);
    }

    public function getContainerMeetings($id): JsonResponse
    {
        $user = Auth::user();

        $container = Container::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        $meetings = $container->meetings()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($meeting) {
                return [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                    'audio_drive_id' => $meeting->audio_drive_id,
                    'transcript_drive_id' => $meeting->transcript_drive_id,
                    'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                    'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                ];
            });

        return response()->json([
            'success' => true,
            'meetings' => $meetings,
        ]);
    }
    /**
     * Obtiene los detalles completos de una reunión específica
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Intentar buscar una reunión legacy primero
            $legacyMeeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->first();

            $this->setGoogleDriveToken($user);

            if ($legacyMeeting) {
                if (empty($legacyMeeting->transcript_drive_id)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Transcripción no disponible',
                    ], 404);
                }

                $transcriptContent = $this->downloadFromDrive($legacyMeeting->transcript_drive_id);
                $transcriptResult = $this->decryptJuFile($transcriptContent);
                $transcriptData = $transcriptResult['data'];
                $needsEncryption = $transcriptResult['needs_encryption'];

                $transcriptData = $this->extractMeetingDataFromJson($transcriptData);
                $audioData = $this->googleDriveService->findAudioInFolder(
                    $legacyMeeting->audio_drive_id,
                    $legacyMeeting->meeting_name
                );
                $audioPath = $audioData['downloadUrl'] ?? null;
                $audioDriveId = $audioData['fileId'] ?? null;
                $processedData = $this->processTranscriptData($transcriptData);
                unset($processedData['tasks']);

                $segments = $processedData['segments'] ?? [];
                $transcription = $processedData['transcription'] ?? '';
                if (empty($segments)) {
                    $segments = [];
                    if (is_array($transcription)) {
                        $transcription = implode(' ', $transcription);
                    }
                } elseif (is_array($transcription)) {
                    $transcription = implode(' ', $transcription);
                }

                $tasks = TaskLaravel::where('meeting_id', $legacyMeeting->id)
                    ->where('username', $user->username)
                    ->get();

                return response()->json([
                    'success' => true,
                    'meeting' => [
                        'id' => $legacyMeeting->id,
                        'meeting_name' => $legacyMeeting->meeting_name,
                        'is_legacy' => true,
                        'created_at' => $legacyMeeting->created_at->format('d/m/Y H:i'),
                        'audio_path' => $audioPath,
                        'audio_drive_id' => $audioDriveId,
                        'summary' => $processedData['summary'],
                        'key_points' => $processedData['key_points'],
                        'transcription' => $transcription,
                        'tasks' => $tasks,
                        'speakers' => $processedData['speakers'] ?? [],
                        'segments' => $segments,
                        'audio_folder' => $this->getFolderName($legacyMeeting->audio_drive_id),
                        'transcript_folder' => $this->getFolderName($legacyMeeting->transcript_drive_id),
                        'needs_encryption' => $needsEncryption,
                    ]
                ]);
            }

            // Reunión moderna en base de datos
            $meeting = Meeting::with([
                'tasks' => function ($q) use ($user) {
                    $q->where('username', $user->username);
                },
                'keyPoints' => function ($q) use ($user) {
                    $q->whereHas('meeting', function ($mq) use ($user) {
                        $mq->where('username', $user->username);
                    })->ordered();
                },
                'transcriptions' => function ($q) use ($user) {
                    $q->whereHas('meeting', function ($mq) use ($user) {
                        $mq->where('username', $user->username);
                    })->byTime();
                },
            ])->where('id', $id)
              ->where('username', $user->username)
              ->firstOrFail();

            $audioFile = null;
            if ($meeting->recordings_folder_id) {
                $files = $this->googleDriveService->searchFiles($meeting->title, $meeting->recordings_folder_id);
                $audioFile = $files[0] ?? null;
            }
            if (!$audioFile) {
                $files = $this->googleDriveService->searchFiles($meeting->title, null);
                $audioFile = $files[0] ?? null;
            }

            $audioPath = null;
            if ($audioFile) {
                $tempMeeting = (object) [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->title,
                    'audio_drive_id' => $audioFile->getId(),
                    'audio_download_url' => null,
                ];
                $audioPath = $this->getAudioPath($tempMeeting);
            }

            $segments = $meeting->transcriptions->map(function ($t) {
                return [
                    'time' => $t->time,
                    'speaker' => $t->speaker,
                    'text' => $t->text,
                    'display_speaker' => $t->display_speaker,
                ];
            });

            $transcriptionText = $segments->pluck('text')->implode(' ');

            return response()->json([
                'success' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->title,
                    'is_legacy' => false,
                    'created_at' => ($meeting->date ?? $meeting->created_at)->format('d/m/Y H:i'),
                    'audio_path' => $audioPath,
                    'summary' => $meeting->summary,
                    'key_points' => $meeting->keyPoints->pluck('point_text'),
                    'transcription' => $transcriptionText,
                    'tasks' => $meeting->tasks,
                    'speakers' => $meeting->speaker_map ?? [],
                    'segments' => $segments,
                    'audio_folder' => $this->getFolderName($meeting->recordings_folder_id),
                    'transcript_folder' => 'Base de datos',
                    'needs_encryption' => false,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la reunión: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Actualiza el nombre de una reunión
     */
    public function updateName(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255'
            ]);

            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $newName = $request->name;
            $oldName = $meeting->meeting_name;

            // Si el nombre no cambió, no hacer nada
            if ($newName === $oldName) {
                return response()->json([
                    'success' => true,
                    'message' => 'Nombre actualizado correctamente'
                ]);
            }

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            // Actualizar archivo .ju en Drive
            if ($meeting->transcript_drive_id) {
                try {
                    $this->googleDriveService->renameFile(
                        $meeting->transcript_drive_id,
                        $newName . '.ju'
                    );
                    Log::info("Archivo .ju renombrado en Drive", [
                        'file_id' => $meeting->transcript_drive_id,
                        'old_name' => $oldName . '.ju',
                        'new_name' => $newName . '.ju'
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al renombrar archivo .ju en Drive', [
                        'file_id' => $meeting->transcript_drive_id,
                        'error' => $e->getMessage()
                    ]);
                    throw new \Exception('Error al actualizar el archivo .ju en Drive: ' . $e->getMessage());
                }
            }

            // Actualizar archivo de audio en Drive
            if ($meeting->audio_drive_id) {
                try {
                    // Obtener información del archivo actual para mantener la extensión
                    $fileInfo = $this->googleDriveService->getFileInfo($meeting->audio_drive_id);
                    $extension = pathinfo($fileInfo['name'], PATHINFO_EXTENSION);

                    $this->googleDriveService->renameFile(
                        $meeting->audio_drive_id,
                        $newName . '.' . $extension
                    );
                    Log::info("Archivo de audio renombrado en Drive", [
                        'file_id' => $meeting->audio_drive_id,
                        'old_name' => $oldName . '.' . $extension,
                        'new_name' => $newName . '.' . $extension
                    ]);
                } catch (\Exception $e) {
                    Log::error('Error al renombrar archivo de audio en Drive', [
                        'file_id' => $meeting->audio_drive_id,
                        'error' => $e->getMessage()
                    ]);
                    throw new \Exception('Error al actualizar el archivo de audio en Drive: ' . $e->getMessage());
                }
            }

            // Actualizar en la base de datos
            $meeting->meeting_name = $newName;
            $meeting->save();

            Log::info("Reunión renombrada correctamente", [
                'meeting_id' => $id,
                'old_name' => $oldName,
                'new_name' => $newName,
                'user' => $user->username
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nombre actualizado correctamente en Drive y base de datos'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar nombre de reunión', [
                'meeting_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Actualiza los segmentos de una reunión
     */
    public function updateSegments(Request $request, $id): JsonResponse
    {
        try {
            $request->validate([
                'segments'   => 'required|array',
                'newDriveId' => 'nullable|string',
            ]);

            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            $driveId = $request->input('newDriveId') ?? $meeting->transcript_drive_id;

            if (!$driveId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de transcripción no encontrado'
                ], 404);
            }

            $this->setGoogleDriveToken($user);

            // Descargar y decodificar el archivo actual
            $content = $this->googleDriveService->downloadFileContent($driveId);
            try {
                $data = json_decode(Crypt::decryptString($content), true) ?: [];
            } catch (\Exception $e) {
                $data = json_decode($content, true) ?: [];
            }

            // Mezclar segmentos
            $data['segments'] = $request->segments;

            $encrypted = Crypt::encryptString(json_encode($data));

            $webLink = $this->googleDriveService->updateFileContent(
                $driveId,
                'application/json',
                $encrypted
            );

            $updates = [];
            if ($driveId !== $meeting->transcript_drive_id) {
                $updates['transcript_drive_id'] = $driveId;
            }
            if ($webLink && $webLink !== $meeting->transcript_download_url) {
                $updates['transcript_download_url'] = $webLink;
            }
            if (!empty($updates)) {
                TranscriptionLaravel::where('id', $id)->update($updates);
            }

            return response()->json([
                'success' => true
            ]);
        } catch (\Exception $e) {
            Log::error('Error al actualizar segmentos de reunión', [
                'meeting_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Encripta y guarda el contenido de la reunión en Google Drive
     */
    public function encryptJu(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $meeting = TranscriptionLaravel::where('id', $id)
            ->where('username', $user->username)
            ->firstOrFail();

        $this->setGoogleDriveToken($request);

        $payload = json_encode([
            'segments'   => $request->input('segments'),
            'summary'    => $request->input('summary'),
            'key_points' => $request->input('key_points'),
            'tasks'      => $request->input('tasks'),
        ]);

        $encrypted = Crypt::encryptString($payload);

        $this->googleDriveService->updateFileContent(
            $meeting->transcript_drive_id,
            'application/json',
            $encrypted
        );

        return response()->json(['success' => true]);
    }

    /**
     * Elimina una reunión
     */
    public function delete($id): JsonResponse
    {
        return $this->destroy($id);
    }

    /**
     * Elimina una reunión (método para rutas DELETE)
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            Log::info("Iniciando eliminación de reunión", [
                'meeting_id' => $id,
                'meeting_name' => $meeting->meeting_name,
                'user' => $user->username
            ]);

            // Eliminar archivos de Google Drive PRIMERO
            $driveErrors = [];

            // Eliminar archivo .ju de Drive
            if ($meeting->transcript_drive_id) {
                try {
                    $this->googleDriveService->deleteFile($meeting->transcript_drive_id);
                    Log::info("Archivo .ju eliminado de Drive", [
                        'file_id' => $meeting->transcript_drive_id
                    ]);
                } catch (\Exception $e) {
                    $driveErrors[] = 'Error al eliminar archivo .ju: ' . $e->getMessage();
                    Log::error('Error al eliminar archivo de transcripción de Drive', [
                        'file_id' => $meeting->transcript_drive_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Eliminar archivo de audio de Drive
            if ($meeting->audio_drive_id) {
                try {
                    $this->googleDriveService->deleteFile($meeting->audio_drive_id);
                    Log::info("Archivo de audio eliminado de Drive", [
                        'file_id' => $meeting->audio_drive_id
                    ]);
                } catch (\Exception $e) {
                    $driveErrors[] = 'Error al eliminar archivo de audio: ' . $e->getMessage();
                    Log::error('Error al eliminar archivo de audio de Drive', [
                        'file_id' => $meeting->audio_drive_id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Si hubo errores en Drive pero no son críticos, continuar con la eliminación de BD
            if (!empty($driveErrors)) {
                Log::warning("Errores en Drive durante eliminación, pero continuando", [
                    'errors' => $driveErrors,
                    'meeting_id' => $id
                ]);
            }

            // Eliminar registro de la base de datos
            $meeting->delete();

            Log::info("Reunión eliminada completamente", [
                'meeting_id' => $id,
                'drive_errors' => $driveErrors
            ]);

            $message = 'Reunión eliminada correctamente';
            if (!empty($driveErrors)) {
                $message .= ', pero con algunos errores en Drive: ' . implode(', ', $driveErrors);
            }

            return response()->json([
                'success' => true,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            Log::error('Error crítico al eliminar reunión', [
                'meeting_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reunión: ' . $e->getMessage()
            ], 500);
        }
    }



    private function storeTemporaryFile($content, $filename): string
    {
        $path = 'temp/' . $filename;
        Storage::disk('public')->put($path, $content);
    $fullPath = storage_path('app/public/' . $path);

        // Log para debuggear
        Log::info('Archivo temporal guardado', [
            'filename' => $filename,
            'path' => $path,
            'full_path' => $fullPath,
            'exists' => Storage::disk('public')->exists($path),
            'size' => Storage::disk('public')->size($path)
        ]);

        return $fullPath;
    }

    /**
     * Obtiene la ruta del audio para una reunión
     * Prioriza la URL directa de descarga si está disponible,
     * sino descarga desde Drive y detecta el formato automáticamente
     */
    private function getAudioPath($meeting): ?string
    {
        try {
            // Si ya tenemos una URL de descarga directa, verificar que sea válida
            if (!empty($meeting->audio_download_url)) {
                $normalized = $this->normalizeDriveUrl($meeting->audio_download_url);

                try {
                    $response = Http::head($normalized);
                    $contentType = $response->header('Content-Type');
                    if ($response->ok() && $contentType && str_starts_with($contentType, 'audio')) {
                        Log::info('Usando URL directa de descarga para audio', [
                            'meeting_id' => $meeting->id,
                            'url' => $normalized,
                            'content_type' => $contentType,
                        ]);
                        return $normalized;
                    }

                    Log::warning('URL directa de audio no válida', [
                        'meeting_id' => $meeting->id,
                        'url' => $normalized,
                        'status' => $response->status(),
                        'content_type' => $contentType,
                    ]);
                } catch (\Exception $e) {
                    Log::warning('Error verificando URL de audio', [
                        'meeting_id' => $meeting->id,
                        'url' => $normalized,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Si no hay URL directa, descargar desde Drive
            if (empty($meeting->audio_drive_id)) {
                Log::warning('No hay audio_drive_id para la reunión', [
                    'meeting_id' => $meeting->id
                ]);
                return null;
            }

            // Obtener información del archivo para detectar formato
            $fileInfo = $this->googleDriveService->getFileInfo($meeting->audio_drive_id);
            $fileName = $fileInfo->getName();
            $mimeType = $fileInfo->getMimeType();

            // Detectar extensión del archivo
            $extension = $this->detectAudioExtension($fileName, $mimeType);

            Log::info('Información del archivo de audio', [
                'meeting_id' => $meeting->id,
                'file_name' => $fileName,
                'mime_type' => $mimeType,
                'detected_extension' => $extension
            ]);

            // Descargar el contenido del archivo
            $audioContent = $this->downloadFromDrive($meeting->audio_drive_id);

            // Generar nombre de archivo temporal con la extensión correcta
            $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meeting->meeting_name);
            $audioFileName = $sanitizedName . '_' . $meeting->id . '.' . $extension;

            return $this->storeTemporaryFile($audioContent, $audioFileName);

        } catch (\Exception $e) {
            Log::error('Error obteniendo audio path', [
                'meeting_id' => $meeting->id,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Detecta la extensión del archivo de audio basado en el nombre y MIME type
     */
    private function detectAudioExtension($fileName, $mimeType): string
    {
        // Primero intentar extraer la extensión del nombre del archivo
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        // Lista de extensiones de audio válidas
        $validAudioExtensions = ['mp3', 'aac', 'wav', 'ogg', 'webm', 'mp4', 'm4a', 'flac'];

        if (in_array($extension, $validAudioExtensions)) {
            return $extension;
        }

        // Si no se detectó por el nombre, usar el MIME type
        $mimeToExtension = [
            'audio/mpeg' => 'mp3',
            'audio/mp3' => 'mp3',
            'audio/aac' => 'aac',
            'audio/mp4' => 'mp4',
            'audio/x-m4a' => 'm4a',
            'audio/wav' => 'wav',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/ogg' => 'ogg',
            'audio/webm' => 'webm',
            'audio/flac' => 'flac',
            'video/webm' => 'webm', // Algunos archivos webm con audio
        ];

        $baseMimeType = explode(';', strtolower($mimeType))[0];

        if (isset($mimeToExtension[$baseMimeType])) {
            return $mimeToExtension[$baseMimeType];
        }

        // Si no se pudo detectar, asumir mp3 como fallback
        Log::warning('No se pudo detectar la extensión del audio, usando mp3 como fallback', [
            'file_name' => $fileName,
            'mime_type' => $mimeType
        ]);

        return 'mp3';
    }

    /**
     * Obtiene el nombre de la carpeta de grabaciones desde la BD
     */
    private function getRecordingsFolderName(string $username): string
    {
        try {
            // Buscar token del usuario para obtener recordings_folder_id
            $token = GoogleToken::where('username', $username)->first();
            if (!$token || empty($token->recordings_folder_id)) {
                return 'Grabaciones';
            }

            // Buscar en la tabla folders el nombre de esa carpeta
            $folder = Folder::where('google_id', $token->recordings_folder_id)->first();
            if ($folder && !empty($folder->name)) {
                return $folder->name;
            }

            return 'Grabaciones';
        } catch (\Exception $e) {
            Log::warning('getRecordingsFolderName: error fetching folder name', [
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            return 'Grabaciones';
        }
    }

    /**
     * Limpia archivos temporales del modal
     */
    public function cleanupModal(): JsonResponse
    {
        try {
            // Limpiar archivos temporales más antiguos de 1 hora
            $tempPath = storage_path('app/public/temp');
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                $oneHourAgo = time() - 3600;

                foreach ($files as $file) {
                    if (is_file($file) && filemtime($file) < $oneHourAgo) {
                        unlink($file);
                    }
                }
            }

            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            Log::error('cleanupModal error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false], 500);
        }
    }

    /**
     * Descarga el archivo .ju de una reunión
     */
    public function downloadJuFile($id)
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);

            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            if (empty($meeting->transcript_drive_id)) {
                return response()->json(['error' => 'No se encontró archivo .ju para esta reunión'], 404);
            }

            // Descargar el contenido del archivo
            $content = $this->googleDriveService->downloadFileContent($meeting->transcript_drive_id);

            // Crear nombre de archivo
            $filename = 'meeting_' . $meeting->id . '.ju';

            return response($content)
                ->header('Content-Type', 'application/octet-stream')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Error downloading .ju file', [
                'meeting_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al descargar archivo .ju'], 500);
        }
    }

    /**
     * Descarga el archivo de audio de una reunión
     */
    public function downloadAudioFile($id)
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);

            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            if (empty($meeting->audio_drive_id)) {
                return response()->json(['error' => 'No se encontró archivo de audio para esta reunión'], 404);
            }

            // Descargar el contenido del archivo
            $content = $this->googleDriveService->downloadFileContent($meeting->audio_drive_id);

            // Crear nombre de archivo (asumiendo que es mp3, pero podría ser otro formato)
            $filename = 'meeting_' . $meeting->id . '_audio.mp3';

            return response($content)
                ->header('Content-Type', 'audio/mpeg')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Error downloading audio file', [
                'meeting_id' => $id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => 'Error al descargar archivo de audio'], 500);
        }
    }

    /**
     * Genera y descarga un reporte PDF de una reunión
     */
    public function downloadReport(Request $request, TranscriptionLaravel $meeting)
    {
        $user = Auth::user();

        if ($meeting->username !== $user->username) {
            abort(403, 'No tienes acceso a esta reunión');
        }

        $sections = $request->input('sections', ['summary', 'key_points', 'transcription', 'tasks']);
        if (!is_array($sections)) {
            $sections = explode(',', $sections);
        }

        $summary = null;
        $keyPoints = [];
        $transcription = '';
        $tasks = collect();

        if (in_array('summary', $sections)) {
            $summary = DB::table('meeting_files')
                ->where('meeting_id', $meeting->id)
                ->value('summary');
        }

        if (in_array('key_points', $sections)) {
            $keyPoints = DB::table('key_points')
                ->join('transcriptions_laravel', 'key_points.meeting_id', '=', 'transcriptions_laravel.id')
                ->where('key_points.meeting_id', $meeting->id)
                ->where('transcriptions_laravel.username', $user->username)
                ->orderBy('key_points.order_num')
                ->pluck('key_points.point_text')
                ->toArray();
        }

        if (in_array('transcription', $sections)) {
            $transcription = DB::table('transcriptions')
                ->join('transcriptions_laravel', 'transcriptions.meeting_id', '=', 'transcriptions_laravel.id')
                ->where('transcriptions.meeting_id', $meeting->id)
                ->where('transcriptions_laravel.username', $user->username)
                ->orderBy('transcriptions.id')
                ->pluck('transcriptions.text')
                ->implode("\n");
        }

        if (in_array('tasks', $sections)) {
            $tasks = TaskLaravel::where('meeting_id', $meeting->id)
                ->where('username', $user->username)
                ->get(['tarea', 'descripcion', 'fecha_limite', 'progreso'])
                ->map(function ($task) {
                    return [
                        'text' => $task->tarea,
                        'description' => $task->descripcion,
                        'due_date' => $task->fecha_limite,
                        'completed' => ($task->progreso ?? 0) >= 100,
                        'progress' => $task->progreso ?? 0,
                    ];
                });
        }

        $pdf = Pdf::loadView('pdf.meeting-report', [
            'meeting' => $meeting,
            'summary' => $summary,
            'keyPoints' => $keyPoints,
            'transcription' => $transcription,
            'tasks' => $tasks,
            'reportTitle' => 'Reporte de Reunión',
            'reportDate' => now()->format('d/m/Y'),
            'meetingName' => $meeting->meeting_name,
            'participants' => $meeting->participants_list ?? [],
            'reportGeneratedAt' => now(),
        ])->setPaper('letter', 'portrait');

        return $pdf->download('meeting_' . $meeting->id . '_report.pdf');
    }

    /**
     * Transmite el audio de una reunión
     */
    public function streamAudio($meeting)
    {
        try {
            $user = Auth::user();
            $this->setGoogleDriveToken($user);

            $meetingModel = TranscriptionLaravel::where('id', $meeting)
                ->where('username', $user->username)
                ->firstOrFail();

            // Generar el nombre base del archivo temporal
            $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meetingModel->meeting_name);
            $pattern = storage_path('app/public/temp/' . $sanitizedName . '_' . $meetingModel->id . '.*');
            $existingFiles = glob($pattern);

            if (!empty($existingFiles)) {
                $audioPath = $existingFiles[0];
            } else {
                $audioPath = $this->getAudioPath($meetingModel);

                if (!$audioPath) {
                    return response()->json(['error' => 'Audio no disponible'], 404);
                }

                if (str_starts_with($audioPath, 'http')) {
                    return redirect()->away($audioPath);
                }
            }

            $mimeType = mime_content_type($audioPath) ?: 'audio/mpeg';

            return response()->file($audioPath, [
                'Content-Type' => $mimeType,
            ]);
        } catch (\Exception $e) {
            Log::error('Error streaming audio file', [
                'meeting_id' => $meeting,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => 'Error al obtener audio'], 500);
        }
    }

    /**
     * Obtiene las reuniones pendientes del usuario
     */
    public function getPendingMeetings()
    {
        try {
            $user = Auth::user();

            // Verificar si el usuario tiene carpeta pendiente
            $pendingFolder = \App\Models\PendingFolder::where('username', $user->username)->first();

            // Obtener grabaciones pendientes del usuario
            $pendingRecordings = \App\Models\PendingRecording::where('username', $user->username)
                ->where('status', 'pending')
                ->get();

            $pendingMeetings = [];

            foreach ($pendingRecordings as $recording) {
                try {
                    // Configurar Google Drive si hay token
                    if ($user->google_token) {
                        $this->setGoogleDriveToken($user);
                        // Intentar obtener información del archivo de Google Drive
                        $fileInfo = $this->googleDriveService->getFileInfo($recording->audio_drive_id);

                        $pendingMeetings[] = [
                            'id' => $recording->id,
                            'name' => $fileInfo->getName() ?: $recording->meeting_name,
                            'drive_file_id' => $recording->audio_drive_id,
                            'created_at' => $recording->created_at->format('d/m/Y H:i'),
                            'size' => $fileInfo->getSize() ? $this->formatBytes($fileInfo->getSize()) : 'N/A',
                            'status' => $recording->status
                        ];
                    } else {
                        // Si no hay token de Google, usar solo datos de la DB
                        $pendingMeetings[] = [
                            'id' => $recording->id,
                            'name' => $recording->meeting_name,
                            'drive_file_id' => $recording->audio_drive_id,
                            'created_at' => $recording->created_at->format('d/m/Y H:i'),
                            'size' => 'N/A',
                            'status' => $recording->status
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('Error getting pending recording info', [
                        'recording_id' => $recording->id,
                        'error' => $e->getMessage()
                    ]);
                    // Incluir el registro aunque no podamos obtener info completa
                    $pendingMeetings[] = [
                        'id' => $recording->id,
                        'name' => $recording->meeting_name ?: ('Audio - ' . $recording->created_at->format('d/m/Y H:i')),
                        'drive_file_id' => $recording->audio_drive_id,
                        'created_at' => $recording->created_at->format('d/m/Y H:i'),
                        'size' => 'N/A',
                        'status' => $recording->status
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'pending_meetings' => $pendingMeetings,
                'has_pending' => count($pendingMeetings) > 0,
                'folder_info' => $pendingFolder ? [
                    'name' => $pendingFolder->name,
                    'google_id' => $pendingFolder->google_id
                ] : null
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting pending meetings', [
                'error' => $e->getMessage()
            ]);
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener reuniones pendientes'
            ], 500);
        }
    }

    /**
     * Analiza una reunión pendiente - Fase 1: Descarga y procesamiento
     */
    public function analyzePendingMeeting($id)
    {
        try {
            $user = Auth::user();

            $pendingRecording = \App\Models\PendingRecording::where('id', $id)
                ->where('username', $user->username)
                ->where('status', 'pending')
                ->firstOrFail();

            // Cambiar status a 'processing'
            $pendingRecording->update(['status' => 'processing']);

            // Guardar información en memoria para el proceso
            $originalAudioName = $pendingRecording->meeting_name;

            try {
                // Descargar el audio de Google Drive usando la cuenta de servicio
                $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
                $audioContent = $serviceAccount->downloadFile($pendingRecording->audio_drive_id);

                if (!$audioContent) {
                    throw new \Exception('No se pudo descargar el audio de Google Drive');
                }

                // Guardar temporalmente el archivo de audio
                $tempFileName = 'pending_' . $id . '_' . time() . '.tmp';
                $tempPath = storage_path('app/temp/' . $tempFileName);

                // Crear directorio si no existe
                if (!file_exists(dirname($tempPath))) {
                    mkdir(dirname($tempPath), 0755, true);
                }

                file_put_contents($tempPath, $audioContent);

                // Guardar información del proceso en session para mantener el estado
                session(['pending_analysis_' . $id => [
                    'original_name' => $originalAudioName,
                    'temp_file' => $tempPath,
                    'drive_file_id' => $pendingRecording->audio_drive_id,
                    'pending_id' => $id,
                    'username' => $user->username
                ]]);

                return response()->json([
                    'success' => true,
                    'message' => 'Audio descargado y listo para procesamiento',
                    'recording_id' => $pendingRecording->id,
                    'filename' => $originalAudioName,
                    'status' => 'processing',
                    'temp_file' => $tempFileName,
                    'redirect_to_processing' => true
                ]);

            } catch (\Exception $e) {
                // Si hay error en la descarga, revertir el status
                $pendingRecording->update([
                    'status' => 'pending',
                    'error_message' => 'Error al descargar: ' . $e->getMessage()
                ]);
                throw $e;
            }

        } catch (\Exception $e) {
            Log::error('Error analyzing pending meeting', [
                'recording_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al procesar audio: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Completa el procesamiento de una reunión pendiente - Fase 2: Mover y guardar
     */
    public function completePendingMeeting(Request $request)
    {
        try {
            $request->validate([
                'pending_id' => 'required|integer',
                'meeting_name' => 'required|string',
                'root_folder' => 'required|string',
                'transcription_subfolder' => 'nullable|string',
                'audio_subfolder' => 'nullable|string',
                'transcription_data' => 'required',
                'analysis_results' => 'required'
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Datos de validación incorrectos',
                'validation_errors' => $e->errors()
            ], 422);
        }

        try {
            $user = Auth::user();
            $pendingId = $request->input('pending_id');

            Log::info('Iniciando completePendingMeeting', [
                'pending_id' => $pendingId,
                'user' => $user->username,
                'meeting_name' => $request->input('meeting_name')
            ]);

            // Verificar que el registro esté en estado processing
            $pendingRecording = \App\Models\PendingRecording::where('id', $pendingId)
                ->where('username', $user->username)
                ->where('status', 'processing')
                ->firstOrFail();

            // Recuperar información del proceso desde la session
            $processInfo = session('pending_analysis_' . $pendingId);
            if (!$processInfo) {
                throw new \Exception('Información del proceso no encontrada');
            }

            $serviceAccount = app(\App\Services\GoogleServiceAccount::class);
            $newMeetingName = $request->input('meeting_name');

            Log::info('Datos recibidos para completePendingMeeting', [
                'root_folder' => $request->input('root_folder'),
                'transcription_subfolder' => $request->input('transcription_subfolder'),
                'audio_subfolder' => $request->input('audio_subfolder'),
                'all_inputs' => $request->all()
            ]);

            // Determinar las carpetas de destino
            $rootFolder = \App\Models\Folder::where('google_id', $request->input('root_folder'))->first();

            if (!$rootFolder) {
                // Intentar buscar por id en lugar de google_id
                $rootFolder = \App\Models\Folder::where('id', $request->input('root_folder'))->first();

                if (!$rootFolder) {
                    // Obtener token del usuario para mostrar carpetas disponibles
                    $token = \App\Models\GoogleToken::where('username', $user->username)->first();
                    $availableFolders = [];
                    if ($token) {
                        $availableFolders = \App\Models\Folder::where('google_token_id', $token->id)
                            ->get(['id', 'google_id', 'name'])->toArray();
                    }

                    Log::error('No se encontró la carpeta raíz', [
                        'root_folder_value' => $request->input('root_folder'),
                        'available_folders' => $availableFolders
                    ]);
                    throw new \Exception('Carpeta raíz no encontrada: ' . $request->input('root_folder'));
                }
            }

            $transcriptionFolderId = $rootFolder->google_id;
            if ($request->input('transcription_subfolder')) {
                $sub = \App\Models\Subfolder::where('google_id', $request->input('transcription_subfolder'))
                    ->where('folder_id', $rootFolder->id)
                    ->first();
                if ($sub) {
                    $transcriptionFolderId = $sub->google_id;
                } else {
                    Log::warning('Subcarpeta de transcripción no encontrada', [
                        'transcription_subfolder' => $request->input('transcription_subfolder'),
                        'root_folder_id' => $rootFolder->id
                    ]);
                }
            }

            $audioFolderId = $rootFolder->google_id;
            if ($request->input('audio_subfolder')) {
                $sub = \App\Models\Subfolder::where('google_id', $request->input('audio_subfolder'))
                    ->where('folder_id', $rootFolder->id)
                    ->first();
                if ($sub) {
                    $audioFolderId = $sub->google_id;
                } else {
                    Log::warning('Subcarpeta de audio no encontrada', [
                        'audio_subfolder' => $request->input('audio_subfolder'),
                        'root_folder_id' => $rootFolder->id
                    ]);
                }
            }            // 1. Mover y renombrar el audio en Google Drive
            $oldFileId = $processInfo['drive_file_id'];
            $audioExtension = pathinfo($processInfo['original_name'], PATHINFO_EXTENSION);
            $newAudioName = $newMeetingName . '.' . $audioExtension;

            // Mover el archivo a la nueva ubicación con nuevo nombre
            $newAudioFileId = $serviceAccount->moveAndRenameFile(
                $oldFileId,
                $audioFolderId,
                $newAudioName
            );

            // 2. Crear y subir la transcripción
            $analysisResults = $request->input('analysis_results');
            $payload = [
                'summary' => $analysisResults['summary'] ?? null,
                'keyPoints' => $analysisResults['keyPoints'] ?? [],
                'segments' => $request->input('transcription_data'),
            ];
            $encrypted = \Illuminate\Support\Facades\Crypt::encryptString(json_encode($payload));

            $transcriptFileId = $serviceAccount->uploadFile(
                $newMeetingName . '.ju',
                'application/json',
                $transcriptionFolderId,
                $encrypted
            );

            // 3. Obtener URLs de descarga
            $audioUrl = $serviceAccount->getFileLink($newAudioFileId);
            $transcriptUrl = $serviceAccount->getFileLink($transcriptFileId);

            // 4. Guardar en la BD principal (TranscriptionLaravel)
            $transcription = \App\Models\TranscriptionLaravel::create([
                'username' => $user->username,
                'meeting_name' => $newMeetingName,
                'audio_drive_id' => $newAudioFileId,
                'audio_download_url' => $audioUrl,
                'transcript_drive_id' => $transcriptFileId,
                'transcript_download_url' => $transcriptUrl,
            ]);

            // 4b. Procesar y guardar tareas en la BD
            if (!empty($analysisResults['tasks']) && is_array($analysisResults['tasks'])) {
                foreach ($analysisResults['tasks'] as $rawTask) {
                    $parsed = $this->parseRawTaskForDb($rawTask);
                    TaskLaravel::create([
                        'username' => $user->username,
                        'meeting_id' => $transcription->id,
                        'tarea' => $parsed['tarea'],
                        'descripcion' => $parsed['descripcion'],
                        'fecha_inicio' => $parsed['fecha_inicio'],
                        'fecha_limite' => $parsed['fecha_limite'],
                        'progreso' => $parsed['progreso'],
                    ]);
                }
            }

            // 5. Marcar como exitoso y limpiar
            $pendingRecording->update(['status' => 'success']);

            // 6. Limpiar archivos temporales
            if (file_exists($processInfo['temp_file'])) {
                unlink($processInfo['temp_file']);
            }
            session()->forget('pending_analysis_' . $pendingId);

            // 7. Eliminar el registro de pending después de confirmar que todo salió bien
            $pendingRecording->delete();

            // Extraer datos adicionales para la respuesta
            $audioData = $processInfo['temp_file'] ?? null;
            $audioDuration = 0;
            $speakerCount = 0;
            $tasks = TaskLaravel::where('meeting_id', $transcription->id)
                ->where('username', $user->username)
                ->get();

            // Intentar obtener duración del audio si está disponible
            if ($audioData && file_exists($audioData)) {
                try {
                    // Aquí podrías agregar lógica para obtener la duración real del audio
                    // Por ahora usaremos un valor por defecto
                    $audioDuration = 300; // 5 minutos como ejemplo
                } catch (\Exception $e) {
                    // Si no se puede obtener, usar valor por defecto
                }
            }

            // Contar speakers únicos de la transcripción si está disponible
            $transcriptionData = $request->input('transcription_data');
            if ($transcriptionData && is_array($transcriptionData)) {
                $speakers = [];
                foreach ($transcriptionData as $segment) {
                    if (isset($segment['speaker']) && !in_array($segment['speaker'], $speakers)) {
                        $speakers[] = $segment['speaker'];
                    }
                }
                $speakerCount = count($speakers);
            }

            return response()->json([
                'success' => true,
                'message' => 'Reunión procesada y guardada exitosamente',
                'transcription_id' => $transcription->id,
                'drive_path' => $newMeetingName,
                'audio_duration' => $audioDuration,
                'speaker_count' => $speakerCount,
                'tasks' => $tasks,
                'audio_drive_id' => $newAudioFileId,
                'transcript_drive_id' => $transcriptFileId
            ]);

        } catch (\Exception $e) {
            Log::error('Error completing pending meeting', [
                'pending_id' => $request->input('pending_id'),
                'error' => $e->getMessage()
            ]);

            // En caso de error, mantener el estado processing para reintento
            return response()->json([
                'success' => false,
                'error' => 'Error al completar el procesamiento: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtiene información de un audio pendiente en procesamiento
     */
    public function getPendingProcessingInfo($id)
    {
        try {
            $user = Auth::user();

            $pendingRecording = \App\Models\PendingRecording::where('id', $id)
                ->where('username', $user->username)
                ->where('status', 'processing')
                ->firstOrFail();

            // Recuperar información del proceso
            $processInfo = session('pending_analysis_' . $id);
            if (!$processInfo) {
                return response()->json([
                    'success' => false,
                    'error' => 'Información del proceso no encontrada'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'pending_id' => $id,
                'original_name' => $processInfo['original_name'],
                'temp_file' => basename($processInfo['temp_file']),
                'status' => 'processing'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Error al obtener información: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Descarga el archivo temporal del audio pendiente para el frontend
     */
    public function getPendingAudioFile($tempFileName)
    {
        try {
            $user = Auth::user();
            $tempPath = storage_path('app/temp/' . $tempFileName);

            if (!file_exists($tempPath)) {
                return response()->json([
                    'success' => false,
                    'error' => 'Archivo temporal no encontrado'
                ], 404);
            }

            // Validar que el archivo pertenece al usuario actual
            if (!str_contains($tempFileName, 'pending_')) {
                return response()->json([
                    'success' => false,
                    'error' => 'Archivo no válido'
                ], 403);
            }

            // Leer el contenido del archivo
            $audioContent = file_get_contents($tempPath);

            if ($audioContent === false) {
                return response()->json([
                    'success' => false,
                    'error' => 'Error al leer el archivo'
                ], 500);
            }

            // Convertir a base64
            $audioBase64 = base64_encode($audioContent);

            return response()->json([
                'success' => true,
                'audioData' => $audioBase64,
                'mimeType' => 'audio/mpeg'
            ]);

        } catch (\Exception $e) {
            Log::error('Error getting pending audio file', [
                'temp_file' => $tempFileName,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => 'Error al obtener archivo: ' . $e->getMessage()
            ], 500);
        }
    }

    private function formatBytes($bytes, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');

        for ($i = 0; $bytes > 1024; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision) . ' ' . $units[$i];
    }

    private function updateDriveFileName($fileId, $newName)
    {
        return $this->googleDriveService->updateFileName($fileId, $newName);
    }

    private function normalizeDriveUrl(string $url): string
    {
        if (preg_match('/https:\/\/drive\.google\.com\/file\/d\/([^\/]+)\/view/', $url, $matches)) {
            return 'https://drive.google.com/uc?export=download&id=' . $matches[1];
        }
        return $url;
    }

    /**
     * Genera y descarga un PDF con los datos seleccionados de la reunión
     */
    public function downloadPdf(Request $request, $id)
    {
        try {
            $user = Auth::user();

            // Validar que la reunión pertenece al usuario
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Validar request
            $request->validate([
                'meeting_name' => 'required|string',
                'sections' => 'required|array',
                'data' => 'required|array'
            ]);

            $data = $request->input('data');
            $sections = $request->input('sections');
            $meetingName = $request->input('meeting_name');

            // Usar la fecha real de created_at de la base de datos
            $realCreatedAt = $meeting->created_at;

            // Verificar si la reunión pertenece a una organización
            $hasOrganization = $meeting->containers()->exists();
            $organizationName = null;
            $organizationLogo = null;
            if ($hasOrganization) {
                $container = $meeting->containers()->first();
                if ($container && isset($container->organization)) {
                    $organizationName = $container->organization->name ?? 'Organización';
                    $organizationLogo = $container->organization->imagen ?? null;
                }
            }

            // Crear el HTML para el PDF
            // Si se solicitó la sección de tareas, usar siempre las tareas de la BD
            if (in_array('tasks', $sections)) {
                $dbTasks = TaskLaravel::where('meeting_id', $meeting->id)
                    ->where('username', $user->username)
                    ->get(['tarea', 'descripcion', 'fecha_inicio', 'fecha_limite', 'progreso', 'username']);
                $mapped = $dbTasks->map(function($t) {
                    $start = $t->fecha_inicio ? ($t->fecha_inicio instanceof \Carbon\Carbon ? $t->fecha_inicio->format('Y-m-d') : (string)$t->fecha_inicio) : 'Sin asignar';
                    $end = $t->fecha_limite ? ($t->fecha_limite instanceof \Carbon\Carbon ? $t->fecha_limite->format('Y-m-d') : (string)$t->fecha_limite) : 'Sin asignar';
                    $progress = isset($t->progreso) && is_numeric($t->progreso)
                        ? (intval($t->progreso) . '%')
                        : '0%';
                    return [
                        'title' => $t->tarea ?? 'Sin nombre',
                        'description' => $t->descripcion ?? '',
                        'assigned' => $t->username ?? 'Sin asignar',
                        'start' => $start,
                        'end' => $end,
                        'progress' => $progress,
                    ];
                })->toArray();
                $data['tasks'] = $mapped;
            }

            // Crear el HTML para el PDF
            $html = $this->generatePdfHtml(
                $meetingName,
                $realCreatedAt,
                $sections,
                $data,
                $hasOrganization,
                $organizationName,
                $organizationLogo
            );

            // Generar PDF usando DomPDF
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);

            // Dibujar paginación centrada en el pie
            $domPdf = $pdf->getDomPDF();
            $canvas = $domPdf->get_canvas();
            $fontMetrics = $domPdf->getFontMetrics();
            $font = $fontMetrics ? $fontMetrics->getFont('Helvetica', 'normal') : null;
            $size = 10;
            $text = 'Página {PAGE_NUM} de {PAGE_COUNT}';
            $width = $canvas->get_width();
            $textWidth = $fontMetrics && $font ? $fontMetrics->getTextWidth($text, $font, $size) : 140;
            $x = ($width - $textWidth) / 2;
            $y = $canvas->get_height() - 28; // dentro del footer (40px alto)
            $canvas->page_text($x, $y, $text, $font, $size, [0.26, 0.26, 0.26]);

            $pdf->setPaper('letter', 'portrait');

            // Nombre del archivo
            $fileName = preg_replace('/[^\w\s]/', '', $meetingName) . '_' . date('Y-m-d') . '.pdf';

            return $pdf->download($fileName);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Genera un PDF con los datos seleccionados y lo muestra en el navegador (vista previa)
     */
    public function previewPdf(Request $request, $id)
    {
        try {
            $user = Auth::user();

            // Validar que la reunión pertenece al usuario
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Validar request
            $request->validate([
                'meeting_name' => 'required|string',
                'sections' => 'required|array',
                'data' => 'required|array'
            ]);

            $data = $request->input('data');
            $sections = $request->input('sections');
            $meetingName = $request->input('meeting_name');

            // Usar la fecha real de created_at de la base de datos
            $realCreatedAt = $meeting->created_at;

            // Verificar si la reunión pertenece a una organización
            $hasOrganization = $meeting->containers()->exists();
            $organizationName = null;
            $organizationLogo = null;
            if ($hasOrganization) {
                $container = $meeting->containers()->first();
                if ($container && isset($container->organization)) {
                    $organizationName = $container->organization->name ?? 'Organización';
                    $organizationLogo = $container->organization->imagen ?? null;
                }
            }

            // Crear el HTML para el PDF
            $html = $this->generatePdfHtml(
                $meetingName,
                $realCreatedAt,
                $sections,
                $data,
                $hasOrganization,
                $organizationName,
                $organizationLogo
            );

            // Generar PDF usando DomPDF
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('letter', 'portrait');

            // Dibujar paginación centrada también en la vista previa
            $domPdf = $pdf->getDomPDF();
            $canvas = $domPdf->get_canvas();
            $fontMetrics = $domPdf->getFontMetrics();
            $font = $fontMetrics ? $fontMetrics->getFont('Helvetica', 'normal') : null;
            $size = 10;
            $text = 'Página {PAGE_NUM} de {PAGE_COUNT}';
            $width = $canvas->get_width();
            $textWidth = $fontMetrics && $font ? $fontMetrics->getTextWidth($text, $font, $size) : 140;
            $x = ($width - $textWidth) / 2;
            $y = $canvas->get_height() - 28;
            $canvas->page_text($x, $y, $text, $font, $size, [0.26, 0.26, 0.26]);

            // Forzar vista inline
            return $pdf->stream('preview.pdf', [
                'Attachment' => false
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al generar vista previa: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Parsea una tarea a partir de partes posicionales ya separadas y limpias.
     * Heurística:
     * - Detecta fechas (YYYY-MM-DD). Si hay 2, asume [inicio, fin]. Si hay 1, es inicio.
     * - El asignado se toma preferentemente del token previo a la última fecha si luce como nombre; si no, del token siguiente.
     * - name = id (primer token) + título (tokens hasta antes de asignado/fecha)
     * - description = tokens tras la última fecha; si no hay fecha, lo que queda tras el título.
     * - progress = último token que parezca porcentaje (e.g., 50%) si existe.
     */
    private function parseTaskFromParts(array $parts, array $result): array
    {
        // Limpieza básica
        $parts = array_values(array_map(function($p){ return trim((string)$p); }, array_filter($parts, function($p){ return $p !== null && trim((string)$p) !== ''; })));
        // Expandir elementos que contienen comas o saltos de línea (caso de arrays numéricos con tokens embebidos)
        $expanded = [];
        foreach ($parts as $p) {
            $p = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $p);
            $sub = array_map('trim', array_filter(explode(',', $p), function($x){ return $x !== ''; }));
            if (empty($sub)) {
                $expanded[] = $p;
            } else {
                foreach ($sub as $s) { $expanded[] = $s; }
            }
        }
        $parts = $expanded;
    $n = count($parts);
        if ($n === 0) { return $result; }

        // Caso especial: un solo token con separador ':' o '-' que contenga nombre y descripción
        if ($n === 1) {
            $single = $parts[0];
            if (preg_match('/^\s*([^:\-–]+?)\s*[:\-–]\s*(.+)$/u', $single, $m)) {
                $idToken = trim($m[1]);
                $desc = trim($m[2]);
                $result['name'] = $idToken !== '' ? rtrim($idToken, ",;:") : 'Sin nombre';
                $result['description'] = $desc;
                return $result;
            }
        }

        // Normalizar tokens para detección (remover puntuación final común)
        $norm = array_map(function($p){ return rtrim($p, " ,;:."); }, $parts);

        // Detectar porcentaje (progreso) al final si existe
        $progressIdx = -1;
        for ($i = $n - 1; $i >= 0; $i--) {
            if (preg_match('/^(100|[0-9]{1,2})%[.,]?$/', $norm[$i])) { $progressIdx = $i; break; }
        }
        if ($progressIdx >= 0) {
            $result['progress'] = rtrim($parts[$progressIdx], " ,;:.");
            array_splice($parts, $progressIdx, 1);
            array_splice($norm, $progressIdx, 1);
            $n = count($parts);
        }

        // Detectar fechas YYYY-MM-DD
        $dateIdxs = [];
        for ($i = 0; $i < $n; $i++) {
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $norm[$i])) { $dateIdxs[] = $i; }
        }

        $startIdx = -1; $endIdx = -1; $lastDateIdx = -1;
        if (count($dateIdxs) >= 2) {
            $startIdx = $dateIdxs[0];
            $endIdx = $dateIdxs[1];
            $result['start'] = $parts[$startIdx];
            $result['end'] = $parts[$endIdx];
            $lastDateIdx = max($dateIdxs);
        } elseif (count($dateIdxs) === 1) {
            $startIdx = $dateIdxs[0];
            $result['start'] = $parts[$startIdx];
            $lastDateIdx = $startIdx;
        }

        // Heurística mejorada para asignado: token adyacente a la última fecha
        $looksLikeName = function($s) {
            $s = trim($s);
            if ($s === '') return false;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', rtrim($s, " ,;:."))) return false; // fecha
            if (preg_match('/^(task[_-]?\d+|tarea[_-]?\d+)$/i', $s)) return false; // id típico
            if (preg_match('/^(100|[0-9]{1,2})%$/', $s)) return false; // porcentaje
            
            // Patrones que sugieren que es un nombre de persona (mejorado)
            $personPatterns = [
                '/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+\s+[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$/', // Nombre Apellido
                '/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+\s+[A-ZÁÉÍÓÚÑ]\.$/', // Nombre A.
                '/^[A-Z]\.\s*[A-ZÁÉÍÓÚÑ][a-záéíóúñ]+$/', // A. Apellido
            ];
            
            foreach ($personPatterns as $pattern) {
                if (preg_match($pattern, $s)) {
                    return true;
                }
            }
            
            // Patrones que sugieren que NO es un nombre (es descripción de tarea)
            $taskPatterns = [
                '/\b(revisar|analizar|preparar|coordinar|planificar|desarrollar|implementar|ejecutar|completar)\b/i',
                '/\b(documento|archivo|reporte|presentación|presupuesto|proyecto|evento|reunión)\b/i',
                '/\b(todos|todas|con|para|de|del|la|el|los|las)\b/i',
            ];
            
            foreach ($taskPatterns as $pattern) {
                if (preg_match($pattern, $s)) {
                    return false;
                }
            }
            
            // Si tiene al menos una letra y posiblemente sea un nombre simple
            if (preg_match('/^[A-ZÁÉÍÓÚÑ][a-záéíóúñ]{2,}$/u', $s)) {
                return true;
            }
            
            return false;
        };

        $assignedIdx = -1;
        if ($lastDateIdx >= 0) {
            // Priorizar tokens DESPUÉS de la fecha (más probable que sean nombres de personas)
            if ($lastDateIdx + 1 < $n && $looksLikeName($parts[$lastDateIdx + 1])) {
                $assignedIdx = $lastDateIdx + 1;
            } elseif ($lastDateIdx - 1 >= 0 && $looksLikeName($parts[$lastDateIdx - 1])) {
                $assignedIdx = $lastDateIdx - 1;
            }
        }
        if ($assignedIdx >= 0) { $result['assigned'] = $parts[$assignedIdx]; }

        // Si no hay fechas ni asignado detectado, usar mejor heurística para separar nombre y descripción
        if ($lastDateIdx < 0 && $assignedIdx < 0 && $n > 1) {
            $idToken = $parts[0] ?? null;
            $baseId = $idToken !== null ? rtrim(trim($idToken), ",;:") : '';
            $result['name'] = $baseId !== '' ? $baseId : 'Sin nombre';
            
            // Buscar si alguno de los tokens restantes parece ser un nombre de persona
            $foundPersonIdx = -1;
            for ($i = 1; $i < $n; $i++) {
                if ($looksLikeName($parts[$i])) {
                    $foundPersonIdx = $i;
                    $result['assigned'] = $parts[$i];
                    break;
                }
            }
            
            // Construir descripción excluyendo el nombre de la persona si se encontró
            $descTokens = [];
            for ($i = 1; $i < $n; $i++) {
                if ($i !== $foundPersonIdx) {
                    $descTokens[] = $parts[$i];
                }
            }
            $result['description'] = trim(implode(', ', $descTokens));
            return $result;
        }

        // Construir name (id + título)
        $idToken = $parts[0] ?? null;
        $titleStart = $idToken !== null ? 1 : 0;
        // Fin del título antes de assigned o de fecha
        $limitIdx = $n - 1;
        if ($assignedIdx >= 0) { $limitIdx = min($limitIdx, $assignedIdx - 1); }
        if ($lastDateIdx >= 0) { $limitIdx = min($limitIdx, $lastDateIdx - 1); }
        $titleTokens = [];
        if ($limitIdx >= $titleStart) {
            for ($i = $titleStart; $i <= $limitIdx; $i++) { $titleTokens[] = $parts[$i]; }
        }
        $title = trim(implode(', ', $titleTokens));
        $baseId = $idToken !== null ? rtrim(trim($idToken), ",;:") : '';
        if ($baseId !== '' && preg_match('/^(task|tarea)[_-]?\d+$/i', $baseId)) {
            // Si luce como id de tarea (task_1), usar solo el id como nombre
            $name = $baseId;
        } else if ($title !== '') {
            $name = trim(($baseId !== '' ? $baseId . ', ' : '') . $title);
        } else {
            $name = $baseId !== '' ? $baseId : 'Sin nombre';
        }
        $result['name'] = $name;
        // Descripción: tomar todo lo que no sea id, ni asignado, ni fechas, para conservar texto antes y después de la fecha
        $descTokens = [];
        $dateIdxSet = array_flip($dateIdxs);
        for ($i = 1; $i < $n; $i++) {
            if ($i === $assignedIdx) continue;
            if (isset($dateIdxSet[$i])) continue;
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', rtrim($parts[$i], " ,;:."))) continue;
            $low = mb_strtolower(rtrim($parts[$i], " ,;:.") , 'UTF-8');
            if (in_array($low, ['no asignado','sin asignar'], true)) continue;
            $descTokens[] = $parts[$i];
        }
        $desc = trim(implode(', ', $descTokens));
        $result['description'] = $desc;

        return $result;
    }

    /**
     * Genera el HTML para el PDF con el nuevo diseño solicitado
     */
    private function generatePdfHtml($meetingName, $realCreatedAt, $sections, $data, $hasOrganization = false, $organizationName = null, $organizationLogo = null)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . htmlspecialchars($meetingName) . '</title>
            <style>
                /* Forzar márgenes internos visibles: usar padding en body y margin 0 en la página */
                @page {
                    size: letter;
                    margin: 0;
                }
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: Arial, sans-serif;
                    font-size: 12px;
                    line-height: 1.5;
                    color: #333;
                    margin: 0;
                    /* Reservar espacio para header fijo + márgenes de 2cm */
                    padding: 20mm; /* 2 cm laterales */
                    padding-top: calc(20mm + 90px);
                    padding-bottom: calc(20mm + 40px); /* reservar para el footer fijo */
                    background: white;
                }

                /* Header (fijo) */
                .header {
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    /* Dompdf ignora a veces los gradients; proveer color sólido de respaldo */
                    background-color: #1d4ed8; /* fallback sólido */
                    background-image: linear-gradient(90deg, #2563eb 0%, #1e3a8a 100%);
                    color: #ffffff !important;
                    height: 90px; /* asegurar altura visible del header fijo */
                }
                .header-topbar {
                    display: table;
                    width: 100%;
                    padding: 14px 22px 8px 22px;
                }
                .header-topbar .brand,
                .header-topbar .generated {
                    display: table-cell;
                    vertical-align: middle;
                    color: #ffffff !important;
                }
                .header-topbar .brand { font-size: 22px; font-weight: 700; letter-spacing: 1px; }
                .header-topbar .generated { text-align: right; font-size: 12px; opacity: 0.95; }
                .meeting-details {
                    text-align: center;
                    padding: 6px 22px 14px 22px;
                    color: #ffffff !important;
                }
                .meeting-details .meeting-name { font-size: 18px; font-weight: 700; margin-bottom: 2px; }
                .meeting-details .meeting-subtitle { font-size: 12px; opacity: 0.95; }

                /* Contenido principal */
                .main-content {
                    padding: 0;
                    margin: 0;
                }

                /* Encabezado del reporte (en el cuerpo) */
                .report-heading { margin: 0 0 18px 0; }
                .report-heading .top-line { height: 6px; width: 100%; background: #3b82f6; border-radius: 2px; margin-bottom: 10px; }
                .report-heading .display-title { font-size: 28px; line-height: 1.15; font-weight: 800; color: #2563eb; margin: 0 0 8px 0; }
                .report-heading .underline { height: 3px; width: 35%; max-width: 380px; background: #60a5fa; border-radius: 2px; margin: 0 0 12px 0; }
                .report-heading .meta { color: #555; font-size: 14px; }
                .report-heading .meta div { margin-bottom: 4px; }

                /* Secciones */
                .section {
                    margin-bottom: 25px;
                    page-break-inside: avoid;
                }
                .section-title {
                    font-size: 16px;
                    font-weight: bold;
                    color: #1d4ed8;
                    margin-bottom: 15px;
                    border-bottom: 2px solid #3b82f6;
                    padding-bottom: 5px;
                }
                .section-content {
                    padding: 10px 0;
                }

                /* Resumen */
                .summary-text {
                    text-align: justify;
                    line-height: 1.6;
                    color: #555;
                }

                /* Transcripción */
                .transcription-item {
                    margin-bottom: 15px;
                    padding: 10px;
                    background: #f9f9f9;
                    border-left: 4px solid #3b82f6;
                    border-radius: 4px;
                }
                .speaker-name {
                    font-weight: bold;
                    color: #1d4ed8;
                    margin-bottom: 5px;
                }
                .speaker-text {
                    color: #555;
                    line-height: 1.5;
                }

                /* Puntos Clave */
                .key-points-list {
                    list-style: none;
                }
                .key-point {
                    margin-bottom: 10px;
                    padding: 10px 15px;
                    background: #eff6ff;
                    border-left: 4px solid #3b82f6;
                    border-radius: 4px;
                    position: relative;
                    padding-left: 40px;
                }
                .key-point::before {
                    content: "●";
                    position: absolute;
                    left: 15px;
                    color: #1d4ed8;
                    font-weight: bold;
                }

                /* Tabla de Tareas */
                .tasks-table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 10px;
                }
                /* Dompdf no soporta bien gradients: usar color sólido para que el texto blanco sea visible */
                .tasks-table th {
                    background-color: #1d4ed8; /* azul sólido */
                    color: #ffffff;
                    padding: 12px 8px;
                    font-size: 11px;
                    font-weight: bold;
                    text-align: center;
                    border: 1px solid #1d4ed8;
                }
                .tasks-table td {
                    padding: 10px 8px;
                    border: 1px solid #ddd;
                    font-size: 10px;
                    text-align: center;
                    vertical-align: top;
                    color: #111111; /* asegurar contraste */
                }
                .tasks-table tr:nth-child(even) {
                    background: #dbeafe;
                }
                .task-name {
                    font-weight: bold;
                    color: #1d4ed8;
                }
                .task-description {
                    text-align: left !important;
                    max-width: 200px;
                }

                /* Footer */
                /* Footer fijo en todas las páginas */
                .footer { position: fixed; left: 0; right: 0; bottom: 0; height: 40px; background: #fff; border-top: 1px solid #ddd; display: table; width: 100%; font-size: 11px; color: #444; }
                .footer .cell { display: table-cell; vertical-align: middle; padding: 10px 20mm; }
                .footer .left { text-align: left; }
                .footer .center { text-align: center; }
                .footer .right { text-align: right; }
            </style>
        </head>
        <body>
            <!-- Header -->
            <div class="header">
                <div class="header-topbar">
                    <div class="brand">JUNTIFY</div>
                    <div class="generated">Generado el: ' . date('d/m/Y') . '</div>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="main-content">
                <!-- Encabezado visual del reporte (en el cuerpo) -->
                <div class="report-heading">
                    <div class="top-line"></div>
                    <div class="display-title">' . htmlspecialchars($meetingName) . '</div>
                    <div class="underline"></div>
                    <div class="meta">
                        <div>Fecha: ' . $realCreatedAt->copy()->locale('es')->isoFormat('D [de] MMMM YYYY') . '</div>
                        <div>Hora: ' . $realCreatedAt->copy()->locale('es')->isoFormat('HH:mm') . '</div>
                    </div>
                </div>';

    // Resumen (solo si el usuario la seleccionó y hay contenido)
    if (in_array('summary', $sections) && !empty($data['summary'])) {
            $summaryText = is_string($data['summary']) ? $data['summary'] : (is_array($data['summary']) ? implode(' ', $data['summary']) : strval($data['summary']));
            $html .= '
            <div class="section">
                <div class="section-title">Resumen</div>
                <div class="section-content">
                    <div class="summary-text">' . nl2br(htmlspecialchars($summaryText)) . '</div>
                </div>
            </div>';
        }

    // Transcripción (solo si el usuario la seleccionó y existe texto o segmentos)
    $hasTranscriptionData = !empty($data['transcription']) || (is_array($data['segments'] ?? null) && !empty($data['segments']));
    if (in_array('transcription', $sections) && $hasTranscriptionData) {
            $html .= '
            <div class="section">
                <div class="section-title">Transcripción</div>
                <div class="section-content">';

            if (is_array($data['segments']) && !empty($data['segments'])) {
                foreach ($data['segments'] as $segment) {
                    $speaker = isset($segment['speaker']) ? (is_string($segment['speaker']) ? htmlspecialchars($segment['speaker']) : 'Participante') : 'Participante';
                    $text = isset($segment['text']) ? (is_string($segment['text']) ? htmlspecialchars($segment['text']) : '') : '';
                    $html .= '
                    <div class="transcription-item">
                        <div class="speaker-name">' . $speaker . ':</div>
                        <div class="speaker-text">' . $text . '</div>
                    </div>';
                }
            } else {
                $transcriptionText = is_string($data['transcription']) ? $data['transcription'] : (is_array($data['transcription']) ? implode(' ', $data['transcription']) : strval($data['transcription']));
                $html .= '
                <div class="transcription-item">
                    <div class="speaker-name">Participante:</div>
                    <div class="speaker-text">' . nl2br(htmlspecialchars($transcriptionText)) . '</div>
                </div>';
            }

            $html .= '</div></div>';
        }

    // Puntos Clave (solo si el usuario los seleccionó y hay contenido)
    if (in_array('key_points', $sections) && !empty($data['key_points'])) {
            $html .= '
            <div class="section">
                <div class="section-title">Puntos Clave</div>
                <div class="section-content">
                    <ul class="key-points-list">';

            if (is_array($data['key_points'])) {
                foreach ($data['key_points'] as $point) {
                    $pointText = is_string($point) ? $point : (is_array($point) ? implode(', ', $point) : strval($point));
                    $html .= '<li class="key-point">' . htmlspecialchars($pointText) . '</li>';
                }
            } else {
                $keyPointsText = is_string($data['key_points']) ? $data['key_points'] : strval($data['key_points']);
                $html .= '<li class="key-point">' . nl2br(htmlspecialchars($keyPointsText)) . '</li>';
            }

            $html .= '</ul></div></div>';
        }

    // Tareas (solo si el usuario las seleccionó y hay contenido)
    if (in_array('tasks', $sections) && !empty($data['tasks'])) {
            $html .= '
            <div class="section">
                <div class="section-title">Tareas</div>
                <div class="section-content">
                    <table class="tasks-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Nombre de la Tarea</th>
                                <th style="width: 35%;">Descripción</th>
                                <th style="width: 12%;">Fecha Inicio</th>
                                <th style="width: 12%;">Fecha Fin</th>
                                <th style="width: 15%;">Asignado a</th>
                                <th style="width: 11%;">Progreso</th>
                            </tr>
                        </thead>
                        <tbody>';

            // Función de parseo flexible para cadenas de tareas
            $parseTask = function($raw) {
                $result = [
                    'name' => 'Sin nombre',
                    'description' => '',
                    'assigned' => 'Sin asignar',
                    'start' => 'Sin asignar',
                    'end' => 'Sin asignar',
                    'progress' => '0%'
                ];

                if (is_array($raw)) {
                    // ¿Es asociativo o numérico?
                    $isAssoc = array_keys($raw) !== range(0, count($raw) - 1);
                    if ($isAssoc) {
                        // Intentar mapear campos por nombre si existen
                        $id = $raw['id'] ?? $raw['name'] ?? $raw['title'] ?? null;
                        $title = $raw['title'] ?? $raw['name'] ?? null;
                        $desc = $raw['description'] ?? $raw['desc'] ?? '';
                        $assigned = $raw['assigned'] ?? $raw['assigned_to'] ?? $raw['owner'] ?? 'Sin asignar';
                        $start = $raw['start'] ?? $raw['start_date'] ?? $raw['fecha_inicio'] ?? 'Sin asignar';
                        $end = $raw['end'] ?? $raw['due'] ?? $raw['due_date'] ?? $raw['fecha_fin'] ?? 'Sin asignar';
                        $progress = isset($raw['progress']) ? (is_numeric($raw['progress']) ? ($raw['progress'] . '%') : $raw['progress']) : '0%';

                        // Si no hay título pero el id contiene más tokens (coma/saltos/':'), intentar parsear desde id
                        $parsedFromId = null;
                        if ($title === null || trim((string)$title) === '') {
                            $idText = is_string($id) ? $id : '';
                            if ($idText !== '') {
                                $norm = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $idText);
                                $idParts = array_map('trim', array_filter(explode(',', $norm), function($p){ return $p !== ''; }));
                                if (!empty($idParts)) {
                                    $parsedFromId = $this->parseTaskFromParts($idParts, $result);
                                }
                            }
                        }

                        if ($parsedFromId) {
                            $name = $parsedFromId['name'] ?? ($id ?? 'Sin nombre');
                            $descFromId = $parsedFromId['description'] ?? '';
                            $finalDesc = is_string($desc) && trim($desc) !== ''
                                ? $desc
                                : $descFromId;
                        } else {
                            // Construir nombre evitando coma extra si no hay título
                            $baseId = $id !== null ? rtrim(trim((string)$id), ",;:") : '';
                            if ($title !== null && trim((string)$title) !== '') {
                                $name = trim(($baseId !== '' ? $baseId . ', ' : '') . $title);
                            } else {
                                $name = $baseId !== '' ? $baseId : 'Sin nombre';
                            }
                            $finalDesc = is_string($desc) ? $desc : (is_array($desc) ? implode(', ', $desc) : strval($desc));
                        }

                        $result['name'] = $name;
                        $result['description'] = $finalDesc;
                        $result['assigned'] = $assigned ?: 'Sin asignar';
                        $result['start'] = $start ?: 'Sin asignar';
                        $result['end'] = $end ?: 'Sin asignar';
                        $result['progress'] = $progress ?: '0%';

                        // Limpieza adicional de la descripción para quitar fechas/asignados que vengan embebidos
                        if (is_string($result['description']) && trim($result['description']) !== '') {
                            $descText = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', (string)$result['description']);
                            $descParts = array_map('trim', array_filter(explode(',', $descText), function($p){ return $p !== ''; }));
                            if (!empty($descParts)) {
                                // Prepend el nombre para que el parser pueda delimitar correctamente
                                $aux = $this->parseTaskFromParts(array_merge([$name], $descParts), [
                                    'name' => $name,
                                    'description' => '',
                                    'assigned' => 'Sin asignar',
                                    'start' => 'Sin asignar',
                                    'end' => $result['end'] ?? 'Sin asignar',
                                    'progress' => $result['progress'] ?? '0%'
                                ]);
                                if (!empty($aux['description'])) {
                                    $result['description'] = $aux['description'];
                                }
                                if (($result['assigned'] === 'Sin asignar' || empty($result['assigned'])) && !empty($aux['assigned']) && $aux['assigned'] !== 'Sin asignar') {
                                    $result['assigned'] = $aux['assigned'];
                                }
                                if (($result['start'] === 'Sin asignar' || empty($result['start'])) && !empty($aux['start']) && $aux['start'] !== 'Sin asignar') {
                                    $result['start'] = $aux['start'];
                                }
                            }
                        }
                        return $result;
                    } else {
                        // Lista posicional: [id, titulo..., asignado, fecha, descripcion...]
                        $parts = array_values(array_map('trim', array_filter($raw, function($p){ return $p !== null && $p !== ''; })));
                        return $this->parseTaskFromParts($parts, $result);
                    }
                }

                $text = is_string($raw) ? $raw : strval($raw);
                // Normalizar saltos de línea a comas para soportar formatos en múltiples líneas
                $text = str_replace(["\r\n", "\n", "\r", "\t", "|"], ',', $text);
                // Separar por comas, limpiar espacios
                $parts = array_map('trim', array_filter(explode(',', $text), function($p){ return $p !== ''; }));
                if (empty($parts)) {
                    $result['description'] = $text;
                    return $result;
                }
                return $this->parseTaskFromParts($parts, $result);
            };

            // Limpieza final de descripción para eliminar asignado/fechas residuales y normalizar comas
            $cleanDesc = function($desc) {
                if (!is_string($desc) || trim($desc) === '') return $desc;
                $s = ' ' . $desc . ' ';
                // Quitar "No asignado" / "Sin asignar" con coma/punto opcional alrededor
                $s = preg_replace('/[,\s]+(no asignado|sin asignar)[\s,\.]+/iu', ', ', $s);
                // Quitar fechas sueltas con coma/punto opcional
                $s = preg_replace('/[,\s]+\d{4}-\d{2}-\d{2}[\s,\.]+/', ', ', $s);
                // Quitar patrón "Nombre, YYYY-MM-DD," (nombre = cualquier cosa sin coma hasta 80 chars)
                $s = preg_replace('/[,\s]+[^,]{1,80}?,\s*\d{4}-\d{2}-\d{2}[\s,\.]+/u', ', ', $s);
                // Normalizar comas consecutivas y espacios
                $s = preg_replace('/\s*,\s*,+/', ', ', $s);
                $s = preg_replace('/\s{2,}/', ' ', $s);
                $s = preg_replace('/\s*,\s*$/', '', trim($s));
                $s = preg_replace('/^,\s*/', '', $s);
                return trim($s);
            };

            if (is_array($data['tasks'])) {
                foreach ($data['tasks'] as $task) {
                    $t = $parseTask($task);
                    // Sanitizar nombre y completar descripción si faltara
                    $t['name'] = rtrim(trim((string)$t['name']), ",;:");
                    if ((string)$t['description'] === '' && is_array($task)) {
                        $assoc = array_keys($task) !== range(0, count($task) - 1);
                        if ($assoc) {
                            $ignoreKeys = ['id','name','title','description','desc','assigned','assigned_to','owner','start','start_date','fecha_inicio','end','due','due_date','fecha_fin','progress'];
                            $extra = [];
                            foreach ($task as $k => $v) {
                                if (!in_array($k, $ignoreKeys, true) && is_string($v) && trim($v) !== '') {
                                    $extra[] = trim($v);
                                }
                            }
                            if (!empty($extra)) {
                                $t['description'] = implode(', ', $extra);
                            }
                        }
                    }
                    // Extraer "Nombre, YYYY-MM-DD," de la descripción si aún no se asignó
                    if (is_string($t['description']) && trim($t['description']) !== '') {
                        $descTmp = ' ' . $t['description'] . ' ';
                        // 1) Nombre, fecha
                        if (($t['assigned'] === 'Sin asignar' || $t['assigned'] === '' || $t['assigned'] === null) &&
                            preg_match('/,\s*([^,\n]{2,80}?)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/u', $descTmp, $m)) {
                            $candidate = trim($m[1]);
                            if (mb_strtolower($candidate, 'UTF-8') !== 'no asignado' && mb_strtolower($candidate, 'UTF-8') !== 'sin asignar') {
                                $t['assigned'] = $candidate;
                            }
                            if ($t['start'] === 'Sin asignar' || empty($t['start'])) {
                                $t['start'] = $m[2];
                            }
                            $descTmp = preg_replace('/,\s*' . preg_quote($m[1], '/') . '\s*,\s*' . preg_quote($m[2], '/') . '\s*,/u', ', ', $descTmp, 1);
                        } else {
                            // 2) (No asignado|Sin asignar), fecha -> solo fecha
                            if (($t['start'] === 'Sin asignar' || empty($t['start'])) &&
                                preg_match('/,\s*(no asignado|sin asignar)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/iu', $descTmp, $m2)) {
                                $t['start'] = $m2[2];
                                $descTmp = preg_replace('/,\s*' . $m2[1] . '\s*,\s*' . preg_quote($m2[2], '/') . '\s*,/iu', ', ', $descTmp, 1);
                            }
                        }
                        $t['description'] = trim($descTmp);
                    }

                    // Limpieza final de descripción
                    $t['description'] = $cleanDesc($t['description']);
                    $html .= '\n                            <tr>\n                                <td class="task-name">' . htmlspecialchars($t['name']) . '</td>\n                                <td class="task-description">' . htmlspecialchars($t['description']) . '</td>\n                                <td>' . htmlspecialchars($t['start']) . '</td>\n                                <td>' . htmlspecialchars($t['end']) . '</td>\n                                <td>' . htmlspecialchars($t['assigned']) . '</td>\n                                <td>' . htmlspecialchars($t['progress']) . '</td>\n                            </tr>';
                }
            } else {
                $t = $parseTask($data['tasks']);
                $t['name'] = rtrim(trim((string)$t['name']), ",;:");
                if (is_string($t['description']) && trim($t['description']) !== '') {
                    $descTmp = ' ' . $t['description'] . ' ';
                    if (($t['assigned'] === 'Sin asignar' || $t['assigned'] === '' || $t['assigned'] === null) &&
                        preg_match('/,\s*([^,\n]{2,80}?)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/u', $descTmp, $m)) {
                        $candidate = trim($m[1]);
                        if (mb_strtolower($candidate, 'UTF-8') !== 'no asignado' && mb_strtolower($candidate, 'UTF-8') !== 'sin asignar') {
                            $t['assigned'] = $candidate;
                        }
                        if ($t['start'] === 'Sin asignar' || empty($t['start'])) {
                            $t['start'] = $m[2];
                        }
                        $descTmp = preg_replace('/,\s*' . preg_quote($m[1], '/') . '\s*,\s*' . preg_quote($m[2], '/') . '\s*,/u', ', ', $descTmp, 1);
                    } else if (($t['start'] === 'Sin asignar' || empty($t['start'])) &&
                        preg_match('/,\s*(no asignado|sin asignar)\s*,\s*(\d{4}-\d{2}-\d{2})\s*,/iu', $descTmp, $m2)) {
                        $t['start'] = $m2[2];
                        $descTmp = preg_replace('/,\s*' . $m2[1] . '\s*,\s*' . preg_quote($m2[2], '/') . '\s*,/iu', ', ', $descTmp, 1);
                    }
                    $t['description'] = trim($descTmp);
                }
                $t['description'] = $cleanDesc($t['description']);
                $html .= '\n                            <tr>\n                                <td class="task-name">' . htmlspecialchars($t['name']) . '</td>\n                                <td class="task-description">' . htmlspecialchars($t['description']) . '</td>\n                                <td>' . htmlspecialchars($t['start']) . '</td>\n                                <td>' . htmlspecialchars($t['end']) . '</td>\n                                <td>' . htmlspecialchars($t['assigned']) . '</td>\n                                <td>' . htmlspecialchars($t['progress']) . '</td>\n                            </tr>';
            }

            $html .= '
                        </tbody>
                    </table>
                </div>
            </div>';
        }

        $html .= '
            </div> <!-- Fin main-content -->

            <!-- Footer -->
            <div class="footer">
                <div class="cell left">Juntify - Gestión de Reuniones</div>
                <div class="cell center">&nbsp;</div>
                <div class="cell right">Documento confidencial</div>
            </div>
        </body>
        </html>';

        return $html;
    }
}
