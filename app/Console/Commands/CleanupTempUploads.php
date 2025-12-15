<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class CleanupTempUploads extends Command
{
    protected $signature = 'audio:cleanup-temp {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up old temporary upload files to free disk space';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $tempDir = storage_path('app/temp-uploads');
        
        if (!is_dir($tempDir)) {
            $this->info('No temp uploads directory found.');
            return 0;
        }

        $cutoffTime = now()->subHours(24); // Archivos más antiguos de 24 horas
        $totalSize = 0;
        $deletedCount = 0;

        $directories = glob($tempDir . '/*', GLOB_ONLYDIR);
        
        foreach ($directories as $dir) {
            $dirTime = filemtime($dir);
            
            if ($dirTime < $cutoffTime->timestamp) {
                $size = $this->getDirectorySize($dir);
                $totalSize += $size;
                
                if ($dryRun) {
                    $this->line("Would delete: " . basename($dir) . " (" . $this->formatBytes($size) . ")");
                } else {
                    $this->removeDirectory($dir);
                    $this->line("Deleted: " . basename($dir) . " (" . $this->formatBytes($size) . ")");
                }
                
                $deletedCount++;
            }
        }

        if ($dryRun) {
            $this->info("Dry run completed. Would delete {$deletedCount} directories totaling " . $this->formatBytes($totalSize));
        } else {
            $this->info("Cleanup completed. Deleted {$deletedCount} directories totaling " . $this->formatBytes($totalSize));
            
            Log::info('Temporary upload cleanup completed', [
                'deleted_count' => $deletedCount,
                'total_size_freed' => $totalSize,
                'cutoff_time' => $cutoffTime->toISOString()
            ]);
        }

        return 0;
    }

    private function getDirectorySize($dir)
    {
        $size = 0;
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $size;
    }

    private function removeDirectory($dir)
    {
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }

    private function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $power), 2) . ' ' . $units[$power];
    }
}