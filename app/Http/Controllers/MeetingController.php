<?php

namespace App\Http\Controllers;

use App\Models\TranscriptionLaravel;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\MeetingContentContainer;
use App\Models\MeetingContentRelation;
use App\Models\Container;
use App\Models\TaskLaravel;
use App\Models\Meeting;
use App\Models\SharedMeeting;
use App\Models\User;
use App\Models\KeyPoint;
use App\Models\Transcription;
use App\Models\Task;
use Carbon\Carbon;
use App\Services\GoogleDriveService;
use Google\Service\Drive\DriveFile;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;

class MeetingController extends Controller
{
    use GoogleDriveHelpers, MeetingContentParsing;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    public function publicIndex()
    {
        $meetings = Meeting::query()
            ->select('id', 'title', 'date', 'duration')
            ->get()
            ->makeHidden(['created_at', 'updated_at']);

        return response()->json($meetings);
    }

    public function publicShow($meeting)
    {
        $meeting = Meeting::query()
            ->select('id', 'title', 'date', 'duration')
            ->findOrFail($meeting)
            ->makeHidden(['created_at', 'updated_at']);

        return response()->json($meeting);
    }

    /**
     * Muestra la página principal de reuniones.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        $user = Auth::user();

        // Server-side render of meetings list (keeps JS fallback too)
        try {

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

            return view('reuniones', [
                'meetings' => $meetings,
                'userRole' => $user->roles ?? 'free',
                'organizationId' => $user->current_organization_id,
            ]);
        } catch (\Throwable $e) {
            // If anything fails, return view without meetings (JS will fetch)
            Log::warning('Meetings SSR failed: ' . $e->getMessage());

            return view('reuniones', [
                'userRole' => $user->roles ?? 'free',
                'organizationId' => $user->current_organization_id,
            ]);
        }
    }

    /**
     * Obtiene todas las reuniones del usuario autenticado
     */
    public function getMeetings(): JsonResponse
    {
        try {
            $user = Auth::user();

            // Configurar el cliente de Google Drive con el token del usuario solo si existe
            $hasGoogleToken = false;
            try {
                if ($user->googleToken) {
                    $this->setGoogleDriveToken($user);
                    $hasGoogleToken = true;
                }
            } catch (\Exception $e) {
                Log::warning('getMeetings: Could not set Google Drive token', [
                    'user' => $user->username,
                    'error' => $e->getMessage()
                ]);
                $hasGoogleToken = false;
            }

            $legacyMeetings = TranscriptionLaravel::where('username', $user->username)
                ->whereDoesntHave('containers')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) use ($hasGoogleToken) {
                    $audioFolder = 'Sin especificar';
                    $transcriptFolder = 'Sin especificar';

                    if ($hasGoogleToken) {
                        try {
                            $audioFolder = $this->getFolderName($meeting->audio_drive_id);
                        } catch (\Exception $e) {
                            $audioFolder = 'Error al cargar carpeta';
                            Log::warning('getMeetings: Error getting audio folder name', [
                                'meeting_id' => $meeting->id,
                                'audio_drive_id' => $meeting->audio_drive_id,
                                'error' => $e->getMessage()
                            ]);
                        }

                        try {
                            $transcriptFolder = $this->getFolderName($meeting->transcript_drive_id);
                        } catch (\Exception $e) {
                            $transcriptFolder = 'Error al cargar carpeta';
                            Log::warning('getMeetings: Error getting transcript folder name', [
                                'meeting_id' => $meeting->id,
                                'transcript_drive_id' => $meeting->transcript_drive_id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } else {
                        $audioFolder = 'Google Drive no conectado';
                        $transcriptFolder = 'Google Drive no conectado';
                    }

                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->meeting_name,
                        'created_at' => $meeting->created_at,
                        'audio_folder' => $audioFolder,
                        'transcript_folder' => $transcriptFolder,
                        'is_legacy' => true,
                        'source' => 'transcriptions_laravel',
                    ];
                });

            $modernMeetings = Meeting::where('username', $user->username)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($meeting) use ($hasGoogleToken) {
                    $audioFolder = 'Base de datos';

                    if ($hasGoogleToken && $meeting->recordings_folder_id) {
                        try {
                            $audioFolder = $this->getFolderName($meeting->recordings_folder_id);
                        } catch (\Exception $e) {
                            $audioFolder = 'Error al cargar carpeta';
                            Log::warning('getMeetings: Error getting recordings folder name', [
                                'meeting_id' => $meeting->id,
                                'recordings_folder_id' => $meeting->recordings_folder_id,
                                'error' => $e->getMessage()
                            ]);
                        }
                    } elseif (!$hasGoogleToken && $meeting->recordings_folder_id) {
                        $audioFolder = 'Google Drive no conectado';
                    }

                    return [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->title,
                        'created_at' => $meeting->created_at ?? $meeting->date,
                        'audio_folder' => $audioFolder,
                        'transcript_folder' => 'Base de datos',
                        'is_legacy' => false,
                        'source' => 'meetings',
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

        } catch (\Throwable $e) {
            Log::error('Error al cargar reuniones', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar reuniones',
                'error' => $e->getMessage(),
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();
            $useServiceAccount = false;
            $sharerEmail = null;
            if ($sharedAccess) {
                $share = SharedMeeting::with('sharedBy')
                    ->where('meeting_id', $id)
                    ->where('shared_with', $user->id)
                    ->first();
                $sharerEmail = $share?->sharedBy?->email;
            }

            // Intentar buscar una reunión legacy primero
            $legacyMeeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
                ->first();

            try {
                $this->setGoogleDriveToken($user);
            } catch (\Throwable $e) {
                if ($sharedAccess) {
                    $useServiceAccount = true;
                } else {
                    throw $e;
                }
            }

            if ($legacyMeeting) {
                $ownerUsername = $share?->sharedBy?->username ?? $legacyMeeting->username ?? $user->username;
                if (empty($legacyMeeting->transcript_drive_id)) {
                    // Reconstruct meeting data from legacy database tables when .ju file is missing
                    $summary = DB::table('meeting_files')
                        ->where('meeting_id', $legacyMeeting->id)
                        ->value('summary');

                    $keyPoints = DB::table('key_points')
                        ->join('transcriptions_laravel', 'key_points.meeting_id', '=', 'transcriptions_laravel.id')
                        ->where('key_points.meeting_id', $legacyMeeting->id)
                        ->where('transcriptions_laravel.username', $ownerUsername)
                        ->orderBy('key_points.order_num')
                        ->pluck('key_points.point_text')
                        ->toArray();

                    $segmentsData = DB::table('transcriptions')
                        ->join('transcriptions_laravel', 'transcriptions.meeting_id', '=', 'transcriptions_laravel.id')
                        ->where('transcriptions.meeting_id', $legacyMeeting->id)
                        ->where('transcriptions_laravel.username', $ownerUsername)
                        ->orderBy('transcriptions.id')
                        ->get(['transcriptions.time', 'transcriptions.speaker', 'transcriptions.text', 'transcriptions.display_speaker']);

                    // Construir segmentos con tiempos de inicio/fin a partir del campo 'time' (formato mm:ss o hh:mm:ss)
                    $segments = $this->buildLegacySegmentsFromDb($segmentsData);

                    $transcriptionText = $segmentsData->pluck('text')->implode(' ');
                    $speakers = collect($segments)
                        ->map(function ($s) { return $s['display_speaker'] ?: $s['speaker']; })
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    $tasks = TaskLaravel::where('meeting_id', $legacyMeeting->id)
                        ->where('username', $ownerUsername)
                        ->get();

                    // Determinar si ya tenemos un archivo de audio. Si el usuario no tiene token y es acceso compartido,
                    // evitamos llamadas a Drive con token de usuario y usamos el endpoint de streaming (con fallback SA).
                    $audioPath = null;
                    $audioDriveId = null;
                    if ($useServiceAccount) {
                        $audioDriveId = $legacyMeeting->audio_drive_id;
                        $audioPath = route('api.meetings.audio', ['meeting' => $legacyMeeting->id]);
                    } else {
                        $hasDirectUrl = !empty($legacyMeeting->audio_download_url);
                        $isFileId = false;
                        if (!empty($legacyMeeting->audio_drive_id)) {
                            try {
                                $info = $this->googleDriveService->getFileInfo($legacyMeeting->audio_drive_id);
                                $isFileId = $info && $info->getMimeType() !== 'application/vnd.google-apps.folder';
                            } catch (\Exception $e) {
                                Log::warning('Error checking audio_drive_id', [
                                    'meeting_id' => $legacyMeeting->id,
                                    'audio_drive_id' => $legacyMeeting->audio_drive_id,
                                    'error' => $e->getMessage(),
                                ]);
                            }
                        }

                        if ($hasDirectUrl || $isFileId) {
                            $tempMeeting = (object) [
                                'id' => $legacyMeeting->id,
                                'meeting_name' => $legacyMeeting->meeting_name,
                                'audio_drive_id' => $legacyMeeting->audio_drive_id,
                                'audio_download_url' => $legacyMeeting->audio_download_url,
                            ];
                            $audioDriveId = $legacyMeeting->audio_drive_id;
                            $audioPath = $this->getAudioPath($tempMeeting);
                            if ($audioPath && !str_starts_with($audioPath, 'http')) {
                                $audioPath = $this->publicUrlFromStoragePath($audioPath);
                            }
                        } else {
                            $audioData = $this->googleDriveService->findAudioInFolder(
                                $legacyMeeting->audio_drive_id,
                                $legacyMeeting->meeting_name,
                                (string) $legacyMeeting->id
                            );
                            $audioPath = $audioData['downloadUrl'] ?? null;
                            $audioDriveId = $audioData['fileId'] ?? null;
                        }
                    }

                    return response()->json([
                        'success' => true,
                        'meeting' => [
                            'id' => $legacyMeeting->id,
                            'meeting_name' => $legacyMeeting->meeting_name,
                            'is_legacy' => true,
                            'created_at' => $legacyMeeting->created_at->format('d/m/Y H:i'),
                            'audio_path' => $audioPath,
                            'audio_drive_id' => $audioDriveId,
                            // Campos para acceder a transcripción (.ju) cuando exista
                            'transcript_drive_id' => $legacyMeeting->transcript_drive_id,
                            'transcript_download_url' => $legacyMeeting->transcript_download_url,
                            'transcript_path' => route('api.meetings.download-ju', ['id' => $legacyMeeting->id]),
                            'summary' => $summary ?? 'No hay resumen disponible',
                            'key_points' => $keyPoints,
                            'transcription' => $transcriptionText,
                            'tasks' => $tasks,
                            'speakers' => $speakers,
                            // Incluir segmentos con start/end solo para el caso legacy sin .ju
                            'segments' => $segments,
                            'audio_folder' => $this->getFolderName($legacyMeeting->audio_drive_id),
                            'transcript_folder' => 'Base de datos',
                            'needs_encryption' => false,
                        ]
                    ]);
                }

                if ($useServiceAccount) {
                    /** @var \App\Services\GoogleServiceAccount $sa */
                    $sa = app(\App\Services\GoogleServiceAccount::class);
                    if ($sharerEmail) { $sa->impersonate($sharerEmail); }
                    $transcriptContent = $sa->downloadFile($legacyMeeting->transcript_drive_id);
                } else {
                    $transcriptContent = $this->downloadFromDrive($legacyMeeting->transcript_drive_id);
                }
                $transcriptResult = $this->decryptJuFile($transcriptContent);
                $transcriptData = $transcriptResult['data'];
                $needsEncryption = $transcriptResult['needs_encryption'];

                $transcriptData = $this->extractMeetingDataFromJson($transcriptData);

                // Determinar si ya tenemos un archivo de audio directo o una URL descargable
                $audioPath = null;
                $audioDriveId = null;
                $hasDirectUrl = !empty($legacyMeeting->audio_download_url);
                $isFileId = false;
                if (!empty($legacyMeeting->audio_drive_id)) {
                    try {
                        if ($useServiceAccount) {
                            // Con service account no pedimos MIME aquí; asumimos que es archivo si no parece carpeta
                            $isFileId = true;
                        } else {
                            $info = $this->googleDriveService->getFileInfo($legacyMeeting->audio_drive_id);
                            $isFileId = $info && $info->getMimeType() !== 'application/vnd.google-apps.folder';
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error checking audio_drive_id', [
                            'meeting_id' => $legacyMeeting->id,
                            'audio_drive_id' => $legacyMeeting->audio_drive_id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // Evitar descargar audio pesado durante la carga del modal.
                // Preparar un ID de archivo si está disponible y usar el endpoint de streaming como ruta de audio.
                if ($hasDirectUrl || $isFileId) {
                    $audioDriveId = $legacyMeeting->audio_drive_id;
                } else {
                    $audioData = $this->googleDriveService->findAudioInFolder(
                        $legacyMeeting->audio_drive_id,
                        $legacyMeeting->meeting_name,
                        (string) $legacyMeeting->id
                    );
                    $audioDriveId = $audioData['fileId'] ?? null;
                }
                $audioPath = route('api.meetings.audio', ['meeting' => $legacyMeeting->id]);

                $processedData = $this->processTranscriptData($transcriptData);
                unset($processedData['tasks']);

                // Fallback: si no pudimos desencriptar o vienen textos por defecto, reconstruir desde DB
                $looksEncrypted = false;
                try {
                    $summaryTxt = (string)($processedData['summary'] ?? '');
                    $transTxt = (string)($processedData['transcription'] ?? '');
                    if (stripos($summaryTxt, 'encriptad') !== false || stripos($transTxt, 'encriptad') !== false) {
                        $looksEncrypted = true;
                    }
                } catch (\Throwable $e) { /* ignore */ }

                if ($looksEncrypted || (empty($processedData['segments']) && empty($processedData['key_points']))) {
                    $ownerUsernameForDb = $ownerUsername;
                    $segmentsData = DB::table('transcriptions')
                        ->join('transcriptions_laravel', 'transcriptions.meeting_id', '=', 'transcriptions_laravel.id')
                        ->where('transcriptions.meeting_id', $legacyMeeting->id)
                        ->where('transcriptions_laravel.username', $ownerUsernameForDb)
                        ->orderBy('transcriptions.id')
                        ->get(['transcriptions.time', 'transcriptions.speaker', 'transcriptions.text', 'transcriptions.display_speaker']);

                    $rebuiltSegments = $this->buildLegacySegmentsFromDb($segmentsData);
                    $rebuiltTranscription = $segmentsData->pluck('text')->implode(' ');
                    $rebuiltSpeakers = collect($rebuiltSegments)
                        ->map(function ($s) { return $s['display_speaker'] ?: $s['speaker']; })
                        ->filter()
                        ->unique()
                        ->values()
                        ->toArray();

                    // Sobrescribir datos procesados con fallback de DB
                    $processedData['segments'] = $rebuiltSegments;
                    $processedData['transcription'] = $rebuiltTranscription ?: ($processedData['transcription'] ?? '');
                    if (empty($processedData['speakers'])) { $processedData['speakers'] = $rebuiltSpeakers; }
                    if (empty($processedData['summary']) || stripos($processedData['summary'], 'encriptad') !== false) {
                        $processedData['summary'] = 'Resumen reconstruido desde base de datos (segmentos históricos).';
                    }
                }

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
                    ->where('username', $ownerUsername)
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
                        // Campos para acceder a transcripción (.ju) cuando exista
                        'transcript_drive_id' => $legacyMeeting->transcript_drive_id,
                        'transcript_download_url' => $legacyMeeting->transcript_download_url ?? null,
                        'transcript_path' => route('api.meetings.download-ju', ['id' => $legacyMeeting->id]),
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
            $meetingQuery = Meeting::with([
                'keyPoints' => function ($q) use ($user, $sharedAccess) {
                    if (!$sharedAccess) {
                        $q->whereHas('meeting', function ($mq) use ($user) {
                            $mq->where('username', $user->username);
                        });
                    }
                    $q->ordered();
                },
                'transcriptions' => function ($q) use ($user, $sharedAccess) {
                    if (!$sharedAccess) {
                        $q->whereHas('meeting', function ($mq) use ($user) {
                            $mq->where('username', $user->username);
                        });
                    }
                    $q->byTime();
                },
            ])->where('id', $id);
            if (!$sharedAccess) {
                $meetingQuery->where('username', $user->username);
            }
            $meeting = $meetingQuery->firstOrFail();
            $ownerUsername = $share?->sharedBy?->username ?? $meeting->username ?? $user->username;

            // Buscar audio (evitar llamadas con token de usuario si estamos usando SA)
            $audioPath = null;
            $audioDriveId = null;
            if ($useServiceAccount) {
                // Evitar consultas a Drive con token de usuario; usar streaming con fallback SA
                $audioPath = route('api.meetings.audio', ['meeting' => $meeting->id]);
            } else {
                if (!empty($meeting->recordings_folder_id)) {
                    $audioData = $this->googleDriveService->findAudioInFolder(
                        $meeting->recordings_folder_id,
                        $meeting->title,
                        (string) $meeting->id
                    );

                    if ($audioData) {
                        $tempMeeting = (object) [
                            'id' => $meeting->id,
                            'meeting_name' => $meeting->title,
                            'audio_drive_id' => $audioData['fileId'] ?? null,
                            // Pasar la URL directa si existe para evitar descargas innecesarias
                            'audio_download_url' => $audioData['downloadUrl'] ?? null,
                        ];
                        $audioDriveId = $audioData['fileId'] ?? null;
                        $audioPath = $this->getAudioPath($tempMeeting);
                        // Si es una ruta local del storage público, convertirla a URL pública
                        if ($audioPath && !str_starts_with($audioPath, 'http')) {
                            $audioPath = $this->publicUrlFromStoragePath($audioPath);
                        }
                    }
                }
            }

            // Fallback: buscar por título en la carpeta y luego globalmente si no se encontró por ID
            if ($audioPath === null) {
                $audioFile = null;
                if (!$useServiceAccount) {
                    if (!empty($meeting->recordings_folder_id)) {
                        $files = $this->googleDriveService->searchFiles($meeting->title, $meeting->recordings_folder_id);
                        $audioFile = $files[0] ?? null;
                    }
                    if (!$audioFile) {
                        $files = $this->googleDriveService->searchFiles($meeting->title, null);
                        $audioFile = $files[0] ?? null;
                    }
                }

                if ($audioFile) {
                    $tempMeeting = (object) [
                        'id' => $meeting->id,
                        'meeting_name' => $meeting->title,
                        'audio_drive_id' => $audioFile->getId(),
                        'audio_download_url' => null,
                    ];
                    $audioDriveId = $audioFile->getId();
                    // En lugar de exponer una ruta local/Drive directa, usar nuestro stream endpoint
                    $audioPath = route('api.meetings.audio', ['meeting' => $meeting->id]);
                } elseif ($useServiceAccount) {
                    // Sin búsquedas de Drive: devolver streaming para que resuelva internamente con SA
                    $audioPath = route('api.meetings.audio', ['meeting' => $meeting->id]);
                }
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

            // Obtener las tareas de la tabla TaskLaravel
            $tasks = TaskLaravel::where('meeting_id', $meeting->id)
                ->where('username', $ownerUsername)
                ->get();

            return response()->json([
                'success' => true,
                'meeting' => [
                    'id' => $meeting->id,
                    'meeting_name' => $meeting->title,
                    'is_legacy' => false,
                    'created_at' => ($meeting->date ?? $meeting->created_at)->format('d/m/Y H:i'),
                    'audio_path' => $audioPath ?? route('api.meetings.audio', ['meeting' => $meeting->id]),
                    // 'audio_drive_id' opcionalmente podría incluirse si el frontend lo requiere
                    'summary' => $meeting->summary,
                    'key_points' => $meeting->keyPoints->pluck('point_text'),
                    'transcription' => $transcriptionText,
                    'tasks' => $tasks,
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();
            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
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
            // Configurar el cliente de Google Drive con el token del usuario
            $this->setGoogleDriveToken($user);

            // 1) Intentar flujo legacy (.ju): TranscriptionLaravel
            $legacy = TranscriptionLaravel::where('id', $id)
                ->where('username', $user->username)
                ->first();

            if ($legacy) {
                Log::info('Eliminación de reunión legacy iniciada', [
                    'meeting_id' => $id,
                    'meeting_name' => $legacy->meeting_name,
                    'user' => $user->username
                ]);

                $warnings = [];
                // Eliminar archivo .ju de Drive (si existe)
                if (!empty($legacy->transcript_drive_id)) {
                    $juId = $this->normalizeDriveId($legacy->transcript_drive_id);
                    $ok = $this->deleteDriveFileResilient($juId, $user->email);
                    if ($ok) {
                        Log::info('Archivo .ju eliminado de Drive', ['file_id' => $juId]);
                    } else {
                        $warnings[] = 'No se pudo eliminar el archivo .ju';
                    }
                }

                // Eliminar archivo de audio de Drive (si existe)
                if (!empty($legacy->audio_drive_id)) {
                    $audioId = $this->normalizeDriveId($legacy->audio_drive_id);
                    $ok = $this->deleteDriveFileResilient($audioId, $user->email);
                    if ($ok) {
                        Log::info('Archivo de audio eliminado de Drive', ['file_id' => $audioId]);
                    } else {
                        $warnings[] = 'No se pudo eliminar el audio';
                    }
                }

                // Eliminar tareas asociadas en tasks_laravel
                try {
                    $deletedTasks = TaskLaravel::where('meeting_id', $legacy->id)->delete();
                    Log::info('Tareas asociadas eliminadas', ['meeting_id' => $legacy->id, 'count' => $deletedTasks]);
                } catch (\Exception $e) {
                    Log::warning('No se pudieron eliminar todas las tareas asociadas', ['meeting_id' => $legacy->id, 'error' => $e->getMessage()]);
                }

                // Remover asociaciones de contenedores (meeting_content_relations)
                try {
                    $deletedRelations = MeetingContentRelation::where('meeting_id', $legacy->id)->delete();
                    Log::info('Relaciones en contenedores eliminadas', ['meeting_id' => $legacy->id, 'count' => $deletedRelations]);
                } catch (\Exception $e) {
                    Log::warning('No se pudieron eliminar las relaciones en contenedores', ['meeting_id' => $legacy->id, 'error' => $e->getMessage()]);
                }

                // Eliminar registro de la base de datos (legacy)
                $legacy->delete();

                Log::info('Reunión legacy eliminada completamente', ['meeting_id' => $id]);
                return response()->json([
                    'success' => true,
                    'message' => 'Reunión eliminada correctamente' . (count($warnings) ? (' (' . implode('; ', $warnings) . ')') : ''),
                ]);
            }

            // 2) Flujo moderno (BD): Meeting (solo borrar audio en Drive)
            $modern = Meeting::where('id', $id)
                ->where('username', $user->username)
                ->first();

            if ($modern) {
                Log::info('Eliminación de reunión moderna (audio en Drive + limpieza en BD)', [
                    'meeting_id' => $id,
                    'title' => $modern->title,
                    'user' => $user->username
                ]);

                $deletedAudio = false;
                try {
                    if (!empty($modern->recordings_folder_id)) {
                        $found = $this->googleDriveService->findAudioInFolder(
                            $modern->recordings_folder_id,
                            $modern->title,
                            (string)$modern->id
                        );
                        if ($found && !empty($found['fileId'])) {
                            if ($this->deleteDriveFileResilient($found['fileId'], $user->email)) {
                                $deletedAudio = true;
                                Log::info('Audio de reunión moderna eliminado en Drive', ['file_id' => $found['fileId']]);
                            } else {
                                Log::warning('No se pudo eliminar audio de reunión moderna en Drive', ['file_id' => $found['fileId']]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Error al eliminar audio de reunión moderna en Drive', [
                        'meeting_id' => $modern->id,
                        'error' => $e->getMessage()
                    ]);
                    // Continuar y reportar como advertencia
                }

                // Eliminar tareas asociadas en tasks_laravel (si hay alguna enlazada por meeting_id)
                try {
                    $deletedTasks = TaskLaravel::where('meeting_id', $modern->id)->delete();
                    Log::info('Tareas asociadas (moderna) eliminadas', ['meeting_id' => $modern->id, 'count' => $deletedTasks]);
                } catch (\Exception $e) {
                    Log::warning('No se pudieron eliminar tareas asociadas (moderna)', ['meeting_id' => $modern->id, 'error' => $e->getMessage()]);
                }

                // Remover asociaciones de contenedores
                try {
                    $deletedRelations = MeetingContentRelation::where('meeting_id', $modern->id)->delete();
                    Log::info('Relaciones en contenedores (moderna) eliminadas', ['meeting_id' => $modern->id, 'count' => $deletedRelations]);
                } catch (\Exception $e) {
                    Log::warning('No se pudieron eliminar relaciones en contenedores (moderna)', ['meeting_id' => $modern->id, 'error' => $e->getMessage()]);
                }

                // Eliminar registro de Meeting
                try {
                    $modern->delete();
                    Log::info('Registro de Meeting eliminado', ['meeting_id' => $id]);
                } catch (\Exception $e) {
                    Log::warning('No se pudo eliminar el registro de Meeting', ['meeting_id' => $id, 'error' => $e->getMessage()]);
                }

                return response()->json([
                    'success' => true,
                    'message' => $deletedAudio ? 'Reunión y audio eliminados' : 'Reunión eliminada (no se encontró audio en Drive)'
                ]);
            }

            // 3) Si no es legacy ni moderno
            return response()->json([
                'success' => false,
                'message' => 'Reunión no encontrada'
            ], 404);

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

    /**
     * Intenta eliminar un archivo de Drive con el token del usuario y, si falla
     * por permisos u otros motivos, intenta con la cuenta de servicio impersonando
     * al usuario. Devuelve true si finalmente se elimina o si el archivo no existe,
     * false si no fue posible eliminarlo.
     */
    private function deleteDriveFileResilient(string $fileId, string $userEmail): bool
    {
    // Extra safety: normalize ID
    $fileId = $this->normalizeDriveId($fileId);
        // Primero, intentar con el token del usuario
        try {
            $this->googleDriveService->deleteFile($fileId);
            return true;
        } catch (\Exception $e) {
            Log::warning('deleteDriveFileResilient: fallo con token de usuario, se intentará con service account', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Segundo, intentar con Service Account impersonando al usuario
        try {
            /** @var \App\Services\GoogleServiceAccount $sa */
            $sa = app(\App\Services\GoogleServiceAccount::class);
            $sa->impersonate($userEmail);
            $sa->deleteFile($fileId);
            return true;
        } catch (\Exception $e) {
            Log::error('deleteDriveFileResilient: fallo con service account', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        // Tercero, intentar con Service Account sin impersonate (por si el archivo fue creado por la SA)
        try {
            /** @var \App\Services\GoogleServiceAccount $sa */
            $sa = app(\App\Services\GoogleServiceAccount::class);
            // no impersonate
            $sa->deleteFile($fileId);
            return true;
        } catch (\Exception $e) {
            Log::error('deleteDriveFileResilient: fallo con service account sin impersonate', [
                'file_id' => $fileId,
                'error' => $e->getMessage(),
            ]);
            if ($this->isNotFoundDriveError($e)) {
                return true; // ya no existe, considerar éxito
            }
        }

        return false;
    }

    private function isNotFoundDriveError(\Exception $e): bool
    {
        $msg = $e->getMessage();
        if (stripos($msg, 'File not found') !== false || stripos($msg, 'notFound') !== false) {
            return true;
        }
        // Algunos SDK devuelven código en getCode()
        $code = (int) $e->getCode();
        return $code === 404;
    }

    /**
     * Acepta un ID directo o una URL de Google Drive y devuelve el fileId.
     */
    private function normalizeDriveId(string $maybeId): string
    {
        // Si parece URL con /file/d/{id}/
        if (preg_match('#/file/d/([^/]+)/#', $maybeId, $m)) {
            return $m[1];
        }
        // URL tipo uc?export=download&id={id}
        if (preg_match('#[?&]id=([a-zA-Z0-9_-]+)#', $maybeId, $m)) {
            return $m[1];
        }
        // Ya es un ID
        return $maybeId;
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
                // Normalizar y devolver directamente; dejar que el navegador siga redirecciones de Drive
                $normalized = $this->normalizeDriveUrl($meeting->audio_download_url);
                Log::info('Usando URL directa de descarga para audio (sin verificación estricta)', [
                    'meeting_id' => $meeting->id,
                    'url' => $normalized,
                ]);
                return $normalized;
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
     * Construye segmentos para reuniones legacy (sin .ju) a partir de filas de DB con 'time'.
     * - Calcula 'start' en segundos desde time (mm:ss u hh:mm:ss)
     * - Estima 'end' como el inicio del siguiente segmento; el último queda sin end (usa start+10s fallback)
     */
    private function buildLegacySegmentsFromDb($segmentsData): array
    {
        // Convertir a arreglo con tiempos en segundos
        $raw = [];
        foreach ($segmentsData as $row) {
            $startSec = $this->parseTimestampToSeconds($row->time);
            $raw[] = [
                'time' => $row->time,
                'start' => $startSec,
                'end' => null, // se define luego
                'speaker' => $row->speaker,
                'text' => $row->text,
                'display_speaker' => $row->display_speaker,
            ];
        }

        // Asignar end como el start del siguiente segmento
        for ($i = 0; $i < count($raw); $i++) {
            if ($i + 1 < count($raw)) {
                $raw[$i]['end'] = $raw[$i + 1]['start'];
            } else {
                // Fallback: 10s después del último start
                $raw[$i]['end'] = $raw[$i]['start'] + 10;
            }
        }

        return $raw;
    }

    /**
     * Parsea una marca de tiempo tipo 'mm:ss' o 'hh:mm:ss' a segundos.
     */
    private function parseTimestampToSeconds(?string $time): int
    {
        if (!$time) return 0;
        $parts = explode(':', $time);
        $parts = array_map('intval', $parts);
        if (count($parts) === 3) {
            [$h, $m, $s] = $parts;
            return $h * 3600 + $m * 60 + $s;
        }
        if (count($parts) === 2) {
            [$m, $s] = $parts;
            return $m * 60 + $s;
        }
        // Si no coincide, intentar castear entero
        return (int) $time;
    }

    /**
     * Convierte una ruta absoluta en storage/app/public a una URL pública accesible
     */
    private function publicUrlFromStoragePath(string $absolutePath): string
    {
        // Esperamos un path como: storage_path('app/public/temp/...')
        $publicRoot = storage_path('app/public/');
        if (str_starts_with($absolutePath, $publicRoot)) {
            $relative = substr($absolutePath, strlen($publicRoot));
            // Usar helper asset() para mapear a /storage/<relative>
            // Requiere que el symlink public/storage exista (php artisan storage:link)
            return asset('storage/' . str_replace('\\', '/', $relative));
        }
        // Si no coincide, devolver original (puede ser ya URL)
        return $absolutePath;
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
            // Permitir a receptores de compartidos descargar por ID (sin requerir token del usuario)
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();

            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
                ->firstOrFail();

            $isOwner = isset($user->username) && $meeting->username === $user->username;
            $recipientShared = $sharedAccess && !$isOwner;

            if (empty($meeting->transcript_drive_id)) {
                return response()->json(['error' => 'No se encontró archivo .ju para esta reunión'], 404);
            }

            // Si es receptor de reunión compartida, descargar y servir desde el servidor usando Service Account
            if ($recipientShared) {
                try {
                    /** @var \App\Services\GoogleServiceAccount $sa */
                    $sa = app(\App\Services\GoogleServiceAccount::class);
                    // Intentar impersonar al dueño para garantizar acceso
                    try {
                        $owner = $meeting->user()->first();
                        if ($owner && !empty($owner->email)) {
                            $sa->impersonate($owner->email);
                        }
                    } catch (\Throwable $e) {
                        Log::info('downloadJuFile impersonation failed', [
                            'meeting_id' => $id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        // Continuar sin impersonate si falla
                    }
                    $content = $sa->downloadFile($meeting->transcript_drive_id);
                    $filename = 'meeting_' . $meeting->id . '.ju';
                    return response($content)
                        ->header('Content-Type', 'application/octet-stream')
                        ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                } catch (\Throwable $e) {
                    Log::warning('downloadJuFile SA impersonated download failed', [
                        'meeting_id' => $id,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    // Intentar descarga sin impersonar antes de redirigir
                    try {
                        Log::info('downloadJuFile retrying without impersonation', [
                            'meeting_id' => $id,
                        ]);
                        /** @var \App\Services\GoogleServiceAccount $saNoImpersonate */
                        $saNoImpersonate = app(\App\Services\GoogleServiceAccount::class);
                        $content = $saNoImpersonate->downloadFile($meeting->transcript_drive_id);
                        $filename = 'meeting_' . $meeting->id . '.ju';
                        return response($content)
                            ->header('Content-Type', 'application/octet-stream')
                            ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                    } catch (\Throwable $e2) {
                        Log::warning('downloadJuFile SA non-impersonated download failed', [
                            'meeting_id' => $id,
                            'error' => $e2->getMessage(),
                            'trace' => $e2->getTraceAsString(),
                        ]);
                        $direct = !empty($meeting->transcript_download_url)
                            ? $this->normalizeDriveUrl($meeting->transcript_download_url)
                            : 'https://drive.google.com/uc?export=download&id=' . $meeting->transcript_drive_id;
                        try {
                            $response = Http::withOptions(['allow_redirects' => false])->head($direct);
                            if ($response->successful()) {
                                return redirect()->away($direct);
                            }
                        } catch (\Throwable $e3) {
                            Log::warning('downloadJuFile redirect check failed', [
                                'meeting_id' => $id,
                                'error' => $e3->getMessage(),
                                'trace' => $e3->getTraceAsString(),
                            ]);
                        }
                        return response()->json(['error' => 'Archivo .ju no accesible'], 404);
                    }
                }
            }

            // Propietario u otros casos: intentar con URL directa si existe
            if (!empty($meeting->transcript_download_url)) {
                $direct = $this->normalizeDriveUrl($meeting->transcript_download_url);
                return redirect()->away($direct);
            }

            // 2) Si el usuario tiene token de Google, intentamos descarga/redirect usando el servicio
            try {
                $this->setGoogleDriveToken($user);
                // Intentar obtener un webContentLink directo
                try {
                    $direct = app(\App\Services\GoogleDriveService::class)->getWebContentLink($meeting->transcript_drive_id);
                    if ($direct) {
                        return redirect()->away($direct);
                    }
                } catch (\Throwable $e) {
                    // Ignorar y hacer fallback a descarga vía servidor
                }
                $content = $this->googleDriveService->downloadFileContent($meeting->transcript_drive_id);
                $filename = 'meeting_' . $meeting->id . '.ju';
                return response($content)
                    ->header('Content-Type', 'application/octet-stream')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            } catch (\Throwable $e) {
                // 3) Fallback sin token: redirigir a URL estándar de Drive por ID
                $direct = 'https://drive.google.com/uc?export=download&id=' . $meeting->transcript_drive_id;
                return redirect()->away($direct);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Reunión no encontrada'], 404);
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();

            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
                ->firstOrFail();

            $fileId = $meeting->audio_drive_id;
            if (empty($fileId)) {
                // Intentar localizar por carpeta/título
                try {
                    $this->setGoogleDriveToken($user);
                    $found = $this->googleDriveService->findAudioInFolder(
                        $meeting->audio_drive_id,
                        $meeting->meeting_name,
                        (string) $meeting->id
                    );
                    $fileId = $found['fileId'] ?? null;
                } catch (\Throwable $e) {
                    // si no hay token, dejamos fileId como null
                }
            }

            // 1) Preferir URL directa almacenada en DB
            if (!empty($meeting->audio_download_url)) {
                $direct = $this->normalizeDriveUrl($meeting->audio_download_url);
                return redirect()->away($direct);
            }

            if (empty($fileId) && !empty($meeting->audio_drive_id)) {
                $fileId = $meeting->audio_drive_id;
            }

            if (empty($fileId)) {
                return response()->json(['error' => 'No se encontró archivo de audio para esta reunión'], 404);
            }

            // Preferir redirigir a Google Drive para evitar tiempos de espera en el servidor
            try {
                // Si el usuario tiene token, intentar webContentLink
                $this->setGoogleDriveToken($user);
                try {
                    $direct = app(\App\Services\GoogleDriveService::class)->getWebContentLink($fileId);
                    if ($direct) {
                        return redirect()->away($direct);
                    }
                } catch (\Throwable $e) {
                    // ignorar y continuar con fallback
                }
            } catch (\Exception $e) {
                // Sin token: haremos fallback a uc?export con el fileId
            }

            // Fallback a descarga a través del servidor (menos ideal por tamaño)
            try {
                $this->setGoogleDriveToken($user);
                $content = $this->googleDriveService->downloadFileContent($fileId);
                $filename = 'meeting_' . $meeting->id . '_audio';
                return response($content)
                    ->header('Content-Type', 'application/octet-stream')
                    ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
            } catch (\Throwable $e) {
                // Último fallback sin token: redirigir a uc?export por ID
                $direct = 'https://drive.google.com/uc?export=download&id=' . $fileId;
                return redirect()->away($direct);
            }

        } catch (ModelNotFoundException $e) {
            return response()->json(['error' => 'Reunión no encontrada'], 404);
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

            // Determinar si el usuario tiene acceso compartido
            $sharedMeeting = SharedMeeting::with('sharedBy')
                ->where('meeting_id', $meeting)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->first();
            $sharedAccess = (bool) $sharedMeeting;

            // Intentar configurar el token del usuario
            try {
                $this->setGoogleDriveToken($user);
            } catch (\Throwable $e) {
                if ($sharedAccess && $sharedMeeting?->sharedBy?->email) {
                    /** @var \App\Services\GoogleServiceAccount $sa */
                    $sa = app(\App\Services\GoogleServiceAccount::class);
                    $sa->impersonate($sharedMeeting->sharedBy->email);
                    $token = $sa->getClient()->fetchAccessTokenWithAssertion();
                    $this->googleDriveService->setAccessToken($token);
                } else {
                    throw $e;
                }
            }

            // Intentar flujo legacy primero
            try {
                $meetingModel = TranscriptionLaravel::where('id', $meeting)
                    ->when(!$sharedAccess, function ($q) use ($user) {
                        $q->where('username', $user->username);
                    })
                    ->firstOrFail();

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
                        // Si tenemos fileId, descargar y guardar localmente para servir con soporte de rangos
                        if (!empty($meetingModel->audio_drive_id)) {
                            try {
                                $info = $this->googleDriveService->getFileInfo($meetingModel->audio_drive_id);
                                $ext = $this->detectAudioExtension($info->getName(), $info->getMimeType());
                                $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $meetingModel->meeting_name);
                                $fileName = $sanitized . '_' . $meetingModel->id . '.' . $ext;
                                $content = $this->downloadFromDrive($meetingModel->audio_drive_id);
                                $localPath = $this->storeTemporaryFile($content, $fileName);
                                $publicUrl = $this->publicUrlFromStoragePath($localPath);
                                return redirect()->to($publicUrl, 302);
                            } catch (\Throwable $e) {
                                // Si falla, intentar redirigir al enlace externo como último recurso
                                return redirect()->away($audioPath);
                            }
                        }
                        return redirect()->away($audioPath);
                    }
                }

                // Si es un archivo local en storage/app/public, redirigir a la URL pública
                $publicRoot = storage_path('app/public/');
                if (str_starts_with($audioPath, $publicRoot)) {
                    $publicUrl = $this->publicUrlFromStoragePath($audioPath);
                    return redirect()->to($publicUrl, 302);
                }

                $mimeType = mime_content_type($audioPath) ?: 'audio/mpeg';
                return response()->file($audioPath, [ 'Content-Type' => $mimeType ]);
            } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
                // Flujo moderno (Meeting en BD)
                $modern = Meeting::where('id', $meeting)
                    ->when(!$sharedAccess, function ($q) use ($user) {
                        $q->where('username', $user->username);
                    })
                    ->firstOrFail();

                // Reutilizar si ya existe archivo temporal
                $sanitizedName = preg_replace('/[^a-zA-Z0-9_-]/', '_', $modern->title);
                $pattern = storage_path('app/public/temp/' . $sanitizedName . '_' . $modern->id . '.*');
                $existingFiles = glob($pattern);

                if (!empty($existingFiles)) {
                    $audioPath = $existingFiles[0];
                    // Si es un archivo local en storage/app/public, redirigir a la URL pública
                    $publicRoot = storage_path('app/public/');
                    if (str_starts_with($audioPath, $publicRoot)) {
                        $publicUrl = $this->publicUrlFromStoragePath($audioPath);
                        return redirect()->to($publicUrl, 302);
                    }
                    $mimeType = mime_content_type($audioPath) ?: 'audio/mpeg';
                    return response()->file($audioPath, [ 'Content-Type' => $mimeType ]);
                }

                $audioData = null;
                if (!empty($modern->recordings_folder_id)) {
                    $audioData = $this->googleDriveService->findAudioInFolder(
                        $modern->recordings_folder_id,
                        $modern->title,
                        (string) $modern->id
                    );
                }
                if (!$audioData) {
                    $files = $this->googleDriveService->searchFiles($modern->title, $modern->recordings_folder_id);
                    $file = $files[0] ?? null;
                    if (!$file) {
                        $files = $this->googleDriveService->searchFiles($modern->title, null);
                        $file = $files[0] ?? null;
                    }
                    if ($file) {
                        $audioData = [ 'fileId' => $file->getId(), 'downloadUrl' => null ];
                    }
                }

                if (!$audioData) {
                    return response()->json(['error' => 'Audio no disponible'], 404);
                }

                $tempMeeting = (object) [
                    'id' => $modern->id,
                    'meeting_name' => $modern->title,
                    'audio_drive_id' => $audioData['fileId'] ?? null,
                    'audio_download_url' => $audioData['downloadUrl'] ?? null,
                ];
                $audioPath = $this->getAudioPath($tempMeeting);
                if (!$audioPath) {
                    return response()->json(['error' => 'Audio no disponible'], 404);
                }
                if (str_starts_with($audioPath, 'http')) {
                    // Descargar por fileId y redirigir a URL pública local si es posible
                    if (!empty($tempMeeting->audio_drive_id)) {
                        try {
                            $info = $this->googleDriveService->getFileInfo($tempMeeting->audio_drive_id);
                            $ext = $this->detectAudioExtension($info->getName(), $info->getMimeType());
                            $sanitized = preg_replace('/[^a-zA-Z0-9_-]/', '_', $tempMeeting->meeting_name);
                            $fileName = $sanitized . '_' . $tempMeeting->id . '.' . $ext;
                            $content = $this->downloadFromDrive($tempMeeting->audio_drive_id);
                            $localPath = $this->storeTemporaryFile($content, $fileName);
                            $publicUrl = $this->publicUrlFromStoragePath($localPath);
                            return redirect()->to($publicUrl, 302);
                        } catch (\Throwable $e) {
                            return redirect()->away($audioPath);
                        }
                    }
                    return redirect()->away($audioPath);
                }

                // Si es un archivo local en storage/app/public, redirigir a la URL pública
                $publicRoot = storage_path('app/public/');
                if (str_starts_with($audioPath, $publicRoot)) {
                    $publicUrl = $this->publicUrlFromStoragePath($audioPath);
                    return redirect()->to($publicUrl, 302);
                }
                $mimeType = mime_content_type($audioPath) ?: 'audio/mpeg';
                return response()->file($audioPath, [ 'Content-Type' => $mimeType ]);
            }
        } catch (\Exception $e) {
            Log::error('Error streaming audio file', [ 'meeting_id' => $meeting, 'error' => $e->getMessage() ]);
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

            $newMeetingName = $request->input('meeting_name');


            Log::info('Datos recibidos para completePendingMeeting', [
                'root_folder' => $request->input('root_folder'),
                'transcription_subfolder' => $request->input('transcription_subfolder'),
                'audio_subfolder' => $request->input('audio_subfolder'),
                'all_inputs' => $request->all()
            ]);

            // Determinar carpetas de destino y token
            $rootInput = $request->input('root_folder');
            $rootFolder = Folder::where('google_id', $rootInput)->first();
            $usingOrgDrive = false;
            if (!$rootFolder) {
                $rootFolder = Folder::where('id', $rootInput)->first();
            }
            if (!$rootFolder) {
                $rootFolder = OrganizationFolder::where('google_id', $rootInput)->first();
                if (!$rootFolder) {
                    $rootFolder = OrganizationFolder::where('id', $rootInput)->first();
                }
                if ($rootFolder) {
                    $usingOrgDrive = true;
                }
            }
            if (!$rootFolder) {
                $token = GoogleToken::where('username', $user->username)->first();
                $availableFolders = [];
                if ($token) {
                    $availableFolders = Folder::where('google_token_id', $token->id)
                        ->get(['id', 'google_id', 'name'])->toArray();
                }
                Log::error('No se encontró la carpeta raíz', [
                    'root_folder_value' => $rootInput,
                    'available_folders' => $availableFolders,
                ]);
                throw new \Exception('Carpeta raíz no encontrada: ' . $rootInput);
            }

            if ($usingOrgDrive) {
                $orgToken = $rootFolder->googleToken;
                if (!$orgToken) {
                    throw new \Exception('Token de Google Drive de la organización no encontrado');
                }
                $client = $this->googleDriveService->getClient();
                $client->setAccessToken([
                    'access_token' => $orgToken->access_token,
                    'refresh_token' => $orgToken->refresh_token,
                    'expiry_date' => $orgToken->expiry_date,
                ]);
                if ($client->isAccessTokenExpired()) {
                    $new = $client->fetchAccessTokenWithRefreshToken($orgToken->refresh_token);
                    if (!isset($new['error'])) {
                        $orgToken->update([
                            'access_token' => $new['access_token'],
                            'expiry_date' => now()->addSeconds($new['expires_in']),
                        ]);
                        $client->setAccessToken($new);
                    } else {
                        throw new \Exception('No se pudo renovar el token de Google Drive');
                    }
                }
            } else {
                $this->setGoogleDriveToken($user);
            }

            $transcriptionFolderId = $rootFolder->google_id;
            if ($request->input('transcription_subfolder')) {
                if ($usingOrgDrive) {
                    $sub = OrganizationSubfolder::where('google_id', $request->input('transcription_subfolder'))
                        ->where('organization_folder_id', $rootFolder->id)
                        ->first();
                } else {
                    $sub = \App\Models\Subfolder::where('google_id', $request->input('transcription_subfolder'))
                        ->where('folder_id', $rootFolder->id)
                        ->first();
                }
                if ($sub) {
                    $transcriptionFolderId = $sub->google_id;
                } else {
                    Log::warning('Subcarpeta de transcripción no encontrada', [
                        'transcription_subfolder' => $request->input('transcription_subfolder'),
                        'root_folder_id' => $rootFolder->id,
                    ]);
                }
            }

            $audioFolderId = $rootFolder->google_id;
            if ($request->input('audio_subfolder')) {
                if ($usingOrgDrive) {
                    $sub = OrganizationSubfolder::where('google_id', $request->input('audio_subfolder'))
                        ->where('organization_folder_id', $rootFolder->id)
                        ->first();
                } else {
                    $sub = \App\Models\Subfolder::where('google_id', $request->input('audio_subfolder'))
                        ->where('folder_id', $rootFolder->id)
                        ->first();
                }
                if ($sub) {
                    $audioFolderId = $sub->google_id;
                } else {
                    Log::warning('Subcarpeta de audio no encontrada', [
                        'audio_subfolder' => $request->input('audio_subfolder'),
                        'root_folder_id' => $rootFolder->id,
                    ]);
                }
            }
            // 1. Mover y renombrar el audio en Google Drive
            $oldFileId = $processInfo['drive_file_id'];
            $audioExtension = pathinfo($processInfo['original_name'], PATHINFO_EXTENSION);
            $newAudioName = $newMeetingName . '.' . $audioExtension;

            // Mover el archivo a la nueva ubicación con nuevo nombre
            $drive = $this->googleDriveService->getDrive();
            $file = $drive->files->get($oldFileId, [
                'fields' => 'parents,name',
                'supportsAllDrives' => true,
            ]);
            $currentParents = $file->getParents();
            $updateData = ['name' => $newAudioName];
            $options = [
                'addParents' => $audioFolderId,
                'fields' => 'id,parents',
                'supportsAllDrives' => true,
            ];
            if ($currentParents) {
                $options['removeParents'] = implode(',', $currentParents);
            }
            $updatedFile = $drive->files->update($oldFileId, new DriveFile($updateData), $options);
            $newAudioFileId = $updatedFile->getId();

            // 2. Crear y subir la transcripción
            $analysisResults = $request->input('analysis_results');
            $payload = [
                'summary' => $analysisResults['summary'] ?? null,
                'keyPoints' => $analysisResults['keyPoints'] ?? [],
                'segments' => $request->input('transcription_data'),
            ];
            $encrypted = Crypt::encryptString(json_encode($payload));

            $transcriptFileId = $this->googleDriveService->uploadFile(
                $newMeetingName . '.ju',
                'application/json',
                $transcriptionFolderId,
                $encrypted
            );

            // 3. Obtener URLs de descarga
            $audioUrl = $this->googleDriveService->getFileLink($newAudioFileId);
            $transcriptUrl = $this->googleDriveService->getFileLink($transcriptFileId);

            // 4. Guardar en la BD principal (TranscriptionLaravel)
            $transcription = \App\Models\TranscriptionLaravel::create([
                'username' => $user->username,
                'meeting_name' => $newMeetingName,
                'audio_drive_id' => $newAudioFileId,
                'audio_download_url' => $audioUrl,
                'transcript_drive_id' => $transcriptFileId,
                'transcript_download_url' => $transcriptUrl,
            ]);

            // 4b. Procesar y guardar tareas en la BD (con parseo robusto y upsert)
            if (!empty($analysisResults['tasks']) && is_array($analysisResults['tasks'])) {
                foreach ($analysisResults['tasks'] as $rawTask) {
                    // Prefer explicit mapping per user request
                    // incoming shape example: { id, text, context, assignee, dueDate }
                    $payload = [
                        'username' => $user->username,
                        'meeting_id' => $transcription->id,
                        'tarea' => substr((string)($rawTask['text'] ?? ''), 0, 255) ?: 'Sin nombre',
                        'descripcion' => (string)($rawTask['context'] ?? ''),
                        'asignado' => (string)($rawTask['assignee'] ?? null),
                        'fecha_inicio' => null,
                        'fecha_limite' => null,
                        'hora_limite' => null,
                        'prioridad' => null,
                        'progreso' => 0,
                    ];

                    // Map dueDate (string like '2025-08-27' or 'No definida') to fecha_inicio
                    $due = $rawTask['dueDate'] ?? null;
                    if (is_string($due)) {
                        const1: {
                            $s = trim($due);
                            if ($s !== '' && strtolower($s) !== 'no definida' && strtolower($s) !== 'no asignado') {
                                // support dd/mm/yyyy and yyyy-mm-dd
                                if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $s, $m)) {
                                    $payload['fecha_inicio'] = $m[3] . '-' . $m[2] . '-' . $m[1];
                                } elseif (preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
                                    $payload['fecha_inicio'] = $s;
                                }
                            }
                        }
                    }

                    $existing = TaskLaravel::where('meeting_id', $payload['meeting_id'])
                        ->where('username', $payload['username'])
                        ->where('tarea', $payload['tarea'])
                        ->first();
                    if ($existing) {
                        $existing->update($payload);
                    } else {
                        TaskLaravel::create($payload);
                    }
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();

            // Validar que la reunión pertenece al usuario
            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
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
            // Si se solicitó la sección de tareas, usar siempre las tareas de la BD y mapear a columnas específicas
            if (in_array('tasks', $sections)) {
                $dbTasks = TaskLaravel::where('meeting_id', $meeting->id)
                    ->where('username', $user->username)
                    ->get(['tarea', 'descripcion', 'fecha_inicio', 'fecha_limite', 'progreso', 'asignado']);

                $mapped = $dbTasks->map(function($t) {
                    $formatDate = function($v) {
                        if (!$v) return 'Sin asignar';
                        if ($v instanceof \Carbon\Carbon) return $v->format('Y-m-d');
                        // Admitir strings "YYYY-MM-DD" o similares
                        return trim((string)$v) !== '' ? (string)$v : 'Sin asignar';
                    };

                    $progress = (isset($t->progreso) && is_numeric($t->progreso)) ? intval($t->progreso) : 0;

                    return [
                        'from_db' => true,
                        'tarea' => $t->tarea ?? 'Sin nombre',
                        'descripcion' => $t->descripcion ?? '',
                        'fecha_inicio' => $formatDate($t->fecha_inicio),
                        'fecha_limite' => $formatDate($t->fecha_limite),
                        'asignado' => ($t->asignado && trim((string)$t->asignado) !== '') ? (string)$t->asignado : 'Sin asignar',
                        'progreso' => $progress . '%',
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
            $sharedAccess = SharedMeeting::where('meeting_id', $id)
                ->where('shared_with', $user->id)
                ->where('status', 'accepted')
                ->exists();

            // Intentar buscar primero en transcriptions_laravel
            $meeting = TranscriptionLaravel::where('id', $id)
                ->when(!$sharedAccess, function ($q) use ($user) {
                    $q->where('username', $user->username);
                })
                ->first();

            $isLegacy = true;

            // Si no existe, buscar en meetings (reuniones modernas)
            if (!$meeting) {
                $meeting = Meeting::where('id', $id)
                    ->when(!$sharedAccess, function ($q) use ($user) {
                        $q->where('username', $user->username);
                    })
                    ->firstOrFail();
                $isLegacy = false;
            }

            // Validar request
            $request->validate([
                'sections' => 'required|array',
                'data' => 'required|array',
            ]);

            $data = $request->input('data');
            $sections = $request->input('sections');

            // Datos base de la reunión
            if ($isLegacy) {
                $meetingName = $meeting->meeting_name;
                $realCreatedAt = $meeting->created_at;

                $hasOrganization = $meeting->containers()->exists();
                $organizationName = null;
                $organizationLogo = null;
                if ($hasOrganization) {
                    $container = $meeting->containers()->with('group.organization')->first();
                    if ($container && $container->group && $container->group->organization) {
                        $organizationName = $container->group->organization->name
                            ?? $container->group->organization->nombre_organizacion
                            ?? 'Organización';
                        $organizationLogo = $container->group->organization->imagen ?? null;
                    }
                }
            } else {
                $meetingName = $meeting->title;
                $realCreatedAt = $meeting->created_at ?? $meeting->date;

                $container = MeetingContentContainer::whereHas('meetingRelations', function ($q) use ($meeting) {
                        $q->where('meeting_id', $meeting->id);
                    })
                    ->with('group.organization')
                    ->first();

                $hasOrganization = $container !== null;
                $organizationName = null;
                $organizationLogo = null;
                if ($container && $container->group && $container->group->organization) {
                    $organizationName = $container->group->organization->name
                        ?? $container->group->organization->nombre_organizacion
                        ?? 'Organización';
                    $organizationLogo = $container->group->organization->imagen ?? null;
                }
            }

            // Si se solicitó la sección de tareas, usar siempre las tareas de la BD y mapear a columnas específicas
            if (in_array('tasks', $sections)) {
                $dbTasks = TaskLaravel::where('meeting_id', $meeting->id)
                    ->where('username', $user->username)
                    ->get(['tarea', 'descripcion', 'fecha_inicio', 'fecha_limite', 'progreso', 'asignado']);

                $mapped = $dbTasks->map(function($t) {
                    $formatDate = function($v) {
                        if (!$v) return 'Sin asignar';
                        if ($v instanceof \Carbon\Carbon) return $v->format('Y-m-d');
                        return trim((string)$v) !== '' ? (string)$v : 'Sin asignar';
                    };

                    $progress = (isset($t->progreso) && is_numeric($t->progreso)) ? intval($t->progreso) : 0;

                    return [
                        'from_db' => true,
                        'tarea' => $t->tarea ?? 'Sin nombre',
                        'descripcion' => $t->descripcion ?? '',
                        'fecha_inicio' => $formatDate($t->fecha_inicio),
                        'fecha_limite' => $formatDate($t->fecha_limite),
                        'asignado' => ($t->asignado && trim((string)$t->asignado) !== '') ? (string)$t->asignado : 'Sin asignar',
                        'progreso' => $progress . '%',
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
                    // Si viene marcado como from_db, renderizar directamente columnas de BD
                    if (is_array($task) && isset($task['from_db']) && $task['from_db'] === true) {
                        $nameRaw = (string)($task['tarea'] ?? 'Sin nombre');
                        $descRaw = (string)($task['descripcion'] ?? '');
                        $startRaw = (string)($task['fecha_inicio'] ?? 'Sin asignar');
                        $endRaw = (string)($task['fecha_limite'] ?? 'Sin asignar');
                        $assignedRaw = (string)($task['asignado'] ?? 'Sin asignar');
                        $progressRaw = (string)($task['progreso'] ?? '0%');

                        // Extraer hasta 2 fechas ISO/fecha simple desde la descripción si están embebidas
                        $descTmp = ' ' . $descRaw . ' ';
                        $datePattern = '/\d{4}-\d{2}-\d{2}(?:[T\s]\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?)?/';
                        $matches = [];
                        if (preg_match_all($datePattern, $descTmp, $matches)) {
                            $foundDates = $matches[0];
                            // Normalizar a Y-m-d
                            $norm = function($s) {
                                $ts = @strtotime($s);
                                return $ts ? date('Y-m-d', $ts) : $s;
                            };
                            if (($startRaw === '' || strtolower($startRaw) === 'sin asignar') && isset($foundDates[0])) {
                                $startRaw = $norm($foundDates[0]);
                            }
                            if (($endRaw === '' || strtolower($endRaw) === 'sin asignar') && isset($foundDates[1])) {
                                $endRaw = $norm($foundDates[1]);
                            }
                            // Remover las fechas de la descripción
                            foreach ($foundDates as $d) {
                                $descTmp = preg_replace('/\s*' . preg_quote($d, '/') . '\s*/', ' ', $descTmp, 1);
                            }
                        }
                        // Limpieza de comas/espacios sobrantes
                        $descTmp = trim(preg_replace(['/\s{2,}/', '/\s*,\s*,+/'], [' ', ', '], $descTmp));

                        $tdName = htmlspecialchars($nameRaw);
                        $tdDesc = htmlspecialchars($descTmp);
                        $tdStart = htmlspecialchars($startRaw ?: 'Sin asignar');
                        $tdEnd = htmlspecialchars($endRaw ?: 'Sin asignar');
                        $tdAssigned = htmlspecialchars($assignedRaw ?: 'Sin asignar');
                        $tdProgress = htmlspecialchars($progressRaw ?: '0%');
                        $html .= '\n                            <tr>\n                                <td class="task-name">' . $tdName . '</td>\n                                <td class="task-description">' . $tdDesc . '</td>\n                                <td>' . $tdStart . '</td>\n                                <td>' . $tdEnd . '</td>\n                                <td>' . $tdAssigned . '</td>\n                                <td>' . $tdProgress . '</td>\n                            </tr>';
                        continue;
                    }

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
