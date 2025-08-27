<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE group_user MODIFY rol ENUM('invitado','colaborador','administrador') DEFAULT 'invitado'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE group_user MODIFY rol VARCHAR(255) NULL");
    }
};
