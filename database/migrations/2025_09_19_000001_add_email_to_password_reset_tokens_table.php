<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens') && !Schema::hasColumn('password_reset_tokens', 'email')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'email')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dropColumn('email');
            });
        }
    }
};
