<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('password_reset_tokens') && !Schema::hasColumn('password_reset_tokens', 'expires_at')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dateTime('expires_at')->nullable()->after('created_at');
                $table->index('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('password_reset_tokens') && Schema::hasColumn('password_reset_tokens', 'expires_at')) {
            Schema::table('password_reset_tokens', function (Blueprint $table) {
                $table->dropIndex(['expires_at']);
                $table->dropColumn('expires_at');
            });
        }
    }
};
