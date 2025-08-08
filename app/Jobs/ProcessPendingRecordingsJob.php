<?php

namespace App\Jobs;

use App\Models\PendingRecording;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
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
                    $recording->update([
                        'status' => PendingRecording::STATUS_FAILED,
                        'error_message' => $e->getMessage(),
                    ]);
                }
            });
    }
}
