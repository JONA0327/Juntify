<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Actualizar la columna rol para soportar los nuevos roles
        DB::statement("ALTER TABLE group_user MODIFY rol ENUM('invitado','colaborador','administrador') DEFAULT 'invitado'");

        // Migrar datos existentes
        DB::statement("UPDATE group_user SET rol = 'invitado' WHERE rol = 'meeting_viewer'");
        DB::statement("UPDATE group_user SET rol = 'colaborador' WHERE rol = 'full_meeting_access'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Migrar datos de vuelta
        DB::statement("UPDATE group_user SET rol = 'meeting_viewer' WHERE rol = 'invitado'");
        DB::statement("UPDATE group_user SET rol = 'full_meeting_access' WHERE rol = 'colaborador'");

        // Revertir la columna rol a los valores anteriores
        DB::statement("ALTER TABLE group_user MODIFY rol ENUM('meeting_viewer','full_meeting_access') DEFAULT 'meeting_viewer'");
    }
};
