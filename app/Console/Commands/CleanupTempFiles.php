<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

class CleanupTempFiles extends Command
{
    protected $signature = 'cleanup:temp-files {--older-than=24 : Hours to consider files old}';
    protected $description = 'Clean up old temporary upload files';

    public function handle()
    {
        $olderThanHours = (int) $this->option('older-than');
        $tempDir = storage_path('app/temp-uploads');

        if (!File::exists($tempDir)) {
            $this->info('No temp directory found.');
            return;
        }

        $cutoffTime = now()->subHours($olderThanHours);
        $files = File::files($tempDir);
        $deletedCount = 0;

        foreach ($files as $file) {
            $fileTime = \Carbon\Carbon::createFromTimestamp(File::lastModified($file->getRealPath()));

            if ($fileTime->lt($cutoffTime)) {
                File::delete($file->getRealPath());
                $deletedCount++;
                $this->info("Deleted: {$file->getFilename()}");
            }
        }

        $this->info("Cleanup completed. Deleted {$deletedCount} files older than {$olderThanHours} hours.");

        Log::info('Temporary files cleanup completed', [
            'deleted_count' => $deletedCount,
            'older_than_hours' => $olderThanHours
        ]);
    }
}
