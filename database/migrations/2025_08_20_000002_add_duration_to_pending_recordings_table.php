<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (! Schema::hasColumn('pending_recordings', 'duration')) {
                $table->integer('duration')->nullable()->after('audio_download_url');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (Schema::hasColumn('pending_recordings', 'duration')) {
                $table->dropColumn('duration');
            }
        });
    }
};
