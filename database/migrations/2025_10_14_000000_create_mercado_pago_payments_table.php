<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mercado_pago_payments', function (Blueprint $table) {
            $table->id();
            $table->string('external_reference')->unique();
            $table->string('preference_id')->nullable();
            $table->string('payment_id')->nullable()->unique();
            $table->string('item_type', 50);
            $table->string('item_id')->nullable();
            $table->string('item_name');
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('ARS');
            $table->string('status')->default('pending');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mercado_pago_payments');
    }
};
