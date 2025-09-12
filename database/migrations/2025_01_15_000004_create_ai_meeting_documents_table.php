<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_meeting_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained('ai_documents')->onDelete('cascade');
            $table->string('meeting_id'); // ID de la reunión (puede ser legacy o nueva)
            $table->enum('meeting_type', ['legacy', 'modern'])->default('legacy');
            $table->string('assigned_by_username');
            $table->text('assignment_note')->nullable();
            $table->timestamps();

            $table->foreign('assigned_by_username')->references('username')->on('users')->onDelete('cascade');
            $table->index(['meeting_id', 'meeting_type']);
            $table->index(['document_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_meeting_documents');
    }
};
