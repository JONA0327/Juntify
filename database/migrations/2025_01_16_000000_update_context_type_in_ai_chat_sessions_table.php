<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE ai_chat_sessions MODIFY context_type ENUM('general','container','meeting','contact_chat','documents','mixed') DEFAULT 'general'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE ai_chat_sessions MODIFY context_type ENUM('general','container','meeting','contact_chat','documents') DEFAULT 'general'");
    }
};
