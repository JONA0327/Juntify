<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->string('plan_id', 100);
            $table->string('role', 100)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('has_unlimited_roles')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_plans');
    }
};
