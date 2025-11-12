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
                $table->bigIncrements('id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('username');
                $table->unsignedBigInteger('group_id')->nullable();
                $table->string('drive_folder_id')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                $table->index('username', 'meeting_content_containers_username_index');
                $table->index('is_active', 'meeting_content_containers_is_active_index');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_content_containers');
    }
};
