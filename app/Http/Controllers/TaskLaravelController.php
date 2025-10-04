<?php

namespace App\Http\Controllers;

use App\Mail\TaskReactivatedMail;
use App\Models\Contact;
use App\Models\Notification;
use App\Models\SharedMeeting;
use App\Models\TaskLaravel;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use App\Traits\MeetingContentParsing;
use Carbon\Carbon;
use App\Services\GoogleCalendarService;
use App\Models\GoogleToken;

class TaskLaravelController extends Controller
{
    use GoogleDriveHelpers, MeetingContentParsing;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
    }

    protected function scopeVisibleTasks($query, User $user)
    {
        return $query->where(function ($q) use ($user) {
            $q->where('username', $user->username)
              ->orWhere('assigned_user_id', $user->id);
        });
    }

    protected function ensureTaskAccess(TaskLaravel $task, User $user): void
    {
        if ($task->username !== $user->username && $task->assigned_user_id !== $user->id) {
            abort(403, 'No tienes permisos para ver esta tarea');
        }
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
        ]);

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
            ];
        })->values();

        return response()->json([
            'success' => true,
            'tasks' => $tasksPayload,
            'stats' => [
                'total' => $total,
                'pending' => $pending,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'overdue' => $overdue,
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
        $task = TaskLaravel::with(['meeting:id,meeting_name', 'assignedUser:id,full_name,email'])
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
            'name' => $task->assignedUser->full_name ?? $task->assignedUser->email,
            'email' => $task->assignedUser->email,
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
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'asignado' => 'nullable|string',
        ]);

        $existing = TaskLaravel::where('meeting_id', $data['meeting_id'])
            ->where('tarea', $data['tarea'])
            ->first();

        $payload = array_merge($data, [
            'username' => $user->username,
            'prioridad' => $data['prioridad'] ?? null,
            'progreso' => $data['progreso'] ?? 0,
        ]);

        $payload = array_merge($payload, $this->resolveAssignmentPayload($data, $request, $existing, $user));

        // upsert safeguard by (meeting_id, tarea)
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
            'assigned_user_id' => 'nullable|integer|exists:users,id',
            'asignado' => 'nullable|string',
        ]);

        if ($task->username !== $user->username) {
            if ($task->assignment_status === 'pending') {
                return response()->json(['success' => false, 'message' => 'Debes aceptar la tarea antes de actualizarla'], 403);
            }
            if ($request->hasAny(['assigned_user_id', 'asignado'])) {
                return response()->json(['success' => false, 'message' => 'Solo el propietario puede cambiar la asignación'], 403);
            }
            $data = array_intersect_key($data, ['progreso' => true]);
            if (!array_key_exists('progreso', $data)) {
                return response()->json(['success' => false, 'message' => 'Solo puedes actualizar el progreso de la tarea asignada'], 403);
            }
        }

        $payload = $data;
        if ($task->username === $user->username) {
            $payload = array_merge($payload, $this->resolveAssignmentPayload($data, $request, $task, $user));
        }

        $task->update($payload);

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
        $task->update(['progreso' => 100]);
        $this->normalizeAssignmentStatus($task);
        return response()->json([
            'success' => true,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    protected function resolveAssignmentPayload(array $data, Request $request, ?TaskLaravel $task, User $actor): array
    {
        $payload = [];
        $hasAssignedId = array_key_exists('assigned_user_id', $data);
        $hasAsignado = array_key_exists('asignado', $data);

        if ($hasAssignedId) {
            $assigneeId = $data['assigned_user_id'];
            if ($assigneeId) {
                $assignee = User::find($assigneeId);
                if ($assignee) {
                    $payload['assigned_user_id'] = $assignee->id;
                    $payload['asignado'] = $assignee->full_name ?: ($assignee->username ?: $assignee->email);

                    $currentAssignee = $task ? (int) $task->assigned_user_id : null;
                    $isNewAssignee = $currentAssignee !== (int) $assignee->id;

                    if ($isNewAssignee || !$task || empty($task->assignment_status)) {
                        $payload['assignment_status'] = $assignee->id === $actor->id ? 'accepted' : 'pending';
                    }

                    if ($isNewAssignee) {
                        $previousProgress = $request->has('progreso')
                            ? ($data['progreso'] ?? 0)
                            : ($task ? $task->progreso : 0);

                        if ($previousProgress >= 100) {
                            $payload['progreso'] = 0;
                        }
                    }
                }
            } else {
                $payload['assigned_user_id'] = null;
                $payload['assignment_status'] = null;
                $payload['asignado'] = $hasAsignado ? $data['asignado'] : null;
            }
        } elseif ($hasAsignado) {
            $payload['asignado'] = $data['asignado'];
        }

        return $payload;
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

        $displayName = $target->full_name ?: ($target->username ?: $target->email);
        $task->asignado = $displayName;
        $task->assigned_user_id = $target->id;
        $task->assignment_status = 'pending';
        if ($task->progreso >= 100) {
            $task->progreso = 0;
        }
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

        return response()->json([
            'success' => true,
            'notification_id' => $notif->id,
            'task' => $this->withTaskRelations($task),
        ]);
    }

    private const PLATFORM_SEARCH_LIMIT = 10;

    /** Lista contactos, miembros de organización y usuarios con reuniones compartidas disponibles para asignar tareas */
    public function assignableUsers(Request $request): JsonResponse
    {
        $user = Auth::user();

        $data = $request->validate([
            'meeting_id' => 'nullable|integer|exists:transcriptions_laravel,id',
            'query' => 'nullable|string|max:255',
        ]);

        $meetingId = $data['meeting_id'] ?? null;
        $searchTerm = isset($data['query']) ? trim((string) $data['query']) : '';

        $contacts = Contact::with('contact:id,full_name,email')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($c) => $c->contact)
            ->map(function ($c) {
                return [
                    'id' => $c->contact->id,
                    'name' => $c->contact->full_name ?? $c->contact->email,
                    'email' => $c->contact->email,
                    'source' => 'contact',
                ];
            });

        $organizationUsers = collect();
        if (!empty($user->current_organization_id)) {
            $organizationUsers = User::query()
                ->where('id', '!=', $user->id)
                ->where('current_organization_id', $user->current_organization_id)
                ->select('id', 'full_name', 'email')
                ->get()
                ->map(function ($orgUser) {
                    return [
                        'id' => $orgUser->id,
                        'name' => $orgUser->full_name ?? $orgUser->email,
                        'email' => $orgUser->email,
                        'source' => 'organization',
                    ];
                });
        }

        $sharedUsers = collect();
        if ($meetingId) {
            $sharedUserIds = SharedMeeting::query()
                ->where('meeting_id', $meetingId)
                ->where('status', 'accepted')
                ->where(function ($query) use ($user) {
                    $query->where('shared_by', $user->id)
                        ->orWhere('shared_with', $user->id);
                })
                ->get()
                ->flatMap(function (SharedMeeting $shared) use ($user) {
                    $ids = collect();
                    if ($shared->shared_by === $user->id && $shared->shared_with) {
                        $ids->push($shared->shared_with);
                    }
                    if ($shared->shared_with === $user->id && $shared->shared_by) {
                        $ids->push($shared->shared_by);
                    }
                    return $ids;
                })
                ->filter(fn ($id) => !empty($id) && (int) $id !== (int) $user->id)
                ->unique()
                ->values();

            if ($sharedUserIds->isNotEmpty()) {
                $sharedUsers = User::query()
                    ->whereIn('id', $sharedUserIds)
                    ->select('id', 'full_name', 'email')
                    ->get()
                    ->map(function ($sharedUser) {
                        return [
                            'id' => $sharedUser->id,
                            'name' => $sharedUser->full_name ?? $sharedUser->email,
                            'email' => $sharedUser->email,
                            'source' => 'shared',
                        ];
                    });
            }
        }

        $platformUsers = collect();
        if ($searchTerm !== '') {
            $normalized = Str::lower($searchTerm);
            $like = '%' . $normalized . '%';

            $platformUsers = User::query()
                ->where('id', '!=', $user->id)
                ->where(function ($query) use ($like) {
                    $query->whereRaw("LOWER(COALESCE(full_name, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(email, '')) LIKE ?", [$like])
                        ->orWhereRaw("LOWER(COALESCE(username, '')) LIKE ?", [$like]);
                })
                ->orderBy('full_name')
                ->limit(self::PLATFORM_SEARCH_LIMIT)
                ->get(['id', 'full_name', 'email', 'username'])
                ->map(function ($platformUser) {
                    $name = $platformUser->full_name
                        ?? $platformUser->email
                        ?? $platformUser->username;

                    return [
                        'id' => $platformUser->id,
                        'name' => $name,
                        'email' => $platformUser->email,
                        'source' => 'platform',
                        'platform' => 'users',
                    ];
                });
        }

        $combined = $contacts
            ->concat($organizationUsers)
            ->concat($sharedUsers)
            ->concat($platformUsers)
            ->unique('id')
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return response()->json([
            'success' => true,
            'users' => $combined,
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
