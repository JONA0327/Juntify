<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            // recordings_folder_id era string(255), al encriptarse puede superar 255 caracteres
            // Cambiar a TEXT para evitar "Data too long for column"
            $table->text('recordings_folder_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            // Revertir a string(255). Nota: si existen valores >255, esto puede truncar o fallar.
            $table->string('recordings_folder_id', 255)->nullable()->change();
        });
    }
};
