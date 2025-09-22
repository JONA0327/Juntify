<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_meeting_ju_caches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id')->unique();
            $table->string('transcript_drive_id')->nullable();
            $table->longText('encrypted_data'); // Crypt::encryptString(JSON)
            $table->string('checksum')->nullable(); // sha256 del JSON plano
            $table->integer('size_bytes')->nullable();
            $table->timestamp('cached_at')->useCurrent();
            $table->timestamps();

            $table->index('meeting_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_meeting_ju_caches');
    }
};
