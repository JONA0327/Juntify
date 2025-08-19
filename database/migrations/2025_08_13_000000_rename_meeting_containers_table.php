<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Crear nueva tabla de contenedores de reuniones con estructura mejorada
        if (!Schema::hasTable('meeting_content_containers')) {
            Schema::create('meeting_content_containers', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('username');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('username');
                $table->index('is_active');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_content_containers');
    }
};
