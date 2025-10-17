<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TranscriptionTemp;
use App\Models\TaskLaravel;
use App\Traits\MeetingContentParsing;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProcessTempMeetingTasks extends Command
{
    use MeetingContentParsing;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'temp-meetings:process-tasks {--meeting-id= : Process specific meeting ID} {--dry-run : Show what would be done without making changes}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process temporary meetings and extract tasks to tasks_laravel table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $meetingId = $this->option('meeting-id');
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->info('🔍 DRY RUN MODE - No changes will be made');
        }

        if ($meetingId) {
            // Process specific meeting
            $meeting = TranscriptionTemp::find($meetingId);
            if (!$meeting) {
                $this->error("Meeting with ID {$meetingId} not found");
                return 1;
            }
            $meetings = collect([$meeting]);
            $this->info("Processing specific meeting: {$meeting->title}");
        } else {
            // Process all meetings that have transcription but no tasks in DB
            $meetings = TranscriptionTemp::whereNotNull('transcription_path')
                ->where('transcription_path', '!=', '')
                ->whereDoesntHave('tasks')
                ->notExpired()
                ->get();

            $this->info("Found {$meetings->count()} temporary meetings to process");
        }

        if ($meetings->isEmpty()) {
            $this->info('No meetings to process');
            return 0;
        }

        $processed = 0;
        $errors = 0;
        $tasksCreated = 0;

        foreach ($meetings as $meeting) {
            $this->info("Processing meeting: {$meeting->title} (ID: {$meeting->id})");

            try {
                $result = $this->processMeetingTasks($meeting, $dryRun);

                if ($result['success']) {
                    $processed++;
                    $tasksCreated += $result['tasks_created'];
                    $this->info("  ✅ Success: {$result['tasks_created']} tasks created");
                } else {
                    $errors++;
                    $this->error("  ❌ Error: {$result['message']}");
                }
            } catch (\Exception $e) {
                $errors++;
                $this->error("  ❌ Exception: {$e->getMessage()}");
                Log::error('ProcessTempMeetingTasks error', [
                    'meeting_id' => $meeting->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->info("\n📊 Summary:");
        $this->info("  Processed: {$processed}");
        $this->info("  Errors: {$errors}");
        $this->info("  Tasks created: {$tasksCreated}");

        return 0;
    }

    /**
     * Process tasks for a specific meeting
     */
    private function processMeetingTasks(TranscriptionTemp $meeting, bool $dryRun): array
    {
        // Check if transcription file exists
        if (!$meeting->transcription_path || !Storage::disk('local')->exists($meeting->transcription_path)) {
            return [
                'success' => false,
                'message' => 'Transcription file not found'
            ];
        }

        // Read and decrypt transcription content
        try {
            $content = Storage::disk('local')->get($meeting->transcription_path);
            $result = $this->decryptJuFile($content);
            $data = $this->extractMeetingDataFromJson($result['data'] ?? []);
        } catch (\Exception $e) {
            return [
                'success' => false,
                'message' => 'Failed to decrypt/parse transcription: ' . $e->getMessage()
            ];
        }

        // Extract tasks
        $tasks = $data['tasks'] ?? [];
        if (empty($tasks) || !is_array($tasks)) {
            return [
                'success' => false,
                'message' => 'No tasks found in transcription'
            ];
        }

        $this->info("    Found {" . count($tasks) . "} tasks in transcription");

        if ($dryRun) {
            // Show what would be created
            foreach ($tasks as $index => $task) {
                $taskText = $this->extractTaskText($task);
                $this->info("      [DRY RUN] Would create: {$taskText}");
            }
            return [
                'success' => true,
                'tasks_created' => count($tasks)
            ];
        }

        // Create tasks in database
        $tasksCreated = 0;
        $user = $meeting->user;

        foreach ($tasks as $taskData) {
            try {
                $taskInfo = $this->parseTaskData($taskData);

                if (empty($taskInfo['tarea'])) {
                    continue; // Skip empty tasks
                }

                // Check if task already exists
                $existingTask = TaskLaravel::where('meeting_id', $meeting->id)
                    ->where('meeting_type', 'temporary')
                    ->where('username', $user->username)
                    ->where('tarea', $taskInfo['tarea'])
                    ->first();

                if ($existingTask) {
                    $this->info("      Task already exists: {$taskInfo['tarea']}");
                    continue;
                }

                // Create new task
                TaskLaravel::create([
                    'username' => $user->username,
                    'meeting_id' => $meeting->id,
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
                $this->info("      ✅ Created: {$taskInfo['tarea']}");

            } catch (\Exception $e) {
                $this->error("      ❌ Failed to create task: {$e->getMessage()}");
            }
        }

        return [
            'success' => true,
            'tasks_created' => $tasksCreated
        ];
    }

    /**
     * Extract task text from various task formats
     */
    private function extractTaskText($task): string
    {
        if (is_string($task)) {
            return trim($task);
        }

        if (is_array($task)) {
            return $task['tarea'] ?? $task['text'] ?? $task['title'] ?? $task['name'] ?? 'Unknown task';
        }

        return 'Unknown task format';
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
                'tarea' => trim($taskData)
            ]);
        }

        if (is_array($taskData)) {
            return array_merge($defaults, [
                'tarea' => $taskData['tarea'] ?? $taskData['text'] ?? $taskData['title'] ?? $taskData['name'] ?? '',
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
}
