<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_user_deletions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chat_id');
            $table->uuid('user_id');
            $table->timestamp('deleted_at');
            $table->unique(['chat_id', 'user_id']);
            $table->foreign('chat_id')->references('id')->on('chats')->onDelete('cascade');
            $table->index(['user_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_user_deletions');
    }
};
