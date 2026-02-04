<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Meeting;

class MeetingDetailsController extends Controller
{
    /**
     * Obtener detalles completos de una reunión
     */
    public function getDetails(Request $request, $meetingId)
    {
        // Buscar la reunión
        $meeting = Meeting::find($meetingId);

        if (!$meeting) {
            return response()->json([
                'success' => false,
                'message' => 'Reunión no encontrada.'
            ], 404);
        }

        // Verificar permisos (opcional si se pasa user_id)
        $userId = $request->query('user_id');
        $canAccess = true;
        
        if ($userId) {
            // Verificar si el usuario creó la reunión o tiene acceso
            $canAccess = $meeting->user_id === $userId || 
                         $meeting->group_id && DB::table('group_user')
                             ->where('group_id', $meeting->group_id)
                             ->where('user_id', $userId)
                             ->exists();
        }

        if (!$canAccess) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para acceder a esta reunión.'
            ], 403);
        }

        $response = [
            'success' => true,
            'meeting' => [
                'id' => $meeting->id,
                'meeting_name' => $meeting->meeting_name,
                'meeting_date' => $meeting->meeting_date,
                'duration' => $meeting->duration,
                'created_at' => $meeting->created_at,
                'updated_at' => $meeting->updated_at,
                'user_id' => $meeting->user_id,
                'group_id' => $meeting->group_id,
                'organization_id' => $meeting->organization_id
            ]
        ];

        // Obtener contenedor (si existe)
        $container = null;
        if ($meeting->id) {
            $containerRelation = DB::connection('mysql')
                ->table('meeting_content_relations')
                ->where('meeting_id', $meeting->id)
                ->first();

            if ($containerRelation) {
                $container = DB::connection('mysql')
                    ->table('meeting_content_containers')
                    ->where('id', $containerRelation->container_id)
                    ->first();

                if ($container) {
                    $response['container'] = [
                        'id' => $container->id,
                        'name' => $container->name,
                        'description' => $container->description,
                        'folder_id' => $container->folder_id ?? null
                    ];
                }
            }
        }

        if (!$container) {
            $response['container'] = null;
        }

        // Obtener archivo .ju (audio)
        $audioPath = "meetings/{$meetingId}/{$meetingId}.ju";
        $audioExists = Storage::disk('public')->exists($audioPath);
        
        if ($audioExists) {
            $fileSize = Storage::disk('public')->size($audioPath);
            $response['audio_file'] = [
                'filename' => "{$meetingId}.ju",
                'file_path' => Storage::disk('public')->path($audioPath),
                'file_size_bytes' => $fileSize,
                'file_size_mb' => round($fileSize / 1024 / 1024, 2),
                'encrypted' => true,
                'google_drive_file_id' => $meeting->google_drive_file_id ?? null,
                'download_url' => $meeting->google_drive_file_id 
                    ? "https://drive.google.com/file/d/{$meeting->google_drive_file_id}/view"
                    : null
            ];
        } else {
            $response['audio_file'] = null;
        }

        // Obtener transcripción
        $transcription = DB::table('meeting_transcriptions')
            ->where('meeting_id', $meetingId)
            ->first();

        if ($transcription) {
            $response['transcription'] = [
                'id' => $transcription->id,
                'transcription_text' => $transcription->transcription_text ?? $transcription->full_transcription,
                'language' => $transcription->language ?? 'es-MX',
                'confidence_score' => $transcription->confidence ?? null,
                'created_at' => $transcription->created_at
            ];
        } else {
            $response['transcription'] = null;
        }

        // Obtener tareas
        $tasks = DB::table('tasks as t')
            ->leftJoin('users as u', 't.assigned_to', '=', 'u.id')
            ->where('t.meeting_id', $meetingId)
            ->select(
                't.id',
                't.description as task_description',
                't.assigned_to as assigned_to_user_id',
                'u.username as assigned_to_username',
                't.status',
                't.due_date',
                't.priority',
                't.created_at'
            )
            ->orderBy('t.created_at', 'desc')
            ->get();

        $response['tasks'] = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'task_description' => $task->task_description,
                'assigned_to_user_id' => $task->assigned_to_user_id,
                'assigned_to_username' => $task->assigned_to_username,
                'status' => $task->status ?? 'pending',
                'due_date' => $task->due_date,
                'priority' => $task->priority ?? 'medium',
                'created_at' => $task->created_at
            ];
        })->toArray();

        // Permisos del usuario (si user_id está presente)
        if ($userId) {
            $isOwner = $meeting->user_id === $userId;
            
            $response['permissions'] = [
                'can_edit' => $isOwner || $canAccess,
                'can_delete' => $isOwner,
                'can_share' => $isOwner || $canAccess,
                'is_owner' => $isOwner
            ];
        }

        return response()->json($response);
    }
}
