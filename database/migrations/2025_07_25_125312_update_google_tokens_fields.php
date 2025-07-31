<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Asegurarse de que el índice no exista antes de crearlo
        $indexExists = DB::select(
            "SHOW INDEX FROM google_tokens WHERE Key_name = 'google_tokens_username_unique'"
        );

        Schema::table('google_tokens', function (Blueprint $table) use ($indexExists) {
            if ($indexExists) {
                $table->dropUnique('google_tokens_username_unique');
            }

            $table->unique('username');

            $table->text('access_token')->nullable()->change();
            $table->text('refresh_token')->nullable()->change();
        });
    }

    public function down()
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            $table->dropUnique(['username']);

            $table->text('access_token')->nullable(false)->change();
            $table->text('refresh_token')->nullable(false)->change();
        });
    }
};
