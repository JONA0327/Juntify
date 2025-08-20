<?php

namespace App\Jobs;

use App\Models\PendingRecording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessPendingRecordingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        PendingRecording::where('status', PendingRecording::STATUS_PENDING)
            ->each(function (PendingRecording $recording) {
                $recording->update([
                    'status' => PendingRecording::STATUS_PROCESSING,
                    'error_message' => null,
                ]);

                try {
                    // Aquí iría la lógica real de procesamiento de la grabación
                    $recording->update([
                        'status' => PendingRecording::STATUS_COMPLETED,
                    ]);
                } catch (Throwable $e) {
                    $backupPath = null;
                    try {
                        $relativePath = "failed-audio/{$recording->id}.webm";
                        $audio = file_get_contents($recording->audio_download_url);
                        Storage::put($relativePath, $audio);
                        $backupPath = Storage::path($relativePath);
                        Log::info('Backup saved for pending recording', [
                            'recording_id' => $recording->id,
                            'path' => $backupPath,
                        ]);
                    } catch (Throwable $downloadException) {
                        Log::warning('Failed to backup pending recording', [
                            'recording_id' => $recording->id,
                            'exception' => $downloadException->getMessage(),
                        ]);
                    }

                    $recording->update([
                        'status' => PendingRecording::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                        'backup_path' => $backupPath,
                    ]);
                }
            });
    }
}
