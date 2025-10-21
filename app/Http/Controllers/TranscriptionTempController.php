<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\TranscriptionTemp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use App\Services\AudioConversionService;
use App\Services\TranscriptionService;
use App\Services\PlanLimitService;
use App\Models\PendingRecording;
use App\Models\TranscriptionLaravel;
use App\Services\GoogleDriveService;
use Illuminate\Support\Str;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\DB;

class TranscriptionTempController extends Controller
{
    use MeetingContentParsing;
    /**
     * Store a new temporary transcription (receives audio file)
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'audioFile' => 'required|file|mimetypes:audio/mpeg,audio/mp3,audio/webm,video/webm,audio/ogg,audio/wav,audio/x-wav,audio/wave,audio/mp4,video/mp4,audio/aac,audio/x-aac,audio/m4a,audio/x-m4a,audio/flac,audio/x-flac,audio/amr,audio/3gpp,audio/3gpp2',
                'meetingName' => 'required|string|max:255',
                'description' => 'nullable|string|max:500',
                'duration' => 'nullable|integer'
            ]);

            $user = Auth::user();
            $audioFile = $request->file('audioFile');

            $planService = app(PlanLimitService::class);
            $planCode = strtolower((string) ($user->plan_code ?? 'free'));
            $role = strtolower((string) ($user->roles ?? 'free'));
            $isBasic = $role === 'basic' || in_array($planCode, ['basic', 'basico'], true) || str_contains($planCode, 'basic');
            $isFree = $role === 'free' || $planCode === 'free' || str_contains($planCode, 'free');

            // Check meeting limits
            $limits = $planService->getLimitsForUser($user);
            if ($limits['max_meetings_per_month'] !== null && $limits['remaining'] !== null && $limits['remaining'] <= 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Has alcanzado el límite mensual de reuniones para tu plan (' . $limits['max_meetings_per_month'] . ' reuniones). Actualiza tu plan para continuar.'
                ], 429);
            }

            $maxSizeMb = null;
            $planLabel = 'tu plan';
            if ($isBasic) {
                $maxSizeMb = 60;
                $planLabel = 'Plan Basic';
            } elseif ($isFree) {
                $maxSizeMb = 50;
                $planLabel = 'Plan Free';
            }

            if ($maxSizeMb !== null && $audioFile->getSize() > $maxSizeMb * 1024 * 1024) {
                return response()->json([
                    'success' => false,
                    'message' => "Los usuarios del {$planLabel} tienen un límite de {$maxSizeMb}MB por archivo"
                ], 413);
            }

            // Guardar archivo de audio temporalmente
            $audioPath = $audioFile->store('temp_audio/' . $user->id, 'local');
            $audioSize = $audioFile->getSize();

            // Generar nombre único para el archivo .ju
            $juBaseName = Str::slug($validated['meetingName'] ?? 'reunion');
            if (!$juBaseName) {
                $juBaseName = 'reunion';
            }
            $juFileName = 'temp_transcriptions/' . $user->id . '/' . uniqid() . '_' . $juBaseName . '.ju';

            $expiresInDays = $planService->getTemporaryRetentionDays($user);
            $expiresAt = Carbon::now()->addDays($expiresInDays);

            // Crear registro temporal
            $transcriptionTemp = TranscriptionTemp::create([
                'user_id' => $user->id,
                'title' => $validated['meetingName'],
                'description' => $validated['description'],
                'audio_path' => $audioPath,
                'transcription_path' => $juFileName,
                'audio_size' => $audioSize,
                'duration' => $validated['duration'],
                'expires_at' => $expiresAt,
                'metadata' => [
                    'original_filename' => $audioFile->getClientOriginalName(),
                    'mime_type' => $audioFile->getMimeType(),
                    'plan_type' => $isBasic ? 'basic_temp' : ($isFree ? 'free_temp' : 'standard_temp'),
                    'storage_type' => 'temp',
                    'retention_days' => $expiresInDays,
                    'storage_reason' => $planService->userCanUseDrive($user) ? 'drive_not_connected' : 'plan_restricted',
                ]
            ]);

            // Increment monthly usage (no decrement when deleted)
            \App\Models\MonthlyMeetingUsage::incrementUsage(
                $user->id,
                $user->current_organization_id,
                [
                    'meeting_id' => $transcriptionTemp->id,
                    'meeting_name' => $validated['meetingName'],
                    'type' => 'temporary'
                ]
            );

            // Crear pending recording para procesamiento
            $pendingRecording = PendingRecording::create([
                'user_id' => $user->id,
                'filename' => $audioFile->getClientOriginalName(),
                'filepath' => $audioPath,
                'status' => 'pending',
                'file_size' => $audioSize,
                'metadata' => [
                    'temp_transcription_id' => $transcriptionTemp->id,
                    'is_temporary' => true,
                    'storage_type' => 'temp',
                    'retention_days' => $expiresInDays,
                ]
            ]);

            Log::info("Transcripción temporal creada y en procesamiento", [
                'user_id' => $user->id,
                'temp_id' => $transcriptionTemp->id,
                'pending_id' => $pendingRecording->id,
                'expires_at' => $expiresAt
            ]);

            return response()->json([
                'success' => true,
                'storage' => 'temp',
                'storage_reason' => $planService->userCanUseDrive($user) ? 'drive_not_connected' : 'plan_restricted',
                'drive_path' => 'Almacenamiento temporal',
                'retention_days' => $expiresInDays,
                'expires_at' => $expiresAt->toIso8601String(),
                'time_remaining' => $transcriptionTemp->time_remaining,
                'data' => $transcriptionTemp,
                'pending_recording' => $pendingRecording->id,
                'message' => sprintf(
                    'Reunión guardada temporalmente. El audio se eliminará automáticamente en %d %s.',
                    $expiresInDays,
                    $expiresInDays === 1 ? 'día' : 'días'
                ),
            ]);

        } catch (\Exception $e) {
            Log::error("Error al guardar transcripción temporal", [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar la reunión temporal'
            ], 500);
        }
    }

    /**
     * Get user's temporary transcriptions with time remaining
     */
    public function index()
    {
        try {
            $user = Auth::user();

            $transcriptions = TranscriptionTemp::with('tasks')
                ->where('user_id', $user->id)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->get();

            // Agregar información adicional para cada transcripción
            $transcriptions->each(function ($transcription) {
                $transcription->time_remaining = $transcription->time_remaining;
                $transcription->is_expired = $transcription->isExpired();
                $transcription->is_temporary = true;
                $transcription->storage_type = 'temp';

                // Mapear title a meeting_name para compatibilidad con frontend
                $transcription->meeting_name = $transcription->title;

                // Merge tasks from database with JSON tasks for compatibility
                $dbTasks = collect();
                if ($transcription->relationLoaded('tasks') && $transcription->tasks) {
                    $dbTasks = collect($transcription->tasks)->map(function($task) {
                        return [
                            'id' => $task->id,
                            'tarea' => $task->tarea,
                            'descripcion' => $task->descripcion,
                            'prioridad' => $task->prioridad,
                            'asignado' => $task->asignado,
                            'fecha_limite' => $task->fecha_limite,
                            'hora_limite' => $task->hora_limite,
                            'progreso' => $task->progreso,
                        ];
                    });
                }

                // Use database tasks if available, otherwise fall back to JSON
                $transcription->tasks_data = $dbTasks->isNotEmpty() ? $dbTasks->toArray() : ($transcription->tasks ?? []);

                // Formatear tamaño del archivo
                if ($transcription->audio_size) {
                    $transcription->formatted_size = $this->formatBytes($transcription->audio_size);
                }
            });

            return response()->json([
                'success' => true,
                'data' => $transcriptions
            ]);

        } catch (\Exception $e) {
            Log::error("Error al obtener transcripciones temporales", [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las reuniones temporales'
            ], 500);
        }
    }

    /**
     * Format bytes to human readable format
     */
    private function formatBytes($size, $precision = 2)
    {
        $units = array('B', 'KB', 'MB', 'GB');
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        return round($size, $precision) . ' ' . $units[$i];
    }

    /**
     * Get a specific temporary transcription
     */
    public function show($id)
    {
        try {
            $user = Auth::user();

            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->notExpired()
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada o expirada'
                ], 404);
            }

            // Merge tasks from database with JSON tasks for compatibility
            $dbTasks = collect();

            // Load tasks manually to avoid eager loading issues
            $taskModels = \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                ->where('meeting_type', 'temporary')
                ->get();

            if ($taskModels->isNotEmpty()) {
                $dbTasks = $taskModels->map(function($task) {
                    return [
                        'id' => $task->id,
                        'tarea' => $task->tarea,
                        'descripcion' => $task->descripcion,
                        'prioridad' => $task->prioridad,
                        'asignado' => $task->asignado,
                        'fecha_limite' => $task->fecha_limite,
                        'hora_limite' => $task->hora_limite,
                        'progreso' => $task->progreso,
                    ];
                });
            }            // Use database tasks if available, otherwise fall back to JSON
            $transcription->tasks_data = $dbTasks->isNotEmpty() ? $dbTasks->toArray() : ($transcription->tasks ?? []);

            // Mapear title a meeting_name para compatibilidad con frontend
            $transcription->meeting_name = $transcription->title;

            return response()->json([
                'success' => true,
                'data' => $transcription
            ]);

        } catch (\Exception $e) {
            Log::error("Error al obtener transcripción temporal", [
                'error' => $e->getMessage(),
                'id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al obtener la reunión temporal'
            ], 500);
        }
    }

    /**
     * Stream the stored temporary audio file for playback
     */
    public function streamAudio($id)
    {
        try {
            $user = Auth::user();
            \Log::info('StreamAudio: Request for ID ' . $id . ' by user ' . $user->id);

            // First try to find as owner
            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->notExpired()
                ->first();

            // If not found as owner, check if it's a shared meeting
            if (!$transcription) {
                $sharedMeeting = \App\Models\SharedMeeting::where('meeting_id', $id)
                    ->where('meeting_type', 'temporary')
                    ->where('shared_with', $user->id)
                    ->where('status', 'accepted')
                    ->first();

                if ($sharedMeeting) {
                    $transcription = TranscriptionTemp::where('id', $id)
                        ->notExpired()
                        ->first();
                }
            }

            if (!$transcription) {
                \Log::warning('StreamAudio: Transcription not found for ID ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada o expirada'
                ], 404);
            }

            \Log::info('StreamAudio: Found transcription, audio_path: ' . $transcription->audio_path);

            if (!Storage::disk('local')->exists($transcription->audio_path)) {
                \Log::error('StreamAudio: Audio file does not exist: ' . $transcription->audio_path);
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de audio no disponible'
                ], 404);
            }

            $fullPath = Storage::disk('local')->path($transcription->audio_path);
            $mime = $transcription->metadata['audio_mime'] ?? 'audio/ogg';

            \Log::info('StreamAudio: Serving file: ' . $fullPath);

            return response()->file($fullPath, [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"'
            ]);
        } catch (\Exception $e) {
            Log::error('Error al transmitir audio temporal', [
                'error' => $e->getMessage(),
                'id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al transmitir el audio temporal'
            ], 500);
        }
    }

    /**
     * Delete a temporary transcription
     */
    public function destroy($id)
    {
        try {
            $user = Auth::user();

            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada'
                ], 404);
            }

            // Eliminar tareas asociadas en tasks_laravel
            $deletedTasksCount = \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                ->where('meeting_type', 'temporary')
                ->where('username', $user->username)
                ->delete();

            // Eliminar archivos físicos
            if (Storage::disk('local')->exists($transcription->audio_path)) {
                Storage::disk('local')->delete($transcription->audio_path);
            }

            // Eliminar registro
            $transcription->delete();

            Log::info("Transcripción temporal eliminada", [
                'id' => $id,
                'user_id' => $user->id,
                'deleted_tasks' => $deletedTasksCount
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Reunión temporal eliminada correctamente'
            ]);

        } catch (\Exception $e) {
            Log::error("Error al eliminar transcripción temporal", [
                'error' => $e->getMessage(),
                'id' => $id,
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la reunión temporal'
            ], 500);
        }
    }

    /**
     * Clean expired temporary transcriptions (for scheduled job)
     */
    public function cleanExpired()
    {
        try {
            $expiredTranscriptions = TranscriptionTemp::expired()->get();

            $deletedCount = 0;
            $deletedTasksCount = 0;
            foreach ($expiredTranscriptions as $transcription) {
                // Eliminar tareas asociadas en tasks_laravel
                $tasksDeleted = \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                    ->where('meeting_type', 'temporary')
                    ->delete();
                $deletedTasksCount += $tasksDeleted;

                // Eliminar archivos físicos
                if (Storage::disk('local')->exists($transcription->audio_path)) {
                    Storage::disk('local')->delete($transcription->audio_path);
                }

                $transcription->delete();
                $deletedCount++;
            }

            Log::info("Transcripciones temporales expiradas eliminadas", [
                'count' => $deletedCount,
                'deleted_tasks' => $deletedTasksCount
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se eliminaron {$deletedCount} transcripciones expiradas"
            ]);

        } catch (\Exception $e) {
            Log::error("Error al limpiar transcripciones expiradas", [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al limpiar transcripciones expiradas'
            ], 500);
        }
    }

    public function updateTasks($id, Request $request)
    {
        try {
            $user = Auth::user();

            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->notExpired()
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada o expirada'
                ], 404);
            }

            $validated = $request->validate([
                'tasks' => 'nullable|array',
                'tasks.*.tarea' => 'nullable|string|max:255',
                'tasks.*.descripcion' => 'nullable|string',
                'tasks.*.prioridad' => 'nullable|string|in:baja,media,alta,urgente',
                'tasks.*.asignado' => 'nullable|string|max:255',
                'tasks.*.fecha_limite' => 'nullable|date',
                'tasks.*.hora_limite' => 'nullable|string',
                'tasks.*.progreso' => 'nullable|integer|between:0,100',
            ]);

            // Primero eliminar tareas existentes para esta reunión temporal
            \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                ->where('meeting_type', 'temporary')
                ->where('username', $user->username)
                ->delete();

            // Crear nuevas tareas en tasks_laravel
            $savedTasks = [];
            foreach ($validated['tasks'] ?? [] as $taskData) {
                if (!empty($taskData['tarea'])) {
                    $task = \App\Models\TaskLaravel::create([
                        'username' => $user->username,
                        'meeting_id' => $transcription->id,
                        'meeting_type' => 'temporary',
                        'tarea' => $taskData['tarea'],
                        'descripcion' => $taskData['descripcion'] ?? null,
                        'prioridad' => $taskData['prioridad'] ?? 'media',
                        'asignado' => $taskData['asignado'] ?? null,
                        'fecha_limite' => $taskData['fecha_limite'] ?? null,
                        'hora_limite' => $taskData['hora_limite'] ?? null,
                        'progreso' => $taskData['progreso'] ?? 0,
                        'assigned_user_id' => null,
                        'assignment_status' => 'pending',
                    ]);
                    $savedTasks[] = $task;
                }
            }

            // También guardar en el JSON del modelo para compatibilidad
            $transcription->tasks = $validated['tasks'] ?? [];
            $transcription->save();

            return response()->json([
                'success' => true,
                'message' => 'Tareas guardadas exitosamente',
                'tasks' => array_map(function($task) {
                    return [
                        'id' => $task->id,
                        'meeting_id' => $task->meeting_id,
                        'meeting_type' => $task->meeting_type,
                        'tarea' => $task->tarea,
                        'descripcion' => $task->descripcion,
                        'prioridad' => $task->prioridad,
                        'asignado' => $task->asignado,
                        'fecha_limite' => $task->fecha_limite,
                        'hora_limite' => $task->hora_limite,
                        'progreso' => $task->progreso,
                    ];
                }, $savedTasks),
                'tasks_count' => count($savedTasks)
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error al actualizar tareas de transcripción temporal', [
                'user_id' => Auth::id(),
                'transcription_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al guardar las tareas: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the name of a temporary meeting
     */
    public function updateName(Request $request, $id)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255',
            ]);

            $user = Auth::user();
            $transcription = TranscriptionTemp::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada'
                ], 404);
            }

            $transcription->update([
                'meeting_name' => $validated['name']
            ]);

            Log::info('Nombre de reunión temporal actualizado', [
                'user_id' => $user->id,
                'transcription_id' => $id,
                'old_name' => $transcription->getOriginal('meeting_name'),
                'new_name' => $validated['name']
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Nombre actualizado correctamente',
                'meeting' => [
                    'id' => $transcription->id,
                    'meeting_name' => $transcription->meeting_name,
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar nombre de transcripción temporal', [
                'user_id' => Auth::id(),
                'transcription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar el nombre'
            ], 500);
        }
    }

    /**
     * Analyze temporary transcription and generate tasks
     */
    public function analyzeAndGenerateTasks($id)
    {
        try {
            $user = Auth::user();

            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->notExpired()
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Reunión temporal no encontrada o expirada'
                ], 404);
            }

            // Check if transcription file exists
            if (!$transcription->transcription_path || !Storage::disk('local')->exists($transcription->transcription_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de transcripción no disponible'
                ], 404);
            }

            // Read and process transcription content
            $content = Storage::disk('local')->get($transcription->transcription_path);

            // Use MeetingContentParsing trait to decrypt and extract data
            $result = $this->decryptJuFile($content);
            $data = $this->extractMeetingDataFromJson($result['data'] ?? []);

            // If no tasks in current data, try to generate them from transcription
            if (empty($data['tasks']) && !empty($data['segments'])) {
                Log::info('No tasks found in transcription, attempting to generate from segments', [
                    'transcription_id' => $id,
                    'segments_count' => count($data['segments'])
                ]);

                // Build transcription text from segments for analysis
                $transcriptionText = '';
                foreach ($data['segments'] as $segment) {
                    $speaker = $segment['speaker'] ?? 'Participante';
                    $text = $segment['text'] ?? '';
                    if (!empty($text)) {
                        $transcriptionText .= "{$speaker}: {$text}\n";
                    }
                }

                if (!empty($transcriptionText)) {
                    // Try to analyze with AI service
                    try {
                        $analysisService = app(\App\Services\OpenAIService::class);
                        $analysisResults = $analysisService->analyzeTranscription($transcriptionText);

                        if (!empty($analysisResults['tasks'])) {
                            $data['tasks'] = $analysisResults['tasks'];
                            Log::info('Generated tasks from AI analysis', [
                                'transcription_id' => $id,
                                'tasks_count' => count($analysisResults['tasks'])
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::warning('AI analysis failed, using manual task extraction', [
                            'transcription_id' => $id,
                            'error' => $e->getMessage()
                        ]);

                        // Fallback: simple keyword-based task extraction
                        $data['tasks'] = $this->extractTasksFromText($transcriptionText);
                    }
                }
            }

            $tasks = $data['tasks'] ?? [];
            if (empty($tasks)) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se pudieron generar tareas a partir de la transcripción'
                ]);
            }

            // Create tasks in database
            $tasksCreated = 0;
            $normalizedTasks = [];
            foreach ($tasks as $taskData) {
                $taskInfo = $this->parseTaskData($taskData);

                if (empty($taskInfo['tarea'])) {
                    continue;
                }

                // Check if task already exists
                $existingTask = \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                    ->where('meeting_type', 'temporary')
                    ->where('username', $user->username)
                    ->where('tarea', $taskInfo['tarea'])
                    ->first();

                if ($existingTask) {
                    continue;
                }

                // Create new task
                $createdTask = \App\Models\TaskLaravel::create([
                    'username' => $user->username,
                    'meeting_id' => $transcription->id,
                    'meeting_type' => 'temporary',
                    'tarea' => $taskInfo['tarea'],
                    'descripcion' => $taskInfo['descripcion'],
                    'prioridad' => $taskInfo['prioridad'],
                    'asignado' => $taskInfo['asignado'],
                    'fecha_limite' => $taskInfo['fecha_limite'],
                    'hora_limite' => $taskInfo['hora_limite'],
                    'progreso' => $taskInfo['progreso'],
                    'assigned_user_id' => null,
                    'assignment_status' => 'pending',
                ]);

                $tasksCreated++;

                $normalizedTasks[] = [
                    'id' => $createdTask->id,
                    'tarea' => $createdTask->tarea,
                    'descripcion' => $createdTask->descripcion,
                    'prioridad' => $createdTask->prioridad,
                    'asignado' => $createdTask->asignado,
                    'fecha_limite' => $createdTask->fecha_limite,
                    'hora_limite' => $createdTask->hora_limite,
                    'progreso' => $createdTask->progreso,
                ];
            }

            // Guardar representación normalizada en el JSON para compatibilidad con vistas antiguas
            if (!empty($normalizedTasks)) {
                $transcription->tasks = array_map(function ($task) {
                    return [
                        'tarea' => $task['tarea'],
                        'descripcion' => $task['descripcion'],
                        'prioridad' => $task['prioridad'],
                        'asignado' => $task['asignado'],
                        'fecha_limite' => $task['fecha_limite'],
                        'hora_limite' => $task['hora_limite'],
                        'progreso' => $task['progreso'],
                    ];
                }, $normalizedTasks);
                $transcription->save();
            }

            Log::info('Tasks generated for temporary transcription', [
                'transcription_id' => $id,
                'tasks_created' => $tasksCreated
            ]);

            return response()->json([
                'success' => true,
                'message' => "Se generaron {$tasksCreated} tareas exitosamente",
                'tasks_created' => $tasksCreated
            ]);

        } catch (\Exception $e) {
            Log::error('Error analyzing temporary transcription', [
                'transcription_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al analizar la transcripción: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extract tasks from text using keyword-based approach
     */
    private function extractTasksFromText($text): array
    {
        $tasks = [];
        $lines = explode("\n", $text);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            // Look for task-related keywords
            if (preg_match('/\b(tarea|task|acción|action|pendiente|todo|debe|should|necesita|need|realizar|do|hacer|make)\b/i', $line)) {
                // Remove speaker prefix
                $cleanLine = preg_replace('/^[^:]+:\s*/', '', $line);
                if (strlen($cleanLine) > 10 && strlen($cleanLine) < 200) {
                    $tasks[] = $cleanLine;
                }
            }
        }

        // Limit to reasonable number of tasks
        return array_slice($tasks, 0, 10);
    }

    /**
     * Parse task data into standardized format
     */
    private function parseTaskData($taskData): array
    {
        $defaults = [
            'tarea' => '',
            'descripcion' => null,
            'prioridad' => 'media',
            'asignado' => null,
            'fecha_limite' => null,
            'hora_limite' => null,
            'progreso' => 0,
        ];

        if (is_string($taskData)) {
            return array_merge($defaults, [
                'tarea' => substr(trim($taskData), 0, 255)
            ]);
        }

        if (is_array($taskData)) {
            return array_merge($defaults, [
                'tarea' => substr($taskData['tarea'] ?? $taskData['text'] ?? $taskData['title'] ?? $taskData['name'] ?? '', 0, 255),
                'descripcion' => $taskData['descripcion'] ?? $taskData['description'] ?? $taskData['desc'] ?? null,
                'prioridad' => $taskData['prioridad'] ?? $taskData['priority'] ?? 'media',
                'asignado' => $taskData['asignado'] ?? $taskData['assigned'] ?? $taskData['assignee'] ?? null,
                'fecha_limite' => $taskData['fecha_limite'] ?? $taskData['due_date'] ?? $taskData['deadline'] ?? null,
                'hora_limite' => $taskData['hora_limite'] ?? $taskData['due_time'] ?? null,
                'progreso' => $taskData['progreso'] ?? $taskData['progress'] ?? 0,
            ]);
        }

        return $defaults;
    }

    /**
     * Export temporary transcription to Google Drive
     */
    public function exportToDrive(Request $request, $id)
    {
        try {
            $user = Auth::user();
            
            // Check if user can use Drive
            $planService = app(PlanLimitService::class);
            if (!$planService->userCanUseDrive($user)) {
                return response()->json([
                    'success' => false,
                    'show_upgrade_modal' => true,
                    'message' => 'Tu plan actual no incluye almacenamiento en Google Drive. Actualiza a Plan Business o superior para acceder a esta funcionalidad.'
                ], 403);
            }

            // Get the temporary transcription
            $transcription = TranscriptionTemp::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (!$transcription) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transcripción temporal no encontrada.'
                ], 404);
            }

            // Get drive type preference
            $driveType = $request->input('drive_type', 'personal'); // 'personal' or 'organization'
            
            // Initialize Drive service
            $driveService = app(GoogleDriveService::class);
            
            // Determine target folder based on drive type
            $targetParentId = null;
            if ($driveType === 'organization' && $user->current_organization_id) {
                $org = $user->currentOrganization;
                if ($org && $org->drive_folder_id) {
                    $targetParentId = $org->drive_folder_id;
                }
            }
            
            if (!$targetParentId) {
                // Use user's personal drive folder
                $targetParentId = $user->drive_folder_id;
            }

            if (!$targetParentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se encontró carpeta de destino en Google Drive. Configura tu integración con Drive primero.'
                ], 400);
            }

            // Prepare tasks already stored in the temporal meeting so we can preserve their IDs
            $existingTaskModels = \App\Models\TaskLaravel::where('meeting_id', $transcription->id)
                ->where('meeting_type', 'temporary')
                ->get();

            $normalizedTaskPayloads = [];
            if ($existingTaskModels->isNotEmpty()) {
                $normalizedTaskPayloads = $existingTaskModels->map(static function ($task) {
                    return [
                        'tarea' => $task->tarea,
                        'descripcion' => $task->descripcion,
                        'prioridad' => $task->prioridad,
                        'asignado' => $task->asignado,
                        'fecha_inicio' => optional($task->fecha_inicio)->format('Y-m-d'),
                        'fecha_limite' => optional($task->fecha_limite)->format('Y-m-d'),
                        'hora_limite' => $task->hora_limite,
                        'progreso' => $task->progreso,
                    ];
                })->values()->all();
            } elseif (is_array($transcription->tasks)) {
                foreach ($transcription->tasks as $taskData) {
                    if (empty($taskData)) {
                        continue;
                    }

                    if (is_object($taskData)) {
                        $taskData = (array) $taskData;
                    }

                    $parsedTask = $this->parseTaskData($taskData);
                    if (!empty($parsedTask['tarea'])) {
                        $normalizedTaskPayloads[] = $parsedTask;
                    }
                }
            }

            // Create Audio and Transcriptions folders if they don't exist
            $audioFolderId = $driveService->createFolderIfNotExists('Audios', $targetParentId);
            $transcriptionsFolderId = $driveService->createFolderIfNotExists('Transcripciones', $targetParentId);

            $audioFileId = null;
            $transcriptionFileId = null;

            $audioStoragePath = $transcription->audio_path;
            $juStoragePath = $transcription->ju_path ?? $transcription->transcription_path;

            // Upload audio file to Drive if exists
            if ($audioStoragePath && Storage::exists($audioStoragePath)) {
                try {
                    $audioContent = Storage::get($audioStoragePath);
                    $audioFileName = $transcription->title . '_audio.' . pathinfo($audioStoragePath, PATHINFO_EXTENSION);

                    $audioFileId = $driveService->uploadFile(
                        $audioFileName,
                        $audioContent,
                        'audio/*',
                        $audioFolderId
                    );
                    
                    Log::info('Audio uploaded to Drive', [
                        'temp_id' => $transcription->id,
                        'audio_file_id' => $audioFileId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to upload audio to Drive', [
                        'temp_id' => $transcription->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Upload .ju file to Drive if exists
            if ($juStoragePath && Storage::exists($juStoragePath)) {
                try {
                    $juContent = Storage::get($juStoragePath);
                    $juFileName = $transcription->title . '.ju';

                    $transcriptionFileId = $driveService->uploadFile(
                        $juFileName,
                        $juContent,
                        'application/json',
                        $transcriptionsFolderId
                    );
                    
                    Log::info('Transcription uploaded to Drive', [
                        'temp_id' => $transcription->id,
                        'transcription_file_id' => $transcriptionFileId
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to upload transcription to Drive', [
                        'temp_id' => $transcription->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Create permanent meeting record in TranscriptionLaravel and preserve/update tasks
            $permanentMeeting = DB::transaction(function () use (
                $user,
                $driveType,
                $transcription,
                $audioFileId,
                $transcriptionFileId,
                $normalizedTaskPayloads,
                $existingTaskModels
            ) {
                $meeting = TranscriptionLaravel::create([
                    'user_id' => $user->id,
                    'organization_id' => $driveType === 'organization' ? $user->current_organization_id : null,
                    'title' => $transcription->title,
                    'description' => $transcription->description,
                    'audio_drive_id' => $audioFileId,
                    'transcript_drive_id' => $transcriptionFileId,
                    'summary' => $transcription->summary,
                    'key_points' => $transcription->key_points,
                    'tasks' => $normalizedTaskPayloads,
                    'duration' => $transcription->duration,
                    'speaker_count' => $transcription->speaker_count,
                    'status' => 'completed',
                    'created_at' => $transcription->created_at,
                    'updated_at' => now(),
                ]);

                if ($existingTaskModels->isNotEmpty()) {
                    foreach ($existingTaskModels as $taskModel) {
                        $taskModel->meeting_id = $meeting->id;
                        $taskModel->meeting_type = 'permanent';
                        $taskModel->save();
                    }
                } elseif (!empty($normalizedTaskPayloads)) {
                    foreach ($normalizedTaskPayloads as $taskData) {
                        \App\Models\TaskLaravel::create([
                            'username' => $user->username,
                            'meeting_id' => $meeting->id,
                            'meeting_type' => 'permanent',
                            'tarea' => $taskData['tarea'],
                            'descripcion' => $taskData['descripcion'] ?? null,
                            'prioridad' => $taskData['prioridad'] ?? 'media',
                            'asignado' => $taskData['asignado'] ?? null,
                            'fecha_inicio' => $taskData['fecha_inicio'] ?? null,
                            'fecha_limite' => $taskData['fecha_limite'] ?? null,
                            'hora_limite' => $taskData['hora_limite'] ?? null,
                            'progreso' => $taskData['progreso'] ?? 0,
                            'assigned_user_id' => null,
                            'assignment_status' => 'pending',
                        ]);
                    }
                }

                return $meeting;
            });

            // Delete temporary files from storage
            if ($audioStoragePath && Storage::exists($audioStoragePath)) {
                Storage::delete($audioStoragePath);
            }
            if ($juStoragePath && Storage::exists($juStoragePath)) {
                Storage::delete($juStoragePath);
            }

            // Delete temporary transcription record
            $transcription->delete();

            return response()->json([
                'success' => true,
                'message' => 'Reunión exportada exitosamente a Google Drive y eliminada del almacenamiento temporal.',
                'permanent_meeting_id' => $permanentMeeting->id,
                'audio_drive_id' => $audioFileId,
                'transcription_drive_id' => $transcriptionFileId,
                'drive_type' => $driveType
            ]);

        } catch (\Exception $e) {
            Log::error('Error exporting temp transcription to Drive', [
                'temp_id' => $id,
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error al exportar a Google Drive: ' . $e->getMessage()
            ], 500);
        }
    }
}
