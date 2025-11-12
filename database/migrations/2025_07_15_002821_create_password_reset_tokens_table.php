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
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('user_id');
            $table->string('token');
            $table->dateTime('expires_at');
            $table->boolean('used')->default(false);
            $table->dateTime('created_at')->useCurrent();
            $table->string('email');

            $table->index('token', 'idx_token');
            $table->index('email', 'password_reset_tokens_email_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
    }
};
