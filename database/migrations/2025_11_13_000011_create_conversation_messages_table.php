<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('conversation_messages')) {
            Schema::create('conversation_messages', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('conversation_id')->index();
                $table->string('role')->nullable()->index(); // 'user','assistant','system' or null for legacy
                $table->unsignedBigInteger('sender_id')->nullable()->index();
                $table->text('content')->nullable(); // primary content (AiChatMessage)
                $table->text('body')->nullable(); // legacy chat body
                $table->json('attachments')->nullable();
                $table->json('metadata')->nullable();
                $table->string('drive_file_id')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime_type')->nullable();
                $table->unsignedBigInteger('file_size')->nullable();
                $table->string('preview_url')->nullable();
                $table->string('voice_path')->nullable();
                $table->timestamp('read_at')->nullable()->index();
                $table->boolean('is_hidden')->default(false)->index();
                $table->timestamp('created_at')->nullable()->index();
                $table->timestamp('updated_at')->nullable();

                // Optional references to legacy ids to help migration/debug
                $table->unsignedBigInteger('legacy_chat_message_id')->nullable()->index();
                $table->unsignedBigInteger('legacy_ai_message_id')->nullable()->index();

                $table->foreign('conversation_id')->references('id')->on('conversations')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_messages');
    }
};
