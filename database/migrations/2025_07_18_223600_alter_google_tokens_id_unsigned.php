<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Convierte el id de google_tokens a UNSIGNED
        DB::statement('
            ALTER TABLE `google_tokens`
            MODIFY `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT
        ');
    }

    public function down()
    {
        // Reviértelo, por si alguna vez haces rollback
        DB::statement('
            ALTER TABLE `google_tokens`
            MODIFY `id` INT(11) NOT NULL AUTO_INCREMENT
        ');
    }
};
