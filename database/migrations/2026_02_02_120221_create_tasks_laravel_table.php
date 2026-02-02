<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tasks_laravel', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->unsignedBigInteger('meeting_id')->nullable();
            $table->text('tarea');
            $table->text('descripcion')->nullable();
            $table->string('prioridad')->nullable();
            $table->date('fecha_vencimiento')->nullable();
            $table->string('estado')->default('pendiente');
            $table->timestamps();
            
            $table->index(['username', 'meeting_id']);
            $table->unique(['username', 'meeting_id', 'tarea']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks_laravel');
    }
};
