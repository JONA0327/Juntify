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
            // Solo agregar la columna si no existe
            if (!Schema::hasColumn('task_comments', 'parent_id')) {
                $table->unsignedInteger('parent_id')->nullable()->after('id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('task_comments', function (Blueprint $table) {
            if (Schema::hasColumn('task_comments', 'parent_id')) {
                $table->dropColumn('parent_id');
            }
        });
    }
};
