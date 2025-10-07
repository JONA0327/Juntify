<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaskLaravel;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IntegrationDataController extends Controller
{
    public function meetings(Request $request): JsonResponse
    {
        $user = $request->user();

        $meetings = TranscriptionLaravel::query()
            ->where('username', $user->username)
            ->latest('created_at')
            ->limit(25)
            ->get(['id', 'meeting_name', 'created_at']);

        return response()->json([
            'data' => $meetings->map(fn ($meeting) => [
                'id' => $meeting->id,
                'title' => $meeting->meeting_name,
                'created_at' => $meeting->created_at?->toIso8601String(),
                'created_at_readable' => $meeting->created_at?->format('d/m/Y H:i'),
            ])->values(),
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();
        $meetingId = $request->query('meeting_id');

        $tasksQuery = TaskLaravel::query()
            ->with(['meeting:id,meeting_name,created_at'])
            ->where(function ($query) use ($user) {
                $query->where('username', $user->username)
                    ->orWhere('assigned_user_id', $user->id);
            });

        if ($meetingId) {
            $tasksQuery->where('meeting_id', $meetingId);
        }

        $tasks = $tasksQuery
            ->latest('updated_at')
            ->limit(50)
            ->get();

        return response()->json([
            'data' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->tarea,
                'status' => $task->assignment_status ?? 'pendiente',
                'progress' => $task->progreso,
                'starts_at' => $task->fecha_inicio?->toDateString(),
                'due_date' => $task->fecha_limite?->toDateString(),
                'due_time' => $task->hora_limite,
                'assigned_to' => $task->asignado,
                'meeting' => $task->meeting ? [
                    'id' => $task->meeting->id,
                    'title' => $task->meeting->meeting_name,
                    'date' => $task->meeting->created_at?->toIso8601String(),
                ] : null,
            ])->values(),
        ]);
    }

    public function meetingTasks(Request $request, string $meetingId): JsonResponse
    {
        $user = $request->user();

        $meeting = TranscriptionLaravel::query()
            ->where('username', $user->username)
            ->where('id', $meetingId)
            ->first();

        if (!$meeting) {
            return response()->json([
                'message' => 'Reunión no encontrada o sin permisos para consultarla.',
            ], 404);
        }

        $tasks = TaskLaravel::query()
            ->where('meeting_id', $meeting->id)
            ->get();

        return response()->json([
            'meeting' => [
                'id' => $meeting->id,
                'title' => $meeting->meeting_name,
                'created_at' => $meeting->created_at?->toIso8601String(),
            ],
            'tasks' => $tasks->map(fn ($task) => [
                'id' => $task->id,
                'title' => $task->tarea,
                'status' => $task->assignment_status ?? 'pendiente',
                'progress' => $task->progreso,
                'due_date' => $task->fecha_limite?->toDateString(),
                'due_time' => $task->hora_limite,
            ])->values(),
        ]);
    }

    public function searchUsers(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => 'required|string|min:2|max:255',
        ]);

        $term = $validated['query'];

        $users = User::query()
            ->where(function ($query) use ($term) {
                $likeTerm = '%' . $term . '%';

                $query->where('full_name', 'like', $likeTerm)
                    ->orWhere('email', 'like', $likeTerm)
                    ->orWhere('username', 'like', $likeTerm);
            })
            ->orderBy('full_name')
            ->limit(10)
            ->get(['id', 'full_name', 'email', 'username', 'roles']);

        return response()->json([
            'data' => $users->map(fn ($user) => [
                'id' => $user->id,
                'full_name' => $user->full_name,
                'email' => $user->email,
                'username' => $user->username,
                'role' => $user->roles,
            ])->values(),
        ]);
    }
}
