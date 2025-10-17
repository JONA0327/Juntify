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
        Schema::create('monthly_meeting_usage', function (Blueprint $table) {
            $table->id();
            $table->string('user_id'); // UUID del usuario
            $table->string('username')->nullable(); // Username para compatibilidad
            $table->string('organization_id')->nullable(); // Si aplica a organización
            $table->integer('year'); // Año (2025)
            $table->integer('month'); // Mes (1-12)
            $table->integer('meetings_consumed')->default(0); // Reuniones consumidas
            $table->json('meeting_records')->nullable(); // Log de reuniones para auditoría
            $table->timestamps();

            // Índices para consultas eficientes
            $table->index(['user_id', 'year', 'month']);
            $table->index(['organization_id', 'year', 'month']);

            // Constraint único por usuario/organización por mes
            $table->unique(['user_id', 'organization_id', 'year', 'month'], 'unique_usage_per_month');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('monthly_meeting_usage');
    }
};
