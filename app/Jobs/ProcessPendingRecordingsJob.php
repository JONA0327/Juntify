<?php

namespace App\Jobs;

use App\Models\PendingRecording;
use App\Models\Notification;
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
        $maxDuration = 2 * 60 * 60; // 2 hours in seconds

        PendingRecording::where('status', PendingRecording::STATUS_PENDING)
            ->each(function (PendingRecording $recording) use ($maxDuration) {
                $recording->update([
                    'status' => PendingRecording::STATUS_PROCESSING,
                    'error_message' => null,
                ]);

                if ($recording->duration !== null && $recording->duration > $maxDuration) {
                    $recording->update([
                        'status' => PendingRecording::STATUS_FAILED,
                        'error_message' => 'Duration exceeds 2 hours',
                    ]);

                    $this->updateNotification($recording, 'Duración excedida', 'failed');
                    return;
                }

                try {
                    // Aquí iría la lógica real de procesamiento de la grabación
                    $recording->update([
                        'status' => PendingRecording::STATUS_COMPLETED,
                    ]);

                    $this->updateNotification($recording, 'Subida completada', 'completed');
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

                    $this->updateNotification(
                        $recording,
                        'Error en la subida',
                        'failed'
                    );
                }
            });
    }

    protected function updateNotification(PendingRecording $recording, string $message, string $status): void
    {
        $user = $recording->user;
        if (! $user) {
            return;
        }

        $notification = Notification::where('emisor', $user->id)
            ->where('type', 'audio_upload')
            ->whereJsonContains('data->pending_recording_id', $recording->id)
            ->first();

        $payload = [
            'remitente' => $user->id,
            'emisor'    => $user->id,
            'status'    => $status,
            'message'   => $message,
            'type'      => 'audio_upload',
            'data'      => [
                'pending_recording_id' => $recording->id,
                'meeting_name'         => $recording->meeting_name,
            ],
        ];

        if ($notification) {
            $notification->update($payload);
        } else {
            Notification::create($payload);
        }
    }
}
