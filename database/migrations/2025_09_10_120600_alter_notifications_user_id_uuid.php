<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }

        // Asegurar que notifications.user_id también sea UUID para coherencia con users.id
        $dbName = DB::getDatabaseName();
        $constraints = DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'notifications' AND COLUMN_NAME = 'user_id' AND REFERENCED_TABLE_NAME IS NOT NULL",
            [$dbName]
        );
        foreach ($constraints as $row) {
            DB::statement("ALTER TABLE `notifications` DROP FOREIGN KEY `{$row->CONSTRAINT_NAME}`");
        }

        if (Schema::hasColumn('notifications', 'user_id')) {
            DB::statement("ALTER TABLE `notifications` MODIFY `user_id` char(36) NULL");
        } else {
            Schema::table('notifications', function (Blueprint $table) {
                $table->uuid('user_id')->nullable()->after('id');
            });
        }

        Schema::table('notifications', function (Blueprint $table) {
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications')) {
            return;
        }
        try {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropForeign(['user_id']);
            });
        } catch (\Throwable $e) {}
    }
};
