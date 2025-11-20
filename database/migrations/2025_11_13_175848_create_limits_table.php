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
        Schema::create('limits', function (Blueprint $table) {
            $table->id();
            $table->string('username')->index();
            $table->string('plan_code')->default('free');
            $table->string('role')->default('user');
            $table->integer('daily_message_limit')->default(10);
            $table->integer('daily_session_limit')->default(3);
            $table->boolean('can_upload_document')->default(false);
            $table->boolean('has_premium_features')->default(false);
            $table->json('additional_limits')->nullable();
            $table->timestamps();

            $table->unique(['username']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('limits');
    }
};
