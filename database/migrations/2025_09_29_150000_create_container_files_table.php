<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('container_files')) {
            // Ya existe (posible ejecución repetida en entornos locales)
            return;
        }
        Schema::create('container_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id'); // meeting_content_containers.id
            // users.id es UUID, por lo tanto usamos uuid en lugar de unsignedBigInteger
            $table->uuid('user_id'); // uploader
            $table->string('original_name');
            $table->string('stored_name');
            $table->string('disk')->default('public');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size'); // bytes
            $table->string('drive_file_id')->nullable(); // futuro (si se sincroniza a Drive)
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['container_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('container_files');
    }
};
