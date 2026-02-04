<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La conexión de base de datos que debe usar la migración.
     */
    protected $connection = 'juntify_panels';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Tabla de conversaciones del asistente
        Schema::connection('juntify_panels')->create('ddu_assistant_conversations', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id'); // Referencia a users en juntify_new (sin FK por ser otra BD)
            $table->string('title')->default('Nueva conversación');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('created_at');
        });

        // Tabla de mensajes del asistente
        Schema::connection('juntify_panels')->create('ddu_assistant_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')
                  ->constrained('ddu_assistant_conversations')
                  ->onDelete('cascade');
            $table->enum('role', ['system', 'user', 'assistant', 'tool']);
            $table->longText('content')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('assistant_conversation_id');
            $table->index('role');
            $table->index('created_at');
        });

        // Tabla de documentos del asistente
        Schema::connection('juntify_panels')->create('ddu_assistant_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assistant_conversation_id')
                  ->constrained('ddu_assistant_conversations')
                  ->onDelete('cascade');
            $table->string('original_name');
            $table->string('path', 500);
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->longText('extracted_text')->nullable();
            $table->text('summary')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index('assistant_conversation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('juntify_panels')->dropIfExists('ddu_assistant_documents');
        Schema::connection('juntify_panels')->dropIfExists('ddu_assistant_messages');
        Schema::connection('juntify_panels')->dropIfExists('ddu_assistant_conversations');
    }
};
