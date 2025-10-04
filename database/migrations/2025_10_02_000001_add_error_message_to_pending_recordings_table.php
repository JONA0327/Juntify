<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pending_recordings')) {
            return; // Tabla no existe todavía
        }
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (!Schema::hasColumn('pending_recordings', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('pending_recordings')) {
            return;
        }
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (Schema::hasColumn('pending_recordings', 'error_message')) {
                $table->dropColumn('error_message');
            }
        });
    }
};
