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
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // basico, negocios, empresas
            $table->string('name'); // Nombre del plan
            $table->text('description')->nullable(); // Descripción del plan
            $table->decimal('price', 10, 2); // Precio mensual
            $table->string('currency', 3)->default('ARS'); // Moneda
            $table->integer('billing_cycle_days')->default(30); // Días del ciclo de facturación
            $table->boolean('is_active')->default(true); // Si está activo
            $table->json('features')->nullable(); // Características del plan
            $table->timestamps();

            $table->index(['code']);
            $table->index(['is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
