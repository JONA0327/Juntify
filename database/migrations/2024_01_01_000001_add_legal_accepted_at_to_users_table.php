<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'legal_accepted_at')) {
                $table->timestamp('legal_accepted_at')->nullable()->after('roles');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'legal_accepted_at')) {
                $table->dropColumn('legal_accepted_at');
            }
        });
    }
};
