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
        Schema::create('ai_context_embeddings', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->enum('content_type', ['document', 'meeting', 'task', 'container']);
            $table->unsignedBigInteger('content_id');
            $table->text('content_snippet');
            $table->json('embedding_vector')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['username', 'content_type', 'content_id']);
            $table->index(['content_type', 'content_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ai_context_embeddings');
    }
};
