<?php

namespace App\Http\Controllers;

use App\Models\TranscriptionLaravel;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\MeetingShare;
use App\Models\MeetingContentContainer;
use App\Models\Task;
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

class MeetingController extends Controller
{
    use GoogleDriveHelpers;

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

            $meetings = TranscriptionLaravel::where('username', $user->username)
                ->whereDoesntHave('containers')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) {
                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                        'audio_drive_id' => $meeting->audio_drive_id,
                        'transcript_drive_id' => $meeting->transcript_drive_id,
                        // Carpeta real desde Drive (subcarpeta o raíz)
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
            $meeting = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->firstOrFail();

            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            if (empty($meeting->transcript_drive_id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transcripción no disponible',
                ], 404);
            }

            // Descargar el archivo .ju (transcripción)
            $transcriptContent = $this->downloadFromDrive($meeting->transcript_drive_id);
            $transcriptResult = $this->decryptJuFile($transcriptContent);
            $transcriptData = $transcriptResult['data'];
            $needsEncryption = $transcriptResult['needs_encryption'];

            // Obtener la ruta del audio
            $audioPath = $this->getAudioPath($meeting);

            // Procesar los datos de la transcripción
            $processedData = $this->processTranscriptData($transcriptData);

            return response()->json([
                'success' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->meeting_name,
                    'created_at' => $meeting->created_at->format('d/m/Y H:i'),
                    'audio_path' => $audioPath,
                    'summary' => $processedData['summary'],
                    'key_points' => $processedData['key_points'],
                    'tasks' => $processedData['tasks'],
                    'transcription' => $processedData['transcription'],
                    'speakers' => $processedData['speakers'] ?? [],
                    'segments' => $processedData['segments'] ?? [],
                    // Carpeta real desde Drive
                    'audio_folder' => $this->getFolderName($meeting->audio_drive_id),
                    'transcript_folder' => $this->getFolderName($meeting->transcript_drive_id),
                    'needs_encryption' => $needsEncryption,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar la reunión: ' . $e->getMessage()
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

    private function downloadFromDrive($fileId)
    {
        return $this->googleDriveService->downloadFileContent($fileId);
    }

    private function decryptJuFile($content): array
    {
        try {
            Log::info('decryptJuFile: Starting decryption', [
                'length' => strlen($content),
                'first_50' => substr($content, 0, 50)
            ]);

            // Primer intento: ver si el contenido ya es JSON válido (sin encriptar)
            $json_data = json_decode($content, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($json_data)) {
                Log::info('decryptJuFile: Content is already valid JSON (unencrypted)');
                return [
                    'data' => $this->extractMeetingDataFromJson($json_data),
                    'needs_encryption' => true,
                ];
            }

            // Segundo intento: ver si es un string encriptado directo de Laravel Crypt
            if (substr($content, 0, 3) === 'eyJ') {
                Log::info('decryptJuFile: Attempting to decrypt Laravel Crypt format');
                try {
                    // Intentar desencriptar directamente el string base64
                    $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($content);
                    Log::info('decryptJuFile: Direct decryption successful');

                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after decryption successful', ['keys' => array_keys($json_data)]);
                        return [
                            'data' => $this->extractMeetingDataFromJson($json_data),
                            'needs_encryption' => false,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::warning('decryptJuFile: Direct decryption failed', ['error' => $e->getMessage()]);
                }
            }

            // Tercer intento: ver si el contenido contiene el formato {"iv":"...","value":"..."} de Laravel
            if (str_contains($content, '"iv"') && str_contains($content, '"value"')) {
                Log::info('decryptJuFile: Detected Laravel Crypt JSON format');
                try {
                    // El contenido ya contiene el formato JSON de Laravel Crypt, desencriptar directamente
                    $decrypted = \Illuminate\Support\Facades\Crypt::decrypt($content);
                    Log::info('decryptJuFile: Laravel Crypt JSON decryption successful');

                    $json_data = json_decode($decrypted, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        Log::info('decryptJuFile: JSON parsing after Laravel Crypt decryption successful', ['keys' => array_keys($json_data)]);
                        return [
                            'data' => $this->extractMeetingDataFromJson($json_data),
                            'needs_encryption' => false,
                        ];
                    }
                } catch (\Exception $e) {
                    Log::error('decryptJuFile: Laravel Crypt JSON decryption failed', ['error' => $e->getMessage()]);
                }
            }

            // Fallback: usar datos por defecto
            Log::warning('decryptJuFile: Using default data - all decryption methods failed');
            return [
                'data' => $this->getDefaultMeetingData(),
                'needs_encryption' => false,
            ];

        } catch (\Exception $e) {
            Log::error('decryptJuFile: General exception', ['error' => $e->getMessage()]);
            return [
                'data' => $this->getDefaultMeetingData(),
                'needs_encryption' => false,
            ];
        }
    }

    private function getDefaultMeetingData(): array
    {
        return [
            'summary' => 'Resumen no disponible - Los archivos están encriptados y necesitan ser procesados.',
            'key_points' => [
                'Archivo encontrado en Google Drive',
                'Formato de encriptación detectado',
                'Procesamiento en desarrollo'
            ],
            'tasks' => [
                'Verificar método de encriptación utilizado',
                'Implementar desencriptación correcta'
            ],
            'transcription' => 'La transcripción está encriptada y será procesada en breve. Mientras tanto, puedes descargar el archivo original desde Google Drive.',
            'speakers' => ['Sistema'],
            'segments' => [
                [
                    'speaker' => 'Sistema',
                    'text' => 'El contenido de esta reunión está siendo procesado. El archivo se descargó correctamente desde Google Drive pero requiere desencriptación.',
                    'timestamp' => '00:00'
                ]
            ]
        ];
    }

    private function extractMeetingDataFromJson($data): array
    {
        // Intentar extraer datos de diferentes estructuras JSON posibles
        return [
            'summary' => $data['summary'] ?? $data['resumen'] ?? $data['meeting_summary'] ?? 'Resumen no disponible',
            'key_points' => $data['key_points'] ?? $data['keyPoints'] ?? $data['puntos_clave'] ?? $data['main_points'] ?? [],
            'tasks' => $data['tasks'] ?? $data['tareas'] ?? $data['action_items'] ?? [],
            'transcription' => $data['transcription'] ?? $data['transcripcion'] ?? $data['text'] ?? 'Transcripción no disponible',
            'speakers' => $data['speakers'] ?? $data['participantes'] ?? [],
            'segments' => $data['segments'] ?? $data['segmentos'] ?? []
        ];
    }

    private function processTranscriptData($data): array
    {
        $segments = $data['segments'] ?? [];

        $segments = array_map(function ($segment) {
            if ((!isset($segment['start']) || !isset($segment['end'])) && isset($segment['timestamp'])) {
                if (preg_match('/(\d{2}):(\d{2})(?::(\d{2}))?\s*-\s*(\d{2}):(\d{2})(?::(\d{2}))?/', $segment['timestamp'], $m)) {
                    $segment['start'] = isset($m[3])
                        ? ((int)$m[1] * 3600 + (int)$m[2] * 60 + (int)$m[3])
                        : ((int)$m[1] * 60 + (int)$m[2]);
                    $segment['end'] = isset($m[6])
                        ? ((int)$m[4] * 3600 + (int)$m[5] * 60 + (int)$m[6])
                        : ((int)$m[4] * 60 + (int)$m[5]);
                }
            }
            return $segment;
        }, $segments);

        return [
            'summary' => $data['summary'] ?? 'No hay resumen disponible',
            'key_points' => $data['key_points'] ?? [],
            'tasks' => $data['tasks'] ?? [],
            'transcription' => $data['transcription'] ?? 'No hay transcripción disponible',
            'speakers' => $data['speakers'] ?? [],
            'segments' => $segments,
        ];
    }

    private function storeTemporaryFile($content, $filename): string
    {
        $path = 'temp/' . $filename;
        Storage::disk('public')->put($path, $content);
        $fullPath = Storage::disk('public')->path($path);

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
                ->where('meeting_id', $meeting->id)
                ->orderBy('order_num')
                ->pluck('point_text')
                ->toArray();
        }

        if (in_array('transcription', $sections)) {
            $transcription = DB::table('transcriptions')
                ->where('meeting_id', $meeting->id)
                ->orderBy('id')
                ->pluck('text')
                ->implode("\n");
        }

        if (in_array('tasks', $sections)) {
            $tasks = Task::where('meeting_id', $meeting->id)
                ->get(['text', 'assignee', 'due_date', 'completed', 'priority', 'description']);
        }

        $pdf = Pdf::loadView('pdf.meeting-report', [
            'meeting' => $meeting,
            'summary' => $summary,
            'keyPoints' => $keyPoints,
            'transcription' => $transcription,
            'tasks' => $tasks,
        ]);

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
            $pattern = Storage::disk('public')->path('temp/' . $sanitizedName . '_' . $meetingModel->id . '.*');
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
                'segments' => $request->input('transcription_data'),
                'summary' => $analysisResults['summary'] ?? null,
                'keyPoints' => $analysisResults['keyPoints'] ?? [],
                'tasks' => $analysisResults['tasks'] ?? [],
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
            $analysisResults = $request->input('analysis_results');
            $audioData = $processInfo['temp_file'] ?? null;
            $audioDuration = 0;
            $speakerCount = 0;
            $tasks = $analysisResults['tasks'] ?? [];

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
            if ($hasOrganization) {
                $container = $meeting->containers()->first();
                if ($container && isset($container->organization)) {
                    $organizationName = $container->organization->name ?? 'Organización';
                }
            }

            // Crear el HTML para el PDF
            $html = $this->generatePdfHtml($meetingName, $realCreatedAt, $sections, $data, $hasOrganization, $organizationName);

            // Generar PDF usando DomPDF
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('A4', 'portrait');

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
     * Genera el HTML para el PDF con el nuevo diseño solicitado
     */
    private function generatePdfHtml($meetingName, $realCreatedAt, $sections, $data, $hasOrganization = false, $organizationName = null)
    {
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="utf-8">
            <title>' . htmlspecialchars($meetingName) . '</title>
            <style>
                @page {
                    margin: 20mm;
                    size: A4;
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
                    padding: 0;
                    background: white;
                }

                /* Header */
                .header {
                    background: #3b82f6;
                    background: -webkit-linear-gradient(left, #3b82f6, #1d4ed8);
                    background: linear-gradient(to right, #3b82f6, #1d4ed8);
                    color: white !important;
                    padding: 20px;
                    margin: -20mm -20mm 20px -20mm;
                    display: block;
                    width: calc(100% + 40mm);
                    position: relative;
                }
                .header-content {
                    display: table;
                    width: 100%;
                    color: white !important;
                }
                .header-left {
                    display: table-cell;
                    vertical-align: middle;
                    width: 70%;
                    color: white !important;
                }
                .header-right {
                    display: table-cell;
                    vertical-align: middle;
                    width: 30%;
                    text-align: right;
                    color: white !important;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    letter-spacing: 1px;
                    color: white !important;
                }
                .org-logo {
                    font-size: 12px;
                    font-style: italic;
                    opacity: 0.9;
                    color: white !important;
                }

                /* Contenido principal */
                .main-content {
                    padding: 0;
                    margin: 0;
                }

                /* Título centrado */
                .title-section {
                    text-align: center;
                    margin-bottom: 30px;
                    padding: 20px 0;
                }
                .meeting-title {
                    font-size: 20px;
                    font-weight: bold;
                    color: #333;
                    margin-bottom: 10px;
                    text-transform: uppercase;
                }
                .meeting-date {
                    font-size: 14px;
                    color: #666;
                    font-style: italic;
                }

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
                .tasks-table th {
                    background: linear-gradient(90deg, #3b82f6, #1d4ed8);
                    color: white;
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
                .footer {
                    background: #3b82f6;
                    background: -webkit-linear-gradient(left, #3b82f6, #1d4ed8);
                    background: linear-gradient(to right, #3b82f6, #1d4ed8);
                    color: white !important;
                    padding: 15px 20px;
                    font-size: 10px;
                    display: block;
                    width: calc(100% + 40mm);
                    margin: 20px -20mm -20mm -20mm;
                    position: relative;
                }
                .footer-content {
                    display: table;
                    width: 100%;
                    color: white !important;
                }
                .footer-left {
                    display: table-cell;
                    vertical-align: middle;
                    width: 50%;
                    color: white !important;
                }
                .footer-right {
                    display: table-cell;
                    vertical-align: middle;
                    width: 50%;
                    text-align: right;
                    font-weight: bold;
                    color: white !important;
                }
            </style>
        </head>
        <body>
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="header-left">
                        <div class="logo">JUNTIFY</div>
                    </div>
                    <div class="header-right">
                        <div class="org-logo">' . ($hasOrganization ? htmlspecialchars($organizationName ?? 'Organización') : '[Logo de la Organización]') . '</div>
                    </div>
                </div>
            </div>

            <!-- Contenido Principal -->
            <div class="main-content">
                <!-- Título Centrado -->
                <div class="title-section">
                    <div class="meeting-title">' . htmlspecialchars($meetingName) . '</div>
                    <div class="meeting-date">Fecha de Grabación: ' . $realCreatedAt->format('d/m/Y H:i') . '</div>
                </div>';

        // Resumen
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

        // Transcripción
        if (in_array('transcription', $sections) && !empty($data['transcription'])) {
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

        // Puntos Clave
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

        // Tareas
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

            if (is_array($data['tasks'])) {
                $taskCounter = 1;
                foreach ($data['tasks'] as $task) {
                    $taskText = is_string($task) ? $task : (is_array($task) ? implode(', ', $task) : strval($task));
                    $html .= '
                            <tr>
                                <td class="task-name">Tarea ' . $taskCounter . '</td>
                                <td class="task-description">' . htmlspecialchars($taskText) . '</td>
                                <td>Sin asignar</td>
                                <td>Sin asignar</td>
                                <td>Sin asignar</td>
                                <td>0%</td>
                            </tr>';
                    $taskCounter++;
                }
            } else {
                $taskText = is_string($data['tasks']) ? $data['tasks'] : strval($data['tasks']);
                $html .= '
                            <tr>
                                <td class="task-name">Tarea 1</td>
                                <td class="task-description">' . htmlspecialchars($taskText) . '</td>
                                <td>Sin asignar</td>
                                <td>Sin asignar</td>
                                <td>Sin asignar</td>
                                <td>0%</td>
                            </tr>';
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
                <div class="footer-content">
                    <div class="footer-left">Generado el: ' . date('d/m/Y H:i') . '</div>
                    <div class="footer-right">Página 1</div>
                </div>
            </div>
        </body>
        </html>';

        return $html;
    }
}
