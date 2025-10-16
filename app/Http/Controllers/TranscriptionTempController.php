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
use Illuminate\Support\Str;

class TranscriptionTempController extends Controller
{
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

            $transcriptions = TranscriptionTemp::where('user_id', $user->id)
                ->notExpired()
                ->orderBy('created_at', 'desc')
                ->get();

            // Agregar información adicional para cada transcripción
            $transcriptions->each(function ($transcription) {
                $transcription->time_remaining = $transcription->time_remaining;
                $transcription->is_expired = $transcription->isExpired();
                $transcription->is_temporary = true;
                $transcription->storage_type = 'temp';

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

            if (!Storage::disk('local')->exists($transcription->audio_path)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Archivo de audio no disponible'
                ], 404);
            }

            $fullPath = Storage::disk('local')->path($transcription->audio_path);
            $mime = $transcription->metadata['audio_mime'] ?? 'audio/ogg';

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

            // Eliminar archivos físicos
            if (Storage::disk('local')->exists($transcription->audio_path)) {
                Storage::disk('local')->delete($transcription->audio_path);
            }

            // Eliminar registro
            $transcription->delete();

            Log::info("Transcripción temporal eliminada", [
                'id' => $id,
                'user_id' => $user->id
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
            foreach ($expiredTranscriptions as $transcription) {
                // Eliminar archivos físicos
                if (Storage::disk('local')->exists($transcription->audio_path)) {
                    Storage::disk('local')->delete($transcription->audio_path);
                }

                $transcription->delete();
                $deletedCount++;
            }

            Log::info("Transcripciones temporales expiradas eliminadas", [
                'count' => $deletedCount
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
}
