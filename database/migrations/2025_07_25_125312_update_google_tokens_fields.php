<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Si aún no tienes DBAL instalado:
        // composer require doctrine/dbal

        Schema::table('google_tokens', function (Blueprint $table) {
            // 1) Añadir UNIQUE a username (si no existía)
            $table->unique('username');

            // 2) Hacer nullable los tokens
            $table->text('access_token')->nullable()->change();
            $table->text('refresh_token')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            // 1) Quitar el índice UNIQUE
            $table->dropUnique(['username']);

            // 2) Revertir nullable
            $table->text('access_token')->nullable(false)->change();
            $table->text('refresh_token')->nullable(false)->change();
        });
    }
};
