<?php

namespace App\Http\Controllers;

use App\Mail\TaskReactivatedMail;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\TaskLaravel;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use App\Services\GoogleDriveService;
use App\Services\Tasks\TaskAccessService;
use App\Traits\GoogleDriveHelpers;
use App\Traits\MeetingContentParsing;
use Carbon\Carbon;
use App\Services\GoogleCalendarService;
use App\Models\GoogleToken;

class TaskLaravelController extends Controller
{
    use GoogleDriveHelpers, MeetingContentParsing;

    protected $googleDriveService;
    protected TaskAccessService $taskAccessService;

    public function __construct(GoogleDriveService $googleDriveService, TaskAccessService $taskAccessService)
    {
        $this->googleDriveService = $googleDriveService;
        $this->taskAccessService = $taskAccessService;
    }

    protected function scopeVisibleTasks($query, User $user)
    {
        $query->where(function ($q) use ($user) {
            $q->where('username', $user->username)
              ->orWhere('assigned_user_id', $user->id);
        });

        return $this->taskAccessService->applyVisibilityScope($query, $user);
    }

    protected function ensureTaskAccess(TaskLaravel $task, User $user): void
    {
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para ver esta tarea');
        }

        $context = $this->taskAccessService->getContext($user);
        if (!$context->hasAccess()) {
            abort(403, 'No tienes permisos para ver esta tarea');
        }

        if ($context->mode === 'organization_only' && !$this->taskAccessService->taskBelongsToOrganization($task, $context->organizationId)) {
            abort(403, 'No tienes permisos para ver esta tarea');
        }
    }

    protected function mapAssignableUser(User $user, string $source, string $label): array
    {
        $context = $this->taskAccessService->getContext($user);

        $restrictedMessage = null;
        if (!$context->hasAccess()) {
            $restrictedMessage = 'Este usuario no puede ver tareas por su plan.';
        } elseif ($context->mode === 'organization_only') {
            $restrictedMessage = 'Este usuario solo puede ver tareas dentro de su organización.';
        }

        return [
            'id' => $user->id,
            'name' => $user->full_name ?: ($user->username ?: $user->email),
            'email' => $user->email,
            'username' => $user->username,
            'source' => $source,
            'label' => $label,
            'task_access' => $context->mode,
            'restricted_message' => $restrictedMessage,
            'organization_id' => $context->organizationId,
            'organization_role' => $context->organizationRole,
        ];
    }

    protected function normalizeAssignmentStatus(TaskLaravel $task): void
    {
        if (!$task->assigned_user_id) {
            if (!is_null($task->assignment_status)) {
                $task->assignment_status = null;
                $task->save();
            }
            return;
        }

        $desired = $task->progreso >= 100
            ? 'completed'
            : ($task->assignment_status === 'pending' ? 'pending' : 'accepted');

        if ($task->assignment_status !== $desired) {
            $task->assignment_status = $desired;
            $task->save();
        }
    }

    protected function withTaskRelations(TaskLaravel $task): TaskLaravel
    {
        return $task->loadMissing(['assignedUser:id,full_name,email', 'meeting:id,meeting_name']);
    }

    protected function isTaskOverdue(TaskLaravel $task): bool
    {
        if ($task->progreso >= 100) {
            return false;
        }

        if (empty($task->fecha_limite)) {
            return false;
        }

        try {
            $due = $task->fecha_limite instanceof Carbon
                ? $task->fecha_limite->copy()->endOfDay()
                : Carbon::parse($task->fecha_limite)->endOfDay();
        } catch (\Throwable $e) {
            return false;
        }

        return $due->lt(now());
    }

    protected function syncOverdueNotifications(Collection $tasks): void
    {
        $now = now();
        $today = Carbon::today();

        foreach ($tasks as $task) {
            if (!$this->isTaskOverdue($task)) {
                if (!empty($task->overdue_notified_at)) {
                    $task->overdue_notified_at = null;
                    $task->save();
                }
                continue;
            }

            if (empty($task->assigned_user_id)) {
                continue;
            }

            $lastNotification = $task->overdue_notified_at
                ? Carbon::parse($task->overdue_notified_at)
                : null;

            if ($lastNotification && $lastNotification->gte($today)) {
                continue;
            }

            $owner = User::where('username', $task->username)->first();
            $assignee = $task->assignedUser ?: User::find($task->assigned_user_id);

            if (!$owner || !$assignee) {
                continue;
            }

            Notification::create([
                'remitente' => $assignee->id,
                'emisor' => $owner->id,
                'user_id' => $owner->id,
                'from_user_id' => $assignee->id,
                'type' => 'task_overdue_alert',
                'title' => 'Tarea vencida',
                'message' => sprintf(
                    'El usuario %s tiene la tarea "%s" vencida.',
                    $assignee->full_name ?: ($assignee->username ?: $assignee->email),
                    $task->tarea ?? 'Sin título'
                ),
                'status' => 'pending',
                'data' => [
                    'task_id' => $task->id,
                    'meeting_id' => $task->meeting_id,
                    'assignee_id' => $assignee->id,
                ],
                'read' => false,
            ]);

            $task->overdue_notified_at = $now;
            $task->save();
        }
    }
    /**
     * Lista reuniones para importar tareas (misma lógica que reuniones_v2: getMeetings)
     */
    public function meetings(): JsonResponse
    {
        $user = Auth::user();

        $meetings = TranscriptionLaravel::where('username', $user->username)
            ->orderBy('created_at', 'desc')
            ->get(['id', 'meeting_name', 'created_at', 'transcript_drive_id'])
            ->map(function ($m) {
                return [
                    'id' => $m->id,
                    'meeting_name' => $m->meeting_name,
                    'created_at' => $m->created_at->format('d/m/Y H:i'),
                    'has_ju' => !empty($m->transcript_drive_id),
                ];
            });

        return response()->json(['success' => true, 'meetings' => $meetings]);
    }

    /**
     * Descarga y parsea el .ju de la reunión indicada y guarda tareas en tasks_laravel
     */
    public function importFromJu(Request $request, int $meetingId): JsonResponse
    {
        $user = Auth::user();

        $meeting = TranscriptionLaravel::where('id', $meetingId)
            ->where('username', $user->username)
            ->firstOrFail();

        if (empty($meeting->transcript_drive_id)) {
            return response()->json(['success' => false, 'message' => 'La reunión no tiene archivo .ju'], 404);
        }

        // Configurar Drive y descargar/decodificar con traits compartidos
        $this->setGoogleDriveToken($user);
        $content = $this->downloadFromDrive($meeting->transcript_drive_id);
        $res = $this->decryptJuFile($content);
        $data = $this->extractMeetingDataFromJson($res['data'] ?? []);

        $rawTasks = $data['tasks'] ?? [];
        if (!$rawTasks || (is_array($rawTasks) && count($rawTasks) === 0)) {
            return response()->json(['success' => false, 'message' => 'El .ju no contiene tareas']);
        }

        $created = 0; $updated = 0;
        $items = is_array($rawTasks) ? $rawTasks : [$rawTasks];
        foreach ($items as $item) {
            // Parse core fields using robust parser
            $parsed = $this->parseRawTaskForDb($item);

            // Try to capture prioridad and hora if available in raw item
            $prioridad = null; $hora = null;
            if (is_array($item)) {
                $isAssoc = array_keys($item) !== range(0, count($item) - 1);
                if ($isAssoc) {
                    $prioridad = $item['prioridad'] ?? $item['priority'] ?? null;
                    $rawTime = $item['hora'] ?? $item['hora_limite'] ?? $item['time'] ?? $item['due_time'] ?? null;
                    if (is_string($rawTime) && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($rawTime), $tm)) {
                        $h = str_pad($tm[1], 2, '0', STR_PAD_LEFT); $m = $tm[2];
                        $hora = $h . ':' . $m;
                    }
                    // Also parse yyyy-mm-dd hh:mm in end/start
                    foreach (['end','due','due_date','fecha_fin','start','start_date','fecha_inicio'] as $k) {
                        if (!empty($item[$k]) && is_string($item[$k]) && preg_match('/^(\d{4}-\d{2}-\d{2})[ T](\d{1,2}:\d{2})(?::\d{2})?$/', $item[$k], $dm)) {
                            $parsedDate = $dm[1]; $parsedTime = $dm[2];
                            if (empty($parsed['fecha_limite']) && in_array($k, ['end','due','due_date','fecha_fin'])) { $parsed['fecha_limite'] = $parsedDate; }
                            if (empty($parsed['fecha_inicio']) && in_array($k, ['start','start_date','fecha_inicio'])) { $parsed['fecha_inicio'] = $parsedDate; }
                            if ($hora === null) { $hora = strlen($parsedTime) === 4 ? '0'.$parsedTime : $parsedTime; }
                        }
                    }
                } else {
                    // Positional arrays: try to find a time token
                    foreach ($item as $tok) {
                        if (is_string($tok) && preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', trim($tok), $tm)) {
                            $h = str_pad($tm[1], 2, '0', STR_PAD_LEFT); $m = $tm[2];
                            $hora = $h . ':' . $m; break;
                        }
                    }
                }
            } elseif (is_string($item)) {
                if (preg_match('/(\d{1,2}:\d{2})(?::\d{2})?/', $item, $tm)) {
                    $parts = explode(':', $tm[1]);
                    $hora = str_pad($parts[0], 2, '0', STR_PAD_LEFT) . ':' . $parts[1];
                }
            }

            $payload = [
                'username' => $user->username,
                'meeting_id' => $meeting->id,
                'tarea' => substr((string)($parsed['tarea'] ?? 'Sin nombre'), 0, 255),
                'prioridad' => $prioridad ? substr((string)$prioridad, 0, 20) : null,
                'fecha_inicio' => $parsed['fecha_inicio'] ?: null,
                'fecha_limite' => $parsed['fecha_limite'] ?: null,
                'hora_limite' => $hora,
                'descripcion' => $parsed['descripcion'] ?: null,
                'asignado' => $item['assignee'] ?? $item['assigned'] ?? $item['responsable'] ?? null,
                'progreso' => $parsed['progreso'] ?? 0,
            ];

            // upsert por (meeting_id, tarea)
            $existing = TaskLaravel::where('meeting_id', $payload['meeting_id'])
                ->where('tarea', $payload['tarea'])
                ->first();
            if ($existing) {
                $existing->update($payload);
                $updated++;
            } else {
                TaskLaravel::create($payload);
                $created++;
            }
        }

        return response()->json([
            'success' => true,
            'created' => $created,
            'updated' => $updated,
        ]);
    }

    /**
     * Verifica en bloque si existen tareas en tasks_laravel por meeting_id para el usuario actual.
     * Request JSON: { ids: number[] }
     * Response JSON: { success: true, exists: { [id]: boolean } }
     */
    public function exists(Request $request): JsonResponse
    {
        $user = Auth::user();
        $ids = $request->input('ids', []);
        if (!is_array($ids) || empty($ids)) {
            return response()->json(['success' => true, 'exists' => []]);
        }

        $present = TaskLaravel::query()
            ->where('username', $user->username)
            ->whereIn('meeting_id', $ids)
            ->distinct()
            ->pluck('meeting_id')
            ->all();

        $set = array_fill_keys(array_map('intval', $present), true);
        $map = [];
        foreach ($ids as $id) {
            $key = (int) $id;
            $map[$key] = isset($set[$key]);
        }

        return response()->json(['success' => true, 'exists' => $map]);
    }

    /**
     * Lista tareas desde tasks_laravel para el usuario autenticado, opcionalmente filtradas por meeting_id
     * Response JSON: { success, tasks: [], stats: {} }
     */
    public function tasks(Request $request): JsonResponse
    {
        $user = Auth::user();
        $meetingId = $request->query('meeting_id');
        $context = $this->taskAccessService->getContext($user);

        $query = TaskLaravel::query();
        $this->scopeVisibleTasks($query, $user);
        if (!empty($meetingId)) {
            $query->where('meeting_id', (int) $meetingId);
        }

        $tasksQuery = (clone $query)
            ->with(['assignedUser:id,full_name,email', 'meeting:id,meeting_name'])
            ->orderBy('fecha_limite', 'asc')
            ->orderBy('prioridad', 'asc');

        $tasks = $tasksQuery->get([
            'id',
            'username',
            'meeting_id',
            'tarea',
            'prioridad',
            'fecha_inicio',
            'fecha_limite',
            'hora_limite',
            'descripcion',
            'asignado',
            'assigned_user_id',
            'assignment_status',
            'progreso',
            'created_at',
            'updated_at',
            'overdue_notified_at',
        ]);

        $this->syncOverdueNotifications($tasks);

        $today = Carbon::today();
        $statsQuery = clone $query;
        $total = (clone $statsQuery)->count();
        $pending = (clone $statsQuery)->where(function($q){ $q->whereNull('progreso')->orWhere('progreso', 0); })->count();
        $inProgress = (clone $statsQuery)->where('progreso', '>', 0)->where('progreso', '<', 100)->count();
        $completed = (clone $statsQuery)->where('progreso', '>=', 100)->count();
        $overdue = (clone $statsQuery)->where(function($q){ $q->whereNull('progreso')->orWhere('progreso', '<', 100); })
            ->whereDate('fecha_limite', '<', $today)->count();

        $tasksPayload = $tasks->map(function ($task) {
            return [
                'id' => $task->id,
                'username' => $task->username,
                'meeting_id' => $task->meeting_id,
                'meeting_name' => $task->meeting->meeting_name ?? null,
                'tarea' => $task->tarea,
                'prioridad' => $task->prioridad,
                'fecha_inicio' => optional($task->fecha_inicio)->toDateString(),
                'fecha_limite' => optional($task->fecha_limite)->toDateString(),
                'hora_limite' => $task->hora_limite,
                'descripcion' => $task->descripcion,
                'asignado' => $task->asignado,
                'assigned_user_id' => $task->assigned_user_id,
                'assignment_status' => $task->assignment_status,
                'assigned_user' => $task->assignedUser ? [
                    'id' => $task->assignedUser->id,
                    'name' => $task->assignedUser->full_name ?? $task->assignedUser->email,
                    'email' => $task->assignedUser->email,
                ] : null,
                'progreso' => $task->progreso,
                'created_at' => optional($task->created_at)->toDateTimeString(),
                'updated_at' => optional($task->updated_at)->toDateTimeString(),
                'is_overdue' => $this->isTaskOverdue($task),
                'overdue_notified_at' => optional($task->overdue_notified_at)->toDateTimeString(),
            ];
        })->values();

        // Obtener reuniones disponibles para filtrado
        $meetingsQuery = TaskLaravel::query();
        $this->scopeVisibleTasks($meetingsQuery, $user);
        $availableMeetings = $meetingsQuery
            ->with('meeting:id,meeting_name')
            ->whereNotNull('meeting_id')
            ->select('meeting_id')
            ->distinct()
            ->get()
            ->map(function ($task) {
                return [
                    'id' => $task->meeting_id,
                    'name' => $task->meeting->meeting_name ?? 'Sin nombre',
                    'task_count' => null // Se calculará en el frontend
                ];
            })
            ->filter(function ($meeting) {
                return !empty($meeting['name']);
            })
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'success' => true,
            'tasks' => $tasksPayload,
            'meetings' => $availableMeetings,
            'current_meeting_id' => $meetingId,
            'stats' => [
                'total' => $total,
                'pending' => $pending,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'overdue' => $overdue,
            ],
            'access' => [
                'mode' => $context->mode,
                'restrict_approved' => $context->restrictsApprovedColumn(),
                'kanban_locked' => $context->blocksKanban(),
            ],
        ]);
    }

    /**
     * Eventos para el calendario desde tasks_laravel (rango start/end)
     * Output: array de eventos { id, title, start, extendedProps }
     */
    public function calendar(Request $request): JsonResponse
    {
        $user = Auth::user();
        $startStr = $request->query('start');
        $endStr = $request->query('end');

        try {
            $start = $startStr ? Carbon::parse($startStr)->startOfDay() : null;
            $end = $endStr ? Carbon::parse($endStr)->endOfDay() : null;
        } catch (\Exception $e) {
            return response()->json([], 200);
        }

        $q = $this->scopeVisibleTasks(TaskLaravel::query(), $user);
        if ($start && $end) {
            $q->where(function($query) use ($start, $end) {
                // fecha_limite dentro del rango
                $query->whereBetween('fecha_limite', [$start->toDateString(), $end->toDateString()])
                    // o fecha_inicio dentro del rango
                    ->orWhereBetween('fecha_inicio', [$start->toDateString(), $end->toDateString()]);
            });
        }

        $tasks = $q->with('assignedUser:id,full_name,email')->get();
        $today = Carbon::today();
        $events = $tasks->map(function ($t) use ($today) {
            $base = $t->fecha_limite ?: $t->fecha_inicio ?: null;
            $start = $base ? Carbon::parse($base)->toDateString() : null;
            if ($start && $t->hora_limite) {
                $start = Carbon::parse($start . ' ' . $t->hora_limite)->toIso8601String();
            }

            // Status mapping: pending, in_progress, completed, overdue
            $status = 'pending';
            if ($t->progreso >= 100) {
                $status = 'completed';
            } elseif ($t->progreso > 0) {
                $status = 'in_progress';
            }
            try {
                if ($status !== 'completed' && $t->fecha_limite) {
                    $due = Carbon::parse($t->fecha_limite)->endOfDay();
                    if ($due->lt($today)) $status = 'overdue';
                }
            } catch (\Exception $e) {}

            return [
                'id' => $t->id,
                'title' => $t->tarea,
                'start' => $start,
                'extendedProps' => [
                    'description' => $t->descripcion,
                    'status' => $status,
                    'priority' => $t->prioridad,
                    'asignado' => $t->asignado,
                    'assignee' => $t->assignedUser ? ($t->assignedUser->full_name ?? $t->assignedUser->email) : null,
                    'progress' => $t->progreso,
                    'meeting_id' => $t->meeting_id,
                    'hora_limite' => $t->hora_limite,
                ]
            ];
        })->filter(function ($ev) {
            return !empty($ev['start']);
        })->values();

        return response()->json($events);
    }

    /** Mostrar una tarea de tasks_laravel */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::with(['meeting:id,meeting_name', 'assignedUser:id,full_name,username,email'])
            ->where('id', $id)
            ->firstOrFail();

        $this->ensureTaskAccess($task, $user);

        $taskArr = $task->only([
            'id',
            'tarea',
            'prioridad',
            'descripcion',
            'fecha_inicio',
            'fecha_limite',
            'hora_limite',
            'asignado',
            'assigned_user_id',
            'assignment_status',
            'progreso',
        ]);

        // Asegurar formato correcto para fecha límite (YYYY-MM-DD para input HTML5)
        if ($task->fecha_limite) {
            $taskArr['fecha_limite'] = $task->fecha_limite->format('Y-m-d');
        }

        $taskArr['meeting_name'] = $task->meeting->meeting_name ?? null;
        $taskArr['assigned_user'] = $task->assignedUser ? [
            'id' => $task->assignedUser->id,
            'full_name' => $task->assignedUser->full_name,
            'username' => $task->assignedUser->username,
            'email' => $task->assignedUser->email,
            'name' => $task->assignedUser->full_name ?? $task->assignedUser->username ?? $task->assignedUser->email,
        ] : null;
        $taskArr['owner_username'] = $task->username;

        return response()->json(['success' => true, 'task' => $taskArr]);
    }

    /**
     * Aplica el token de Google del usuario autenticado al servicio Calendar.
     */
    protected function applyCalendarToken(GoogleCalendarService $calendar): ?GoogleToken
    {
        $user = Auth::user();
        $token = GoogleToken::where('username', $user->username)
            ->whereNotNull('access_token')
            ->first();
        if (!$token) return null;

        $client = $calendar->getClient();

        // Usar el método del modelo para obtener el token como array completo
        $tokenArray = $token->getTokenArray();
        if (empty($tokenArray['access_token'])) {
            return null;
        }

        $client->setAccessToken($tokenArray);
        if ($client->isAccessTokenExpired() && $token->refresh_token) {
            $new = $client->fetchAccessTokenWithRefreshToken($token->refresh_token);
            if (!isset($new['error'])) {
                $token->update([
                    'access_token' => $new['access_token'],
                    'expiry_date'  => now()->addSeconds($new['expires_in']),
                ]);
                $client->setAccessToken($new);
            }
        }
        return $token;
    }

    /** Crear una nueva tarea en tasks_laravel */
    public function store(Request $request, GoogleCalendarService $calendar): JsonResponse
    {
        $user = Auth::user();
        $data = $request->validate([
            'tarea' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'prioridad' => 'nullable|in:baja,media,alta',
            'fecha_inicio' => 'nullable|date',
            'fecha_limite' => 'nullable|date',
            'hora_limite' => 'nullable|date_format:H:i',
            'progreso' => 'nullable|integer|min:0|max:100',
            'meeting_id' => 'required|exists:transcriptions_laravel,id',
        ]);

        $payload = array_merge($data, [
            'username' => $user->username,
            'prioridad' => $data['prioridad'] ?? null,
            'progreso' => $data['progreso'] ?? 0,
        ]);

        $context = $this->taskAccessService->getContext($user);
        if ($context->mode === 'organization_only'
            && !$this->taskAccessService->meetingBelongsToOrganization((int) $payload['meeting_id'], $context->organizationId)) {
            return response()->json([
                'success' => false,
                'message' => 'Solo puedes crear tareas para reuniones dentro de tu organización.',
            ], 403);
        }

        // upsert safeguard by (meeting_id, tarea)
        $existing = TaskLaravel::where('meeting_id', $payload['meeting_id'])
            ->where('tarea', $payload['tarea'])->first();
        if ($existing) {
            $existing->update($payload);
            $task = $existing;
            $created = false;
        } else {
            $task = TaskLaravel::create($payload);
            $created = true;
        }

        // Intentar sincronizar con Google Calendar si hay fecha
        $this->maybeSyncToCalendar($task, $calendar);

        return response()->json([
            'success' => true,
            'created' => $created,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    /** Actualizar una tarea en tasks_laravel */
    public function update(Request $request, int $id, GoogleCalendarService $calendar): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($id);
        $this->ensureTaskAccess($task, $user);

        $data = $request->validate([
            // Permitir actualizaciones parciales (para Kanban / progreso)
            'tarea' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'prioridad' => 'nullable|in:baja,media,alta',
            'fecha_inicio' => 'nullable|date',
            'fecha_limite' => 'nullable|date',
            'hora_limite' => 'nullable|date_format:H:i',
            'progreso' => 'nullable|integer|min:0|max:100',
        ]);

        $isOwner = $task->username === $user->username;

        if (!$isOwner) {
            if ($task->assignment_status === 'pending') {
                return response()->json(['success' => false, 'message' => 'Debes aceptar la tarea antes de actualizarla'], 403);
            }
            $data = array_intersect_key($data, ['progreso' => true]);
            if (!array_key_exists('progreso', $data)) {
                return response()->json(['success' => false, 'message' => 'Solo puedes actualizar el progreso de la tarea asignada'], 403);
            }

            if ($this->isTaskOverdue($task) && array_key_exists('progreso', $data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta tarea está vencida. Pídele al dueño de la reunión que la reabra antes de actualizarla.',
                ], 423);
            }
        }

        $task->fill($data);
        if (!$this->isTaskOverdue($task)) {
            $task->overdue_notified_at = null;
        }
        $task->save();

        // Sincronizar con Google Calendar en actualizaciones
        $this->maybeSyncToCalendar($task, $calendar);

        $this->normalizeAssignmentStatus($task);

        return response()->json([
            'success' => true,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    /** Eliminar una tarea en tasks_laravel */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($id);
        if ($task->username !== $user->username) {
            abort(403, 'Solo el creador puede eliminar la tarea');
        }
        $task->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Si la tarea tiene fecha (con o sin hora), crear/actualizar evento en Google Calendar.
     * Si no tiene fecha, no crea evento (queda pendiente hasta que se agregue una fecha).
     */
    protected function maybeSyncToCalendar(TaskLaravel $task, GoogleCalendarService $calendar): void
    {
        try {
            // Requiere token válido; si no hay, no hace nada
            if (!$this->applyCalendarToken($calendar)) return;

            $date = $task->fecha_limite ?: $task->fecha_inicio;
            if (!$date) return; // sin fecha, no se agenda todavía

            $calendarId = $task->google_calendar_id ?: 'primary';
            $summary = 'Tarea: ' . $task->tarea;

            // Si hay hora, usar dateTime; si no, evento de todo el día
            if (!empty($task->hora_limite)) {
                $start = Carbon::parse($date->toDateString() . ' ' . $task->hora_limite, config('app.timezone'));
                $end = (clone $start)->addHour();
                $startArr = ['dateTime' => $start->toRfc3339String()];
                $endArr   = ['dateTime' => $end->toRfc3339String()];
            } else {
                $startArr = ['date' => $date->toDateString()];
                // End para all-day debe ser el día siguiente
                $endArr   = ['date' => Carbon::parse($date)->addDay()->toDateString()];
            }

            $eventId = $calendar->upsertEvent($summary, $startArr, $endArr, $calendarId, $task->google_event_id);

            if ($eventId && $eventId !== $task->google_event_id) {
                $task->google_event_id = $eventId;
            }
            $task->calendar_synced_at = now();
            $task->save();
        } catch (\Throwable $e) {
            Log::warning('Calendar sync failed for task '.$task->id.': '.$e->getMessage());
        }
    }

    /** Marcar como completada (progreso=100) */
    public function complete(int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($id);
        $this->ensureTaskAccess($task, $user);
        $isOwner = $task->username === $user->username;
        if (!$isOwner && $this->isTaskOverdue($task)) {
            return response()->json([
                'success' => false,
                'message' => 'Esta tarea está vencida. Pídele al dueño de la reunión que la reabra antes de completarla.',
            ], 423);
        }
        $task->progreso = 100;
        $task->overdue_notified_at = null;
        $task->save();
        $this->normalizeAssignmentStatus($task);
        return response()->json([
            'success' => true,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    /**
     * Enviar solicitud de asignación de tarea a un usuario (contacto/miembro de organización).
     * Crea una notificación para que el usuario acepte o rechace.
     * Request: { user_id?: int, email?: string, username?: string }
     */
    public function assign(Request $request, int $id): JsonResponse
    {
        $owner = Auth::user();
        $task = TaskLaravel::where('id', $id)->where('username', $owner->username)->firstOrFail();
        $task->loadMissing('meeting');

        $data = $request->validate([
            'user_id' => 'nullable|integer|exists:users,id',
            'email' => 'nullable|email',
            'username' => 'nullable|string'
        ]);

        // Resolver destinatario
        $target = null;
        if (!empty($data['user_id'])) {
            $target = User::find($data['user_id']);
        } elseif (!empty($data['email'])) {
            $target = User::where('email', $data['email'])->first();
        } elseif (!empty($data['username'])) {
            $target = User::where('username', $data['username'])->first();
        }
        if (!$target) {
            return response()->json(['success' => false, 'message' => 'Destinatario no encontrado'], 404);
        }

        $eligibility = $this->taskAccessService->userCanReceiveTask($target, $task);
        if (!$eligibility['allowed']) {
            $message = match ($eligibility['reason']) {
                'outside_organization' => 'Esta tarea no pertenece a la organización de este usuario.',
                'organization_missing' => 'Este usuario no tiene una organización activa para trabajar con tareas.',
                default => 'Este usuario no puede ver tareas por su plan.',
            };

            return response()->json([
                'success' => false,
                'message' => $message,
            ], 422);
        }

        $displayName = $target->full_name ?: ($target->username ?: $target->email);
        $task->asignado = $displayName;
        $task->assigned_user_id = $target->id;
        $task->assignment_status = 'pending';
        if ($task->progreso >= 100) {
            $task->progreso = 0;
        }
        $task->overdue_notified_at = null;
        $task->save();

        // Crear notificación (compatibilidad legacy y nueva)
        $notif = Notification::create([
            'remitente' => $owner->id,          // legacy sender
            'emisor'    => $target->id,         // legacy receiver
            'user_id'   => $target->id,         // new schema
            'from_user_id' => $owner->id,       // new schema
            'type'      => 'task_assign_request',
            'title'     => 'Solicitud de asignación de tarea',
            'message'   => sprintf('Te asignaron la tarea "%s" de la reunión "%s".',
                $task->tarea ?? 'Sin título',
                $task->meeting->meeting_name ?? 'Sin nombre'
            ),
            'status'    => 'pending',
            'data'      => [
                'task_id' => $task->id,
                'meeting_id' => $task->meeting_id,
                'task_title' => $task->tarea,
                'owner_username' => $owner->username,
                'meeting_name' => $task->meeting->meeting_name ?? null,
            ],
            'read' => false,
        ]);

        // Enviar notificación por email
        try {
            $message = $request->input('message'); // Mensaje opcional del asignador
            \Mail::to($target->email)->send(new \App\Mail\TaskAssignedMail($task, $owner, $target, $message));
        } catch (\Exception $e) {
            \Log::warning('Failed to send task assignment email', [
                'task_id' => $task->id,
                'target_email' => $target->email,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'notification_id' => $notif->id,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    /** Lista contactos y miembros de organización disponibles para asignar tareas */
    public function assignableUsers(Request $request): JsonResponse
    {
        $user = Auth::user();
        $search = trim($request->query('q', ''));

        // 1. Contactos del usuario (siempre visible)
        $contacts = Contact::with('contact:id,full_name,email,username,roles,plan_code,current_organization_id')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($c) => $c->contact)
            ->map(function ($c) {
                return $this->mapAssignableUser($c->contact, 'contact', '📧 Contacto');
            });

        // 2. Usuarios de la organización actual (prioritario)
        $organizationUsers = collect();
        if (!empty($user->current_organization_id)) {
            $organizationUsers = User::query()
                ->where('id', '!=', $user->id)
                ->where('current_organization_id', $user->current_organization_id)
                ->select('id', 'full_name', 'email', 'username', 'roles', 'plan_code', 'current_organization_id')
                ->get()
                ->map(fn ($orgUser) => $this->mapAssignableUser($orgUser, 'organization', '🏢 Mi Organización'));
        }

        // 3. Usuarios de grupos donde el usuario actual es miembro
        $groupUsers = collect();
        $userGroups = $user->groups()->pluck('groups.id');
        if ($userGroups->isNotEmpty()) {
            $groupUsers = User::query()
                ->whereHas('groups', function ($q) use ($userGroups) {
                    $q->whereIn('groups.id', $userGroups);
                })
                ->where('id', '!=', $user->id)
                ->select('id', 'full_name', 'email', 'username', 'roles', 'plan_code', 'current_organization_id')
                ->get()
                ->map(fn ($groupUser) => $this->mapAssignableUser($groupUser, 'group', '👥 Compañeros de Grupo'));
        }

        // Combinar todos los usuarios conocidos y eliminar duplicados
        $knownUsers = $contacts->concat($organizationUsers)->concat($groupUsers)
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        // 4. Búsqueda en directorio general (solo si hay search)
        $directoryUsers = collect();
        if ($search !== '' && strlen($search) >= 2) {
            $directoryUsers = User::query()
                ->where('id', '!=', $user->id)
                ->whereNotIn('id', $knownUsers->pluck('id')->toArray())
                ->where(function ($q) use ($search) {
                    $q->where('email', 'like', "%{$search}%")
                        ->orWhere('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%");
                })
                ->limit(10)
                ->get(['id', 'full_name', 'email', 'username', 'roles', 'plan_code', 'current_organization_id'])
                ->map(fn ($directoryUser) => $this->mapAssignableUser($directoryUser, 'directory', '🔍 Otros Usuarios'));
        }

        // Filtrar usuarios conocidos si hay búsqueda
        $filteredKnown = $knownUsers;
        if ($search !== '' && strlen($search) >= 1) {
            $filteredKnown = $knownUsers->filter(function ($user) use ($search) {
                return stripos($user['name'], $search) !== false ||
                       stripos($user['email'], $search) !== false ||
                       stripos($user['username'] ?? '', $search) !== false;
            })->values();
        }

        return response()->json([
            'success' => true,
            'users' => $filteredKnown,
            'directory' => $directoryUsers->values(),
            'search_term' => $search,
        ]);
    }

    /**
     * Responder a una solicitud de asignación: accept | reject.
     * Request: { action: 'accept'|'reject', notification_id?: int }
     */
    public function respond(Request $request, int $id, GoogleCalendarService $calendar): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::findOrFail($id);
        if ($task->assigned_user_id !== $user->id) {
            abort(403, 'No puedes responder esta asignación');
        }
        $task->loadMissing('meeting');

        $context = $this->taskAccessService->getContext($user);
        if (!$context->hasAccess()) {
            return response()->json([
                'success' => false,
                'message' => 'Tu plan actual no permite gestionar tareas.',
            ], 403);
        }

        if ($context->mode === 'organization_only'
            && !$this->taskAccessService->taskBelongsToOrganization($task, $context->organizationId)) {
            return response()->json([
                'success' => false,
                'message' => 'No puedes gestionar tareas que están fuera de tu organización.',
            ], 403);
        }

        $data = $request->validate([
            'action' => 'required|in:accept,reject',
            'notification_id' => 'nullable|integer'
        ]);

        // Marcar notificación como resuelta si viene
        if (!empty($data['notification_id'])) {
            try {
                $n = Notification::find($data['notification_id']);
                if ($n && $n->type === 'task_assign_request' && ($n->emisor == $user->id || $n->user_id == $user->id)) {
                    $n->status = $data['action'] === 'accept' ? 'accepted' : 'rejected';
                    $n->read = true; $n->read_at = now();
                    $n->save();
                }
            } catch (\Throwable $e) { /* ignore */ }
        }

        // Notificar al dueño
        $owner = User::where('username', $task->username)->first();

        if ($data['action'] === 'accept') {
            $task->assignment_status = 'accepted';
            $task->assigned_user_id = $user->id;
            $task->asignado = $user->full_name ?: ($user->username ?: $user->email);
            if ($task->progreso >= 100) {
                $task->progreso = 0;
            }
            $task->overdue_notified_at = null;
            $task->save();

            // Intentar crear evento en Calendar si hay fecha/hora
            $this->maybeSyncToCalendar($task, $calendar);

            if ($owner) {
                Notification::create([
                    'remitente' => $user->id,
                    'emisor'    => $owner->id,
                    'user_id'   => $owner->id,
                    'from_user_id' => $user->id,
                    'type'      => 'task_assign_response',
                    'title'     => 'Asignación aceptada',
                    'message'   => sprintf('Acepté la tarea "%s" de la reunión "%s".',
                        $task->tarea ?? '',
                        $task->meeting->meeting_name ?? 'Sin nombre'
                    ),
                    'status'    => 'accepted',
                    'data'      => [ 'task_id' => $task->id ],
                    'read'      => false,
                ]);
            }
        } else {
            // Rechazada
            $task->assignment_status = 'rejected';
            $task->assigned_user_id = null;
            $task->asignado = null;
            $task->overdue_notified_at = null;
            $task->save();

            if ($owner) {
                Notification::create([
                    'remitente' => $user->id,
                    'emisor'    => $owner->id,
                    'user_id'   => $owner->id,
                    'from_user_id' => $user->id,
                    'type'      => 'task_assign_response',
                    'title'     => 'Asignación rechazada',
                    'message'   => sprintf('Rechacé la tarea "%s" de la reunión "%s".',
                        $task->tarea ?? '',
                        $task->meeting->meeting_name ?? 'Sin nombre'
                    ),
                    'status'    => 'rejected',
                    'data'      => [ 'task_id' => $task->id ],
                    'read'      => false,
                ]);
            }
        }

        $this->normalizeAssignmentStatus($task);

        return response()->json([
            'success' => true,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    /**
     * Responder a una asignación de tarea desde el enlace del email (sin autenticación previa)
     * GET /tasks/{task}/respond/{action}?token={user_id}
     */
    public function respondByEmail(Request $request, int $taskId, string $action)
    {
        $task = TaskLaravel::findOrFail($taskId);
        $task->loadMissing('meeting');

        // Validar que la acción sea válida
        if (!in_array($action, ['accept', 'reject'])) {
            abort(400, 'Acción no válida');
        }

        // Verificar token básico (user_id) si está presente
        $userId = $request->query('token');
        if ($userId && $task->assigned_user_id !== $userId) {
            abort(403, 'Token no válido para esta tarea');
        }

        // Si no hay token, mostrar formulario de autenticación
        if (!$userId || !$task->assigned_user_id) {
            return view('tasks.email-response', [
                'task' => $task,
                'action' => $action,
                'actionText' => $action === 'accept' ? 'aceptar' : 'rechazar',
                'needsAuth' => true
            ]);
        }

        $assignedUser = User::find($task->assigned_user_id);
        if (!$assignedUser) {
            abort(404, 'Usuario no encontrado');
        }

        $assignedContext = $this->taskAccessService->getContext($assignedUser);
        if (!$assignedContext->hasAccess()) {
            return view('tasks.email-response', [
                'task' => $task,
                'action' => $action,
                'actionText' => $action === 'accept' ? 'aceptar' : 'rechazar',
                'success' => false,
                'message' => 'Tu plan actual no permite gestionar tareas. Inicia sesión para actualizarlo.',
                'user' => $assignedUser,
            ]);
        }

        if ($assignedContext->mode === 'organization_only'
            && !$this->taskAccessService->taskBelongsToOrganization($task, $assignedContext->organizationId)) {
            return view('tasks.email-response', [
                'task' => $task,
                'action' => $action,
                'actionText' => $action === 'accept' ? 'aceptar' : 'rechazar',
                'success' => false,
                'message' => 'Esta tarea no pertenece a la organización donde tienes acceso a tareas.',
                'user' => $assignedUser,
            ]);
        }

        // Verificar que la tarea esté en estado pending
        if ($task->assignment_status !== 'pending') {
            return view('tasks.email-response', [
                'task' => $task,
                'action' => $action,
                'actionText' => $action === 'accept' ? 'aceptar' : 'rechazar',
                'alreadyResponded' => true,
                'currentStatus' => $task->assignment_status
            ]);
        }

        // Obtener el dueño de la tarea
        $owner = User::where('username', $task->username)->first();

        if ($action === 'accept') {
            $task->assignment_status = 'accepted';
            $task->overdue_notified_at = null;
            $task->save();

            // Crear notificación para el dueño
            if ($owner) {
                Notification::create([
                    'remitente' => $assignedUser->id,
                    'emisor' => $owner->id,
                    'user_id' => $owner->id,
                    'from_user_id' => $assignedUser->id,
                    'type' => 'task_assign_response',
                    'title' => 'Tarea aceptada',
                    'message' => sprintf('%s aceptó la tarea "%s"',
                        $assignedUser->full_name ?: $assignedUser->username,
                        $task->tarea
                    ),
                    'status' => 'accepted',
                    'data' => ['task_id' => $task->id],
                    'read' => false,
                ]);
            }

            $message = 'Has aceptado exitosamente la tarea. Puedes verla en tu panel de tareas en Juntify.';

        } else { // reject
            $reason = $request->query('reason', 'No se especificó motivo');

            $task->assignment_status = 'rejected';
            $task->assigned_user_id = null;
            $task->asignado = null;
            $task->overdue_notified_at = null;
            $task->save();

            // Crear notificación para el dueño
            if ($owner) {
                Notification::create([
                    'remitente' => $assignedUser->id,
                    'emisor' => $owner->id,
                    'user_id' => $owner->id,
                    'from_user_id' => $assignedUser->id,
                    'type' => 'task_assign_response',
                    'title' => 'Tarea rechazada',
                    'message' => sprintf('%s rechazó la tarea "%s". Motivo: %s',
                        $assignedUser->full_name ?: $assignedUser->username,
                        $task->tarea,
                        $reason
                    ),
                    'status' => 'rejected',
                    'data' => ['task_id' => $task->id, 'reason' => $reason],
                    'read' => false,
                ]);

                // Enviar email de rechazo al dueño
                try {
                    \Mail::to($owner->email)->send(new \App\Mail\TaskRejectedMail($task, $owner, $assignedUser, $reason));
                } catch (\Exception $e) {
                    \Log::warning('Failed to send task rejection email', [
                        'task_id' => $task->id,
                        'owner_email' => $owner->email,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = 'Has rechazado la tarea. El dueño ha sido notificado.';
        }

        return view('tasks.email-response', [
            'task' => $task,
            'action' => $action,
            'actionText' => $action === 'accept' ? 'aceptada' : 'rechazada',
            'success' => true,
            'message' => $message,
            'user' => $assignedUser
        ]);
    }

    /** Reactivar una tarea (dueño la reabre y notifica al asignado) */
    public function reactivate(Request $request, int $id): JsonResponse
    {
        $owner = Auth::user();
        $task = TaskLaravel::where('id', $id)->where('username', $owner->username)->firstOrFail();
        $task->loadMissing('meeting');

        $data = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);
        $reason = $data['reason'] ?? null;

        $task->progreso = 0;
        if ($task->assignment_status === 'completed') {
            $task->assignment_status = 'accepted';
        }
        $task->save();

        $this->normalizeAssignmentStatus($task);

        if (!empty($task->assigned_user_id) || !empty($task->asignado)) {
            $assignee = $task->assigned_user_id
                ? User::find($task->assigned_user_id)
                : User::where('full_name', $task->asignado)
                    ->orWhere('username', $task->asignado)
                    ->orWhere('email', $task->asignado)
                    ->first();
            if ($assignee) {
                Notification::create([
                    'remitente' => $owner->id,
                    'emisor'    => $assignee->id,
                    'user_id'   => $assignee->id,
                    'from_user_id' => $owner->id,
                    'type'      => 'task_reactivated',
                    'title'     => 'Tarea reactivada',
                    'message'   => sprintf('Se reactivó la tarea "%s" de la reunión "%s".',
                        $task->tarea ?? '',
                        $task->meeting->meeting_name ?? 'Sin nombre'
                    ),
                    'status'    => 'pending',
                    'data'      => [
                        'task_id' => $task->id,
                        'reason' => $reason,
                    ],
                    'read'      => false,
                ]);

                if (!empty($assignee->email)) {
                    try {
                        Mail::to($assignee->email)->send(new TaskReactivatedMail($task, $owner, $reason));
                    } catch (\Throwable $mailException) {
                        Log::warning('No se pudo enviar correo de tarea reactivada', [
                            'task_id' => $task->id,
                            'error' => $mailException->getMessage(),
                        ]);
                    }
                }
            }
        }

        return response()->json([
            'success' => true,
            'task' => $this->withTaskRelations($task),
        ]);
    }
}
