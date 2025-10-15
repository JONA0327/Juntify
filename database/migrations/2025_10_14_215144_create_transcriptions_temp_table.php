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
        Schema::create('transcriptions_temp', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('audio_path'); // Ruta del audio temporal
            $table->string('transcription_path'); // Ruta del .ju
            $table->integer('audio_size')->nullable(); // Tamaño en bytes
            $table->integer('duration')->nullable(); // Duración en segundos
            $table->timestamp('expires_at'); // Cuándo se eliminará el audio
            $table->json('metadata')->nullable(); // Info adicional
            $table->timestamps();

            // Índices para optimización
            $table->index('user_id');
            $table->index('expires_at');

            // Foreign key constraint
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcriptions_temp');
    }
};
