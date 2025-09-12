<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('title')->default('Nueva conversación');
            $table->json('context_data')->nullable(); // Información del contexto seleccionado (contenedor, reuniones, etc.)
            $table->enum('context_type', ['general', 'container', 'meeting', 'contact_chat', 'documents'])->default('general');
            $table->string('context_id')->nullable(); // ID del contenedor, reunión, chat, etc.
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_activity')->nullable();
            $table->timestamps();

            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
            $table->index(['username', 'is_active']);
            $table->index(['context_type', 'context_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_sessions');
    }
};
