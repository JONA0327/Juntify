<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            $table->string('backup_path')->nullable()->after('error_message');
        });
    }

    public function down(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            $table->dropColumn('backup_path');
        });
    }
};
