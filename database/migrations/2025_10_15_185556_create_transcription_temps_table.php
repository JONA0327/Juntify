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
        Schema::create('transcription_temps', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // Parece que es VARCHAR según otros modelos
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('audio_path');
            $table->string('transcription_path');
            $table->bigInteger('audio_size')->unsigned();
            $table->decimal('duration', 10, 2)->default(0); // Duración en segundos con decimales
            $table->datetime('expires_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            // Índices para mejorar rendimiento
            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transcription_temps');
    }
};
