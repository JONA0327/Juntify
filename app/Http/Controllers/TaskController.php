<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\TaskLaravel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use App\Services\PlanLimitService;

class TaskController extends Controller
{
    /**
     * Mostrar la vista principal de tareas
     */
    public function index(Request $request)
    {
        // Verificar acceso a tareas basado en plan y organización
        $user = Auth::user();
        $userPlan = $user->plan_code ?? 'free';
        $planService = app(PlanLimitService::class);
        $belongsToOrg = $planService->userBelongsToOrganization($user);
        $hasTasksAccess = $userPlan !== 'free' || $belongsToOrg;

        // Si no tiene acceso, mostrar vista con modal bloqueado
        if (!$hasTasksAccess) {
            return view('tasks.blocked', [
                'userPlan' => $userPlan,
                'belongsToOrganization' => $belongsToOrg
            ]);
        }

        // Detectar si es usuario business (solo acceso a calendario, no tablero)
        $role = strtolower((string) ($user->roles ?? ''));
        $planCode = strtolower((string) ($user->plan_code ?? ''));
        $isBusinessPlan = $role === 'business' || $planCode === 'business' ||
                         $role === 'negocios' || $planCode === 'negocios' ||
                         str_contains($role, 'business') || str_contains($planCode, 'business') ||
                         str_contains($role, 'negocio') || str_contains($planCode, 'negocio');

        $username = $this->getValidatedUsername();

        // Obtener tareas del usuario autenticado o asignadas a él
        $query = Task::where(function($q) use ($username) {
            $q->where('user_id', $username)
              ->orWhere('assignee', $username);
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
            'total' => Task::where(function($q) use ($username) {
                $q->where('user_id', $username)->orWhere('assignee', $username);
            })->count(),
            'pending' => Task::where(function($q) use ($username) {
                $q->where('user_id', $username)->orWhere('assignee', $username);
            })->where('completed', false)->count(),
            'in_progress' => Task::where(function($q) use ($username) {
                $q->where('user_id', $username)->orWhere('assignee', $username);
            })->where('completed', false)->where('progress', '>', 0)->count(),
            'completed' => Task::where(function($q) use ($username) {
                $q->where('user_id', $username)->orWhere('assignee', $username);
            })->where('completed', true)->count(),
            'overdue' => Task::where(function($q) use ($username) {
                $q->where('user_id', $username)->orWhere('assignee', $username);
            })->overdue()->count(),
        ];

        return view('tasks.index', compact('tasks', 'stats', 'isBusinessPlan'));
    }

    /**
     * API: Obtener tareas para calendario
     */
    public function getTasks(Request $request)
    {
        $username = $this->getValidatedUsername();

        // Si se especifica meeting_id, SIEMPRE usar tasks_laravel (tanto para meetings como para transcriptions_laravel)
        if ($request->has('meeting_id')) {
            $meetingId = $request->meeting_id;

            // Usar tasks_laravel para todas las reuniones
            $tasks = \App\Models\TaskLaravel::where('meeting_id', $meetingId)
                ->where('username', $username)
                ->get();

            // Formatear las tareas de tasks_laravel
            $events = $tasks->map(function($task) {
                $status = $task->progreso >= 100 ? 'completed' : ($task->progreso > 0 ? 'in_progress' : 'pending');
                return [
                    'id' => $task->id,
                    'title' => $task->tarea,
                    'text' => $task->tarea, // Para compatibilidad con el panel de tareas
                    'tarea' => $task->tarea,
                    'start' => $task->fecha_limite ? $task->fecha_limite->toISOString() : null,
                    'color' => $this->getEventColorFromProgress($task->progreso),
                    'description' => $task->descripcion,
                    'descripcion' => $task->descripcion,
                    'due_date' => $task->fecha_limite,
                    'fecha_limite' => $task->fecha_limite,
                    'fecha_inicio' => $task->fecha_inicio,
                    'hora_limite' => $task->hora_limite,
                    'priority' => $task->prioridad,
                    'prioridad' => $task->prioridad,
                    'assignee' => $task->asignado,
                    'asignado' => $task->asignado,
                    'progress' => $task->progreso,
                    'progreso' => $task->progreso, // Para compatibilidad
                    'completed' => $task->progreso >= 100,
                    'extendedProps' => [
                        'description' => $task->descripcion,
                        'status' => $status,
                        'priority' => $task->prioridad,
                        'assignee' => $task->asignado,
                        'progress' => $task->progreso,
                        'meeting_id' => $task->meeting_id,
                    ]
                ];
            });

            return response()->json([
                'success' => true,
                'tasks' => $events,
                'stats' => [
                    'total' => $events->count(),
                    'pending' => $events->where('progreso', 0)->count(),
                    'in_progress' => $events->where('progreso', '>', 0)->where('progreso', '<', 100)->count(),
                    'completed' => $events->where('progreso', '>=', 100)->count(),
                ]
            ]);
        }

        $query = Task::where(function($q) use ($username) {
            $q->where('user_id', $username)
              ->orWhere('assignee', $username);
        })->with(['user', 'assignedUser', 'meeting']);

        if ($request->has('start') && $request->has('end')) {
            $start = Carbon::parse($request->start);
            $end = Carbon::parse($request->end);
            $query->whereBetween('due_date', [$start, $end]);
        }

        $tasks = $query->get();

        // Formatear para FullCalendar o para el panel de tareas según el caso
        $events = $tasks->map(function($task) {
            return [
                'id' => $task->id,
                'title' => $task->text,
                'text' => $task->text, // Para compatibilidad con el panel de tareas
                'start' => $task->due_date ? $task->due_date->toISOString() : null,
                'color' => $this->getEventColor($task),
                'description' => $task->description,
                'due_date' => $task->due_date,
                'priority' => $task->priority,
                'assignee' => $task->assignee,
                'progress' => $task->progress,
                'progreso' => $task->progress, // Para compatibilidad
                'completed' => $task->completed,
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

        // Si se está filtrando por meeting_id, devolver formato compatible con el panel de tareas
        if ($request->has('meeting_id')) {
            return response()->json([
                'success' => true,
                'tasks' => $events,
                'stats' => [
                    'total' => $events->count(),
                    'pending' => $events->where('completed', false)->where('progress', 0)->count(),
                    'in_progress' => $events->where('completed', false)->where('progress', '>', 0)->count(),
                    'completed' => $events->where('completed', true)->count(),
                ]
            ]);
        }

        return response()->json($events);
    }

    /**
     * Mostrar detalles de una tarea específica
     */
    public function show(Task $task)
    {
        $username = $this->getValidatedUsername();

        // Verificar que el usuario pueda ver esta tarea
        if ($task->user_id !== $username && $task->assignee !== $username) {
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
        $username = $this->getValidatedUsername();

        $request->validate([
            'text' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'priority' => 'in:baja,media,alta',
            'assignee' => 'nullable|string|exists:users,username',
            'meeting_id' => 'nullable|exists:transcriptions_laravel,id',
            'progress' => 'nullable|integer|min:0|max:100',
        ]);

        $task = Task::create([
            'text' => $request->text,
            'description' => $request->description,
            'due_date' => $request->due_date,
            'priority' => $request->priority ?? 'media',
            'user_id' => $username,
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
        $username = $this->getValidatedUsername();

        // Verificar que el usuario pueda editar esta tarea
        if ($task->user_id !== $username && $task->assignee !== $username) {
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
        $username = $this->getValidatedUsername();

        // Verificar que el usuario pueda eliminar esta tarea
        if ($task->user_id !== $username) {
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
        $username = $this->getValidatedUsername();

        // Verificar que el usuario pueda completar esta tarea
        if ($task->user_id !== $username && $task->assignee !== $username) {
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

    /**
     * Obtener color del evento para tareas de tasks_laravel basado en progreso
     */
    private function getEventColorFromProgress($progreso)
    {
        if ($progreso >= 100) {
            return '#10b981'; // Verde para completadas
        }

        if ($progreso > 0) {
            return '#f59e0b'; // Naranja para en progreso
        }

        return '#6b7280'; // Gris para pendientes
    }

    /**
     * Obtener el nombre de usuario autenticado como cadena.
     */
    private function getValidatedUsername(): string
    {
        $username = Auth::user()?->username;

        if (!is_string($username) || $username === '') {
            abort(403, 'Nombre de usuario inválido');
        }

        return $username;
    }
}
