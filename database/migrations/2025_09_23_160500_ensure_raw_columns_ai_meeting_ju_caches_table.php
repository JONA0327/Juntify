<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('ai_meeting_ju_caches')) { return; }
        Schema::table('ai_meeting_ju_caches', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_meeting_ju_caches', 'raw_encrypted_data')) {
                $table->longText('raw_encrypted_data')->nullable()->after('encrypted_data');
            }
            if (!Schema::hasColumn('ai_meeting_ju_caches', 'raw_checksum')) {
                $table->string('raw_checksum')->nullable()->after('checksum');
            }
            if (!Schema::hasColumn('ai_meeting_ju_caches', 'raw_size_bytes')) {
                $table->integer('raw_size_bytes')->nullable()->after('size_bytes');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('ai_meeting_ju_caches')) { return; }
        Schema::table('ai_meeting_ju_caches', function (Blueprint $table) {
            if (Schema::hasColumn('ai_meeting_ju_caches', 'raw_encrypted_data')) {
                $table->dropColumn('raw_encrypted_data');
            }
            if (Schema::hasColumn('ai_meeting_ju_caches', 'raw_checksum')) {
                $table->dropColumn('raw_checksum');
            }
            if (Schema::hasColumn('ai_meeting_ju_caches', 'raw_size_bytes')) {
                $table->dropColumn('raw_size_bytes');
            }
        });
    }
};
