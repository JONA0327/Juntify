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
        DB::statement("ALTER TABLE ai_documents MODIFY COLUMN drive_type ENUM('personal', 'organization', 'temp') DEFAULT 'personal'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE ai_documents MODIFY COLUMN drive_type ENUM('personal', 'organization') DEFAULT 'personal'");
    }
};
