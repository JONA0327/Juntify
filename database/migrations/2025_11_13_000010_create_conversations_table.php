<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('conversations')) {
            Schema::create('conversations', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('type')->nullable()->default('chat')->index(); // 'chat' or 'ai' or other
                $table->string('title')->nullable();
                $table->string('username')->nullable()->index(); // owner username for Ai sessions
                $table->unsignedBigInteger('user_one_id')->nullable()->index();
                $table->unsignedBigInteger('user_two_id')->nullable()->index();
                $table->string('context_type')->nullable()->index();
                $table->string('context_id')->nullable();
                $table->json('context_data')->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->timestamp('last_activity')->nullable()->index();
                $table->json('metadata')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
