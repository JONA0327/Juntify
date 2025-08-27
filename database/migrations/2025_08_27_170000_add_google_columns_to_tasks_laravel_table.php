<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_laravel', 'google_event_id')) {
                $table->string('google_event_id')->nullable()->after('progreso');
            }
            if (!Schema::hasColumn('tasks_laravel', 'google_calendar_id')) {
                $table->string('google_calendar_id')->nullable()->after('google_event_id');
            }
            if (!Schema::hasColumn('tasks_laravel', 'calendar_synced_at')) {
                $table->timestamp('calendar_synced_at')->nullable()->after('google_calendar_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_laravel', 'google_event_id')) {
                $table->dropColumn('google_event_id');
            }
            if (Schema::hasColumn('tasks_laravel', 'google_calendar_id')) {
                $table->dropColumn('google_calendar_id');
            }
            if (Schema::hasColumn('tasks_laravel', 'calendar_synced_at')) {
                $table->dropColumn('calendar_synced_at');
            }
        });
    }
};

