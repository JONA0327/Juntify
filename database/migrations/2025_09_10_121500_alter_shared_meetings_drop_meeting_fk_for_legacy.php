<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shared_meetings')) {
            return;
        }

        // Drop FK on meeting_id if present, to allow legacy meeting ids (TranscriptionLaravel) without FK
        $db = DB::getDatabaseName();
        $rows = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shared_meetings' AND COLUMN_NAME = 'meeting_id' AND REFERENCED_TABLE_NAME IS NOT NULL", [$db]);
        foreach ($rows as $r) {
            DB::statement("ALTER TABLE `shared_meetings` DROP FOREIGN KEY `{$r->CONSTRAINT_NAME}`");
        }
    }

    public function down(): void
    {
        // No restablecemos la FK automáticamente para no romper datos existentes
    }
};
