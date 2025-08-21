<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement("ALTER TABLE group_user MODIFY rol ENUM('meeting_viewer','full_meeting_access') DEFAULT 'meeting_viewer'");
    }

    public function down()
    {
        DB::statement("ALTER TABLE group_user MODIFY rol VARCHAR(255) NULL");
    }
};
