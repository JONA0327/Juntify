<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->unsignedBigInteger('user_plan_id');
            $table->string('provider', 50)->default('mercado_pago');
            $table->string('payment_id', 100)->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->string('status', 50);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_plan_id')->references('id')->on('user_plans')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->unique(['provider', 'payment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_purchases');
    }
};
