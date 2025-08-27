<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (!Schema::hasColumn('tasks_laravel', 'hora_limite')) {
                $table->time('hora_limite')->nullable()->after('fecha_limite');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            if (Schema::hasColumn('tasks_laravel', 'hora_limite')) {
                $table->dropColumn('hora_limite');
            }
        });
    }
};
