<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_container_ju_caches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('container_id')->unique();
            $table->text('encrypted_payload');
            $table->string('checksum', 64);
            $table->unsignedBigInteger('size_bytes')->nullable();
            $table->timestamp('cached_at')->nullable();
            $table->timestamps();

            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_container_ju_caches');
    }
};
