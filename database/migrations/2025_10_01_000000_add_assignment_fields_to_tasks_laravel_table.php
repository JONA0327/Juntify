<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_laravel', 'assigned_user_id')) {
                $table->uuid('assigned_user_id')->nullable()->after('asignado');
            }
            if (!Schema::hasColumn('tasks_laravel', 'assignment_status')) {
                $table->string('assignment_status', 30)->nullable()->after('assigned_user_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_laravel', 'assignment_status')) {
                $table->dropColumn('assignment_status');
            }
            if (Schema::hasColumn('tasks_laravel', 'assigned_user_id')) {
                $table->dropColumn('assigned_user_id');
            }
        });
    }
};
