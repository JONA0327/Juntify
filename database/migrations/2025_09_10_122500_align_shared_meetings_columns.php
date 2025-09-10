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

        $db = DB::getDatabaseName();
        // Drop FKs that reference owner_id or shared_with_id if any
        $cols = ['owner_id', 'shared_with_id'];
        foreach ($cols as $col) {
            $rows = DB::select("SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'shared_meetings' AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL", [$db, $col]);
            foreach ($rows as $r) {
                DB::statement("ALTER TABLE `shared_meetings` DROP FOREIGN KEY `{$r->CONSTRAINT_NAME}`");
            }
        }

        // Rename and convert types using raw SQL to avoid requiring doctrine/dbal
        if (Schema::hasColumn('shared_meetings', 'owner_id') && !Schema::hasColumn('shared_meetings', 'shared_by')) {
            DB::statement("ALTER TABLE `shared_meetings` CHANGE `owner_id` `shared_by` CHAR(36) NOT NULL");
        }
        if (Schema::hasColumn('shared_meetings', 'shared_with_id') && !Schema::hasColumn('shared_meetings', 'shared_with')) {
            DB::statement("ALTER TABLE `shared_meetings` CHANGE `shared_with_id` `shared_with` CHAR(36) NOT NULL");
        }

        // Add FKs to users(id) for the new columns
        if (Schema::hasColumn('shared_meetings', 'shared_by')) {
            try {
                Schema::table('shared_meetings', function (Blueprint $table) {
                    $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        }
        if (Schema::hasColumn('shared_meetings', 'shared_with')) {
            try {
                Schema::table('shared_meetings', function (Blueprint $table) {
                    $table->foreign('shared_with')->references('id')->on('users')->onDelete('cascade');
                });
            } catch (\Throwable $e) {
                // ignore if already exists
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('shared_meetings')) {
            return;
        }
        // Optionally revert names (not necessary)
    }
};
