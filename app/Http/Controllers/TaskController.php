<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\User;
use App\Models\MeetingContainer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class TaskController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Mostrar la vista principal de tareas
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        // Obtener tareas del usuario autenticado o asignadas a él
        $query = Task::where(function($q) use ($user) {
            $q->where('username', $user->username)
              ->orWhere('assignee', $user->username);
        })->with(['user', 'assignedUser', 'meeting']);

        // Filtros
        if ($request->has('completed') && $request->completed !== 'all') {
            $completed = $request->completed === 'true';
            $query->where('completed', $completed);
        }

        if ($request->has('priority') && $request->priority !== 'all') {
            $query->where('priority', $request->priority);
        }

        if ($request->has('date')) {
            $date = Carbon::parse($request->date);
            $query->whereDate('due_date', $date);
        }

        $tasks = $query->orderBy('due_date', 'asc')
                      ->orderByRaw("FIELD(priority, 'alta', 'media', 'baja')")
                      ->paginate(20);

        // Estadísticas para el dashboard
        $stats = [
            'total' => Task::where(function($q) use ($user) {
                $q->where('username', $user->username)->orWhere('assignee', $user->username);
            })->count(),
            'pending' => Task::where(function($q) use ($user) {
                $q->where('username', $user->username)->orWhere('assignee', $user->username);
            })->where('completed', false)->count(),
            'in_progress' => Task::where(function($q) use ($user) {
                $q->where('username', $user->username)->orWhere('assignee', $user->username);
            })->where('completed', false)->where('progress', '>', 0)->count(),
            'completed' => Task::where(function($q) use ($user) {
                $q->where('username', $user->username)->orWhere('assignee', $user->username);
            })->where('completed', true)->count(),
            'overdue' => Task::where(function($q) use ($user) {
                $q->where('username', $user->username)->orWhere('assignee', $user->username);
            })->overdue()->count(),
        ];

        return view('tasks.index', compact('tasks', 'stats'));
    }

    /**
     * API: Obtener tareas para calendario
     */
    public function getTasks(Request $request)
    {
        $user = Auth::user();

        $query = Task::where(function($q) use ($user) {
            $q->where('username', $user->username)
              ->orWhere('assignee', $user->username);
        })->with(['user', 'assignedUser', 'meeting']);

        if ($request->has('start') && $request->has('end')) {
            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);
            $query->whereBetween('due_date', [$start, $end]);
        }

        $tasks = $query->get();

        // Formatear para FullCalendar
        $events = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->text,
                'start' => $task->due_date ? $task->due_date->toISOString() : null,
                'color' => $this->getEventColor($task),
                'extendedProps' => [
                    'description' => $task->description,
                    'status' => $task->status,
                    'priority' => $task->priority,
                    'assignee' => $task->assignee,
                    'progress' => $task->progress,
                    'meeting_id' => $task->meeting_id,
                ]
            ];
        });

        return response()->json($events);
    }

    /**
     * Mostrar detalles de una tarea específica
     */
    public function show(Task $task)
    {
        $user = Auth::user();

        // Verificar que el usuario pueda ver esta tarea
        if ($task->username !== $user->username && $task->assignee !== $user->username) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $task->load(['user', 'assignedUser', 'meeting']);

        return response()->json($task);
    }

    /**
     * Crear una nueva tarea
     */
    public function store(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'text' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'in:baja,media,alta',
            'assignee' => 'nullable|string|exists:users,username',
            'meeting_id' => 'nullable|exists:meeting_containers,id',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        $task = Task::create([
            'text' => $request->text,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'priority' => $request->priority ?? 'media',
            'username' => $user->username,
            'assignee' => $request->assignee,
            'meeting_id' => $request->meeting_id,
            'progress' => $request->progress ?? 0,
            'completed' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarea creada exitosamente',
            'task' => $task->load(['user', 'assignedUser', 'meeting'])
        ]);
    }

    /**
     * Actualizar una tarea
     */
    public function update(Request $request, Task $task)
    {
        $user = Auth::user();

        // Verificar que el usuario pueda editar esta tarea
        if ($task->username !== $user->username && $task->assignee !== $user->username) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $request->validate([
            'text' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'in:baja,media,alta',
            'assignee' => 'nullable|string|exists:users,username',
            'progress' => 'nullable|integer|min:0|max:100',
            'completed' => 'nullable|boolean',
        ]);

        $task->update($request->only([
            'text', 'description', 'due_date', 'priority',
            'assignee', 'progress', 'completed'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Tarea actualizada exitosamente',
            'task' => $task->load(['user', 'assignedUser', 'meeting'])
        ]);
    }

    /**
     * Eliminar una tarea
     */
    public function destroy(Task $task)
    {
        $user = Auth::user();

        // Verificar que el usuario pueda eliminar esta tarea
        if ($task->username !== $user->username) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $task->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tarea eliminada exitosamente'
        ]);
    }

    /**
     * Marcar tarea como completada
     */
    public function complete(Task $task)
    {
        $user = Auth::user();

        // Verificar que el usuario pueda completar esta tarea
        if ($task->username !== $user->username && $task->assignee !== $user->username) {
            return response()->json(['error' => 'No autorizado'], 403);
        }

        $task->update([
            'completed' => true,
            'progress' => 100
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Tarea marcada como completada',
            'task' => $task->load(['user', 'assignedUser', 'meeting'])
        ]);
    }

    /**
     * Obtener color del evento para el calendario
     */
    private function getEventColor(Task $task)
    {
        if ($task->completed) {
            return '#10b981'; // Verde
        }

        if ($task->is_overdue) {
            return '#ef4444'; // Rojo para vencidas
        }

        return match($task->priority) {
            'alta' => '#f59e0b', // Naranja
            'media' => '#3b82f6', // Azul
            'baja' => '#6b7280', // Gris
            default => '#6b7280'
        };
    }
}
