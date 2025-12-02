<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TranscriptionLaravel;
use App\Http\Controllers\AiAssistantController;

class DebugJuAccess extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'debug:ju-access {meeting_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Debug access to .ju files for specific meetings';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $meetingId = $this->argument('meeting_id');

        $meeting = TranscriptionLaravel::find($meetingId);

        if (!$meeting) {
            $this->error("Meeting with ID {$meetingId} not found");
            return 1;
        }

        $this->info("Meeting ID: {$meeting->id}");
        $this->info("Meeting Name: {$meeting->meeting_name}");
        $this->info("Transcript Drive ID: {$meeting->transcript_drive_id}");
        $this->info("User ID: {$meeting->user_id}");
        $this->info("Created At: {$meeting->created_at}");

        // Verificar si el archivo existe en Google Drive
        if ($meeting->transcript_drive_id) {
            $controller = app(AiAssistantController::class);
            $reflection = new \ReflectionClass($controller);
            $method = $reflection->getMethod('tryDownloadJuContent');
            $method->setAccessible(true);

            try {
                $content = $method->invoke($controller, $meeting);

                if (is_string($content) && !empty($content)) {
                    $this->info("✅ Successfully downloaded .ju file");
                    $this->info("Content length: " . strlen($content) . " bytes");

                    // Intentar parsear el contenido
                    $parseMethod = $reflection->getMethod('decryptJuFile');
                    $parseMethod->setAccessible(true);

                    $parsed = $parseMethod->invoke($controller, $content);
                    $data = $parsed['data'] ?? [];

                    $this->info("Summary available: " . (isset($data['summary']) ? 'Yes' : 'No'));
                    $this->info("Key points count: " . count($data['key_points'] ?? []));
                    $this->info("Tasks count: " . count($data['tasks'] ?? []));
                    $this->info("Segments count: " . count($data['segments'] ?? []));
                } else {
                    $this->error("❌ Failed to download .ju file");
                }
            } catch (\Throwable $e) {
                $this->error("❌ Exception while accessing .ju file: " . $e->getMessage());
            }
        } else {
            $this->error("❌ No transcript_drive_id set for this meeting");
        }

        return 0;
    }
}
