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
            $table->string('tarea');
            $table->string('prioridad', 20)->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_limite')->nullable();
            $table->text('descripcion')->nullable();
            $table->unsignedTinyInteger('progreso')->default(0);
            $table->timestamps();

            $table->foreign('meeting_id')
                ->references('id')->on('transcriptions_laravel')
                ->onDelete('cascade');

            $table->unique(['meeting_id', 'tarea'], 'tasks_laravel_meeting_tarea_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks_laravel');
    }
};
