<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tasks')) {
            return; // Nothing to do if table doesn't exist
        }

        Schema::table('tasks', function (Blueprint $table) {
            // Add username if missing
            if (!Schema::hasColumn('tasks', 'username')) {
                $table->string('username', 255)->nullable()->after('id');
                $table->index('username', 'tasks_username_index');
                // Try to add FK only if users.username exists
                if (Schema::hasTable('users') && Schema::hasColumn('users', 'username')) {
                    // Use a separate statement to avoid errors if FK name collisions
                    $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
                }
            }

            // Ensure assignee exists (some older schemas might miss it)
            if (!Schema::hasColumn('tasks', 'assignee')) {
                $table->string('assignee', 100)->nullable()->after('description');
                $table->index('assignee', 'tasks_assignee_index');
                if (Schema::hasTable('users') && Schema::hasColumn('users', 'username')) {
                    $table->foreign('assignee')->references('username')->on('users')->onDelete('set null');
                }
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('tasks')) {
            return;
        }

        Schema::table('tasks', function (Blueprint $table) {
            // Drop FKs and columns if they exist
            if (Schema::hasColumn('tasks', 'assignee')) {
                // Drop FK if present (ignore errors if the key name differs)
                try { $table->dropForeign(['assignee']); } catch (\Throwable $e) {}
                try { $table->dropIndex('tasks_assignee_index'); } catch (\Throwable $e) {}
                $table->dropColumn('assignee');
            }

            if (Schema::hasColumn('tasks', 'username')) {
                try { $table->dropForeign(['username']); } catch (\Throwable $e) {}
                try { $table->dropIndex('tasks_username_index'); } catch (\Throwable $e) {}
                $table->dropColumn('username');
            }
        });
    }
};
