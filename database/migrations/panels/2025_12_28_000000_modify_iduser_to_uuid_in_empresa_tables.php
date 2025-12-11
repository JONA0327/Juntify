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
        // Modificar tabla empresa
        Schema::connection('juntify_panels')->table('empresa', function (Blueprint $table) {
            // Eliminar el índice existente
            $table->dropIndex(['iduser']);

            // Cambiar el tipo de columna a char(36) para UUID
            $table->char('iduser', 36)->change();

            // Recrear el índice
            $table->index('iduser');
        });

        // Modificar tabla integrantes_empresa
        Schema::connection('juntify_panels')->table('integrantes_empresa', function (Blueprint $table) {
            // Eliminar constraints e índices que dependan de iduser
            $table->dropIndex(['iduser']);
            $table->dropUnique(['iduser', 'empresa_id']);

            // Cambiar el tipo de columna a char(36) para UUID
            $table->char('iduser', 36)->change();

            // Recrear el índice y la restricción unique
            $table->index('iduser');
            $table->unique(['iduser', 'empresa_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir tabla empresa
        Schema::connection('juntify_panels')->table('empresa', function (Blueprint $table) {
            $table->dropIndex(['iduser']);
            $table->bigInteger('iduser')->unsigned()->change();
            $table->index('iduser');
        });

        // Revertir tabla integrantes_empresa
        Schema::connection('juntify_panels')->table('integrantes_empresa', function (Blueprint $table) {
            $table->dropIndex(['iduser']);
            $table->dropUnique(['iduser', 'empresa_id']);
            $table->bigInteger('iduser')->unsigned()->change();
            $table->index('iduser');
            $table->unique(['iduser', 'empresa_id']);
        });
    }
};
