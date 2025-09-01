<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Alinear tipos y recrear FKs de forma idempotente
        // 1) Intentar eliminar FKs previas si existen
        try {
            Schema::table('meeting_content_relations', function (Blueprint $table) {
                $table->dropForeign(['container_id']);
            });
        } catch (\Throwable $e) {}
        try {
            Schema::table('meeting_content_relations', function (Blueprint $table) {
                $table->dropForeign(['meeting_id']);
            });
        } catch (\Throwable $e) {}

        // 2) Alinear el tipo de container_id a BIGINT UNSIGNED para que coincida con meeting_content_containers.id
        // Usamos SQL directo para evitar dependencia de doctrine/dbal
        \Illuminate\Support\Facades\DB::statement('ALTER TABLE meeting_content_relations MODIFY COLUMN container_id BIGINT UNSIGNED NOT NULL');

        // 3) Asegurar meeting_id es BIGINT UNSIGNED (ya lo es, pero lo reforzamos de forma segura)
        // \Illuminate\Support\Facades\DB::statement('ALTER TABLE meeting_content_relations MODIFY COLUMN meeting_id BIGINT UNSIGNED NOT NULL');

        // 4) Volver a crear las llaves foráneas
        Schema::table('meeting_content_relations', function (Blueprint $table) {
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_content_relations', function (Blueprint $table) {
            $table->dropForeign(['container_id']);
            $table->dropForeign(['meeting_id']);
        });
        // Revertir tipo de container_id a INT UNSIGNED si fuese necesario
        try {
            \Illuminate\Support\Facades\DB::statement('ALTER TABLE meeting_content_relations MODIFY COLUMN container_id INT UNSIGNED NOT NULL');
        } catch (\Throwable $e) {}
    }
};
