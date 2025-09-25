<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\Subfolder;
use App\Models\OrganizationFolder;
use App\Models\OrganizationSubfolder;
use App\Models\GroupDriveFolder;
use App\Models\TranscriptionLaravel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Database\Eloquent\Model;

class EncryptDriveIds extends Command
{
    protected $signature = 'encrypt:drive-ids {--dry-run : Do not persist changes, only show counts}';
    protected $description = 'Encrypt plaintext Google Drive IDs across folder-related tables and transcriptions_laravel';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $this->info('Encrypting Drive IDs'.($dry ? ' (dry-run)' : '').'...');

        $total = 0;
        $total += $this->process(Folder::query(), ['google_id']);
        $total += $this->process(Subfolder::query(), ['google_id']);
        $total += $this->process(OrganizationFolder::query(), ['google_id']);
        $total += $this->process(OrganizationSubfolder::query(), ['google_id']);
        $total += $this->process(GroupDriveFolder::query(), ['google_id']);
        $total += $this->process(TranscriptionLaravel::query(), ['audio_drive_id','transcript_drive_id']);

        $this->info("Total records updated: {$total}");
        return self::SUCCESS;
    }

    private function process($query, array $fields): int
    {
        $updates = 0;
        $query->chunkById(200, function ($rows) use (&$updates, $fields) {
            foreach ($rows as $row) {
                $changed = false;
                foreach ($fields as $field) {
                    $raw = $row->getRawOriginal($field);
                    if (!empty($raw)) {
                        // If plaintext present, move into encrypted column via setter
                        $row->setAttribute($field, $raw);
                        // Clear plaintext so we stop storing raw IDs
                        $row->setRawAttributes(array_merge($row->getAttributes(), [$field => null]));
                        $changed = true;
                    }
                }
                if ($changed) {
                    $row->save();
                    $updates++;
                }
            }
        });
        return $updates;
    }
}
