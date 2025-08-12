<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (!Schema::hasColumn('pending_recordings', 'audio_download_url')) {
                $table->text('audio_download_url')->nullable()->after('audio_drive_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pending_recordings', function (Blueprint $table) {
            if (Schema::hasColumn('pending_recordings', 'audio_download_url')) {
                $table->dropColumn('audio_download_url');
            }
        });
    }
};
