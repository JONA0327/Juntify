<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Verificar si la columna group_id ya existe
        $hasColumn = Schema::hasColumn('meeting_content_containers', 'group_id');

        if (!$hasColumn) {
            Schema::table('meeting_content_containers', function (Blueprint $table) {
                $table->unsignedInteger('group_id')->nullable()->after('username');
            });
        }

        // Agregar foreign key si no existe
        try {
            Schema::table('meeting_content_containers', function (Blueprint $table) {
                $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            });
        } catch (\Exception $e) {
            // Si el foreign key ya existe, ignorar el error
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('meeting_content_containers', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};
