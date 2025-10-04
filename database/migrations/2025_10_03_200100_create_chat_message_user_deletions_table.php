<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_message_user_deletions', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id');
            $table->unsignedBigInteger('chat_message_id');
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('chat_message_id')->references('id')->on('chat_messages')->onDelete('cascade');
            $table->index(['user_id', 'chat_message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_message_user_deletions');
    }
};
