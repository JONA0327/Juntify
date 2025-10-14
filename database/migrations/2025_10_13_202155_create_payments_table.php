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
            $table->id();
            $table->string('user_id');
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->foreignId('subscription_id')->nullable()->constrained('user_subscriptions')->onDelete('set null');
            $table->string('external_reference')->unique()->nullable(); // Referencia externa de MP
            $table->string('external_payment_id')->unique()->nullable(); // ID del pago en MP
            $table->string('status')->default('pending'); // Estado del pago
            $table->decimal('amount', 10, 2); // Monto del pago
            $table->string('currency', 3)->default('ARS'); // Moneda
            $table->string('payment_method')->nullable(); // Método de pago
            $table->string('payment_method_id')->nullable(); // ID del método de pago
            $table->string('payer_email')->nullable(); // Email del pagador
            $table->string('payer_name')->nullable(); // Nombre del pagador
            $table->text('description')->nullable(); // Descripción del pago
            $table->json('webhook_data')->nullable(); // Datos completos del webhook
            $table->timestamp('processed_at')->nullable(); // Cuándo se procesó
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'status']);
            $table->index(['external_payment_id']);
            $table->index(['status']);
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
