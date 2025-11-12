<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Drop and recreate to ensure a clean table state
        Schema::disableForeignKeyConstraints();
        Schema::dropIfExists('tasks_laravel');
        Schema::enableForeignKeyConstraints();

        Schema::create('tasks_laravel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username')->index();
            $table->unsignedBigInteger('meeting_id');
            $table->string('meeting_type', 20)->default('permanent');
            $table->string('tarea');
            $table->string('prioridad', 20)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->time('hora_limite')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('asignado', 255)->nullable();
            $table->char('assigned_user_id', 36)->nullable();
            $table->string('assignment_status', 30)->nullable();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->string('google_event_id', 255)->nullable();
            $table->string('google_calendar_id', 255)->nullable();
            $table->timestamp('calendar_synced_at')->nullable();
            $table->timestamp('overdue_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['meeting_id', 'tarea'], 'tasks_laravel_meeting_tarea_unique');
            $table->index(['meeting_id', 'meeting_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_laravel');
    }
};
