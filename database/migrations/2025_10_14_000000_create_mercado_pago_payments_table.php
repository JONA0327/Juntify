<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_payments', function (Blueprint $table) {
            $table->id();
            // Limitar longitudes para compatibilidad con índices únicos en MySQL/MariaDB antiguos
            $table->string('external_reference', 191)->unique();
            $table->string('preference_id', 191)->nullable();
            $table->string('payment_id', 191)->nullable()->unique();
            $table->string('item_type', 50);
            $table->string('item_id', 64)->nullable();
            $table->string('item_name', 255);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('ARS');
            $table->string('status', 32)->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_payments');
    }
};
