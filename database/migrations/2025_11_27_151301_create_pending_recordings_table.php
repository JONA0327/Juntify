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
        Schema::create('pending_recordings', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('meeting_name')->nullable();
            $table->string('audio_drive_id')->nullable();
            $table->text('audio_download_url')->nullable();
            $table->integer('duration')->nullable();
            $table->enum('status', ['PENDING', 'PROCESSING', 'COMPLETED', 'FAILED'])->default('PENDING');
            $table->text('error_message')->nullable();
            $table->string('backup_path')->nullable();
            $table->timestamps();

            // Índices
            $table->index(['username', 'status']);
            $table->index('username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pending_recordings');
    }
};
