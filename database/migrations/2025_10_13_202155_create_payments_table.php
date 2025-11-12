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
        Schema::create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('user_id', 255);
            $table->unsignedBigInteger('plan_id');
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('external_reference', 255)->nullable()->unique();
            $table->string('external_payment_id', 255)->nullable()->unique();
            $table->string('status', 255)->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('ARS');
            $table->string('payment_method', 255)->nullable();
            $table->string('payment_method_id', 255)->nullable();
            $table->string('payer_email', 255)->nullable();
            $table->string('payer_name', 255)->nullable();
            $table->text('description')->nullable();
            $table->json('webhook_data')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
            $table->foreign('subscription_id')->references('id')->on('user_subscriptions')->onDelete('set null');
            $table->index(['user_id', 'status'], 'payments_user_id_status_index');
            $table->index('external_payment_id', 'payments_external_payment_id_index');
            $table->index('status', 'payments_status_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
