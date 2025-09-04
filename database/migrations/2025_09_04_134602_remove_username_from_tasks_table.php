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
        Schema::table('tasks', function (Blueprint $table) {
            // Verificar si la columna existe antes de intentar eliminarla
            if (Schema::hasColumn('tasks', 'username')) {
                // Eliminar foreign key si existe
                try {
                    $table->dropForeign(['username']);
                } catch (\Throwable $e) {
                    // La foreign key puede no existir o tener un nombre diferente
                }

                // Eliminar índice si existe
                try {
                    $table->dropIndex('tasks_username_index');
                } catch (\Throwable $e) {
                    // El índice puede no existir o tener un nombre diferente
                }

                // Eliminar la columna
                $table->dropColumn('username');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Recrear la columna username si es necesario
            if (!Schema::hasColumn('tasks', 'username')) {
                $table->string('username', 255)->nullable()->after('id');
                $table->index('username', 'tasks_username_index');

                // Intentar recrear la foreign key solo si users.username existe
                if (Schema::hasTable('users') && Schema::hasColumn('users', 'username')) {
                    try {
                        $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
                    } catch (\Throwable $e) {
                        // Puede fallar si ya existe o hay problemas de datos
                    }
                }
            }
        });
    }
};
