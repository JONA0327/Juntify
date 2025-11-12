<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_daily_usage', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 255);
            $table->date('usage_date');
            $table->unsignedInteger('message_count')->default(0);
            $table->unsignedInteger('document_count')->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date']);
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_daily_usage');
    }
};
