<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_laravel', 'overdue_notified_at')) {
                $table->timestamp('overdue_notified_at')->nullable()->after('calendar_synced_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_laravel', 'overdue_notified_at')) {
                $table->dropColumn('overdue_notified_at');
            }
        });
    }
};
