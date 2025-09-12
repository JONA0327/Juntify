<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_context_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->enum('content_type', ['meeting_summary', 'meeting_transcription', 'document_text', 'task_description', 'contact_message']);
            $table->string('content_id'); // ID del contenido referenciado
            $table->text('content_snippet'); // Fragmento del contenido para referencia
            $table->json('embedding_vector'); // Vector de embedding para búsqueda semántica
            $table->json('metadata')->nullable(); // Metadatos adicionales del contenido
            $table->timestamps();

            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
            $table->index(['username', 'content_type']);
            $table->index(['content_type', 'content_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_context_embeddings');
    }
};
