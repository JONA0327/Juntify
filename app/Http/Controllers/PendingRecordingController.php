<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessPendingRecordingsJob;
use App\Models\PendingRecording;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;

class PendingRecordingController extends Controller
{
    public function process(Request $request): Response
    {
        $user = $request->user();
        if (!in_array($user->roles, ['superadmin', 'developer'])) {
            abort(403, 'No tienes permisos para disparar este proceso');
        }

        ProcessPendingRecordingsJob::dispatch();

        return response()->noContent();
    }

    public function show(Request $request, PendingRecording $pendingRecording): JsonResponse
    {
        if ($pendingRecording->username !== $request->user()->username) {
            abort(403);
        }

        return response()->json([
            'id'            => $pendingRecording->id,
            'status'        => $pendingRecording->status,
            'error_message' => $pendingRecording->error_message,
        ]);
    }

    public function status(Request $request, PendingRecording $pendingRecording): JsonResponse
    {
        $user = $request->user();

        // Verificar que el usuario es el dueño del pending recording
        if ($pendingRecording->user_id !== $user->id) {
            abort(403, 'No tienes permisos para ver este registro');
        }

        // Obtener ID de la reunión/transcripción creada si está completa
        $meetingId = null;
        if ($pendingRecording->status === 'completed') {
            // Buscar en metadata si hay referencia al meeting/transcripción
            $metadata = $pendingRecording->metadata ?? [];

            if (isset($metadata['temp_transcription_id'])) {
                // Es una transcripción temporal
                $meetingId = $metadata['temp_transcription_id'];
            } else {
                // Buscar en TranscriptionLaravel por filename
                $transcription = \App\Models\TranscriptionLaravel::where('filename', $pendingRecording->filename)
                    ->where('username', $user->username)
                    ->orderBy('created_at', 'desc')
                    ->first();

                if ($transcription) {
                    $meetingId = $transcription->id;
                }
            }
        }

        return response()->json([
            'id' => $pendingRecording->id,
            'status' => $pendingRecording->status,
            'meeting_id' => $meetingId,
            'error_message' => $pendingRecording->error_message,
            'is_temporary' => isset($metadata['is_temporary']) ? $metadata['is_temporary'] : false,
        ]);
    }
}
