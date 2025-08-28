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
        Schema::table('task_comments', function (Blueprint $table) {
            // Verificar si la columna parent_id existe, si no, crearla
            if (!Schema::hasColumn('task_comments', 'parent_id')) {
                $table->unsignedInteger('parent_id')->nullable()->after('id');
            }
        });

        // Intentar agregar la foreign key solo si no existe
        try {
            Schema::table('task_comments', function (Blueprint $table) {
                $table->foreign('parent_id')
                      ->references('id')
                      ->on('task_comments')
                      ->onDelete('cascade');
            });
        } catch (Exception $e) {
            // Si la foreign key ya existe, no hacer nada
            if (strpos($e->getMessage(), 'Duplicate key name') === false) {
                throw $e;
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });
    }
};
