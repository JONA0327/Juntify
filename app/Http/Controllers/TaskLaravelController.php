<?php

namespace App\Http\Controllers;

use App\Models\TaskLaravel;
use App\Models\TranscriptionLaravel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\JsonResponse;
use App\Services\GoogleDriveService;
use App\Traits\GoogleDriveHelpers;
use App\Traits\MeetingContentParsing;
use Carbon\Carbon;

class TaskLaravelController extends Controller
{
    use GoogleDriveHelpers, MeetingContentParsing;

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        $this->googleDriveService = $googleDriveService;
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

        $query = TaskLaravel::query()->where('username', $user->username);
        if (!empty($meetingId)) {
            $query->where('meeting_id', (int) $meetingId);
        }

        // Obtener tareas
            $tasks = $query->orderBy('fecha_limite', 'asc')
                ->orderBy('prioridad', 'asc')
                ->get(['id','username','meeting_id','tarea','prioridad','fecha_inicio','fecha_limite','hora_limite','descripcion','asignado','progreso','created_at','updated_at']);

        $today = Carbon::today();
        $total = (clone $query)->count();
        $pending = (clone $query)->where(function($q){ $q->whereNull('progreso')->orWhere('progreso', 0); })->count();
        $inProgress = (clone $query)->where('progreso', '>', 0)->where('progreso', '<', 100)->count();
        $completed = (clone $query)->where('progreso', '>=', 100)->count();
        $overdue = (clone $query)->where(function($q){ $q->whereNull('progreso')->orWhere('progreso', '<', 100); })
            ->whereDate('fecha_limite', '<', $today)->count();

        return response()->json([
            'success' => true,
            'tasks' => $tasks,
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

        $q = TaskLaravel::query()->where('username', $user->username);
        if ($start && $end) {
            $q->where(function($query) use ($start, $end) {
                // fecha_limite dentro del rango
                $query->whereBetween('fecha_limite', [$start->toDateString(), $end->toDateString()])
                    // o fecha_inicio dentro del rango
                    ->orWhereBetween('fecha_inicio', [$start->toDateString(), $end->toDateString()]);
            });
        }

        $tasks = $q->get();
        $events = $tasks->map(function ($t) {
            $base = $t->fecha_limite ?: $t->fecha_inicio ?: null;
            $start = $base ? Carbon::parse($base)->toDateString() : null;
            if ($start && $t->hora_limite) {
                $start = Carbon::parse($start . ' ' . $t->hora_limite)->toIso8601String();
            }
            return [
                'id' => $t->id,
                'title' => $t->tarea,
                'start' => $start,
                'extendedProps' => [
                    'description' => $t->descripcion,
                    'status' => ($t->progreso >= 100 ? 'completed' : ($t->progreso > 0 ? 'in_progress' : 'pending')),
                    'priority' => $t->prioridad,
                        'asignado' => $t->asignado,
                    'assignee' => null,
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
        $task = TaskLaravel::where('id', $id)->where('username', $user->username)->firstOrFail();
        return response()->json(['success' => true, 'task' => $task]);
    }

    /** Crear una nueva tarea en tasks_laravel */
    public function store(Request $request): JsonResponse
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

        return response()->json(['success' => true, 'created' => $created, 'task' => $task]);
    }

    /** Actualizar una tarea en tasks_laravel */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::where('id', $id)->where('username', $user->username)->firstOrFail();

        $data = $request->validate([
            'tarea' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'prioridad' => 'nullable|in:baja,media,alta',
            'fecha_inicio' => 'nullable|date',
            'fecha_limite' => 'nullable|date',
            'hora_limite' => 'nullable|date_format:H:i',
            'progreso' => 'nullable|integer|min:0|max:100',
        ]);

        $task->update($data);

        return response()->json(['success' => true, 'task' => $task]);
    }

    /** Eliminar una tarea en tasks_laravel */
    public function destroy(int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::where('id', $id)->where('username', $user->username)->firstOrFail();
        $task->delete();
        return response()->json(['success' => true]);
    }

    /** Marcar como completada (progreso=100) */
    public function complete(int $id): JsonResponse
    {
        $user = Auth::user();
        $task = TaskLaravel::where('id', $id)->where('username', $user->username)->firstOrFail();
        $task->update(['progreso' => 100]);
        return response()->json(['success' => true, 'task' => $task]);
    }
}
