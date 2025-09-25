<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Folders
        Schema::table('folders', function (Blueprint $table) {
            if (!Schema::hasColumn('folders', 'google_id_enc')) {
                $table->text('google_id_enc')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('folders', 'google_id_hash')) {
                $table->string('google_id_hash', 64)->nullable()->after('google_id_enc');
                $table->unique('google_id_hash', 'folders_google_id_hash_unique');
            }
        });

        // Subfolders
        Schema::table('subfolders', function (Blueprint $table) {
            if (!Schema::hasColumn('subfolders', 'google_id_enc')) {
                $table->text('google_id_enc')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('subfolders', 'google_id_hash')) {
                $table->string('google_id_hash', 64)->nullable()->after('google_id_enc');
                $table->unique('google_id_hash', 'subfolders_google_id_hash_unique');
            }
        });

        // Organization folders
        Schema::table('organization_folders', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_folders', 'google_id_enc')) {
                $table->text('google_id_enc')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('organization_folders', 'google_id_hash')) {
                $table->string('google_id_hash', 64)->nullable()->after('google_id_enc');
                $table->unique('google_id_hash', 'org_folders_google_id_hash_unique');
            }
        });

        // Organization subfolders
        Schema::table('organization_subfolders', function (Blueprint $table) {
            if (!Schema::hasColumn('organization_subfolders', 'google_id_enc')) {
                $table->text('google_id_enc')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('organization_subfolders', 'google_id_hash')) {
                $table->string('google_id_hash', 64)->nullable()->after('google_id_enc');
                $table->unique('google_id_hash', 'org_subfolders_google_id_hash_unique');
            }
        });

        // Group drive folders
        Schema::table('group_drive_folders', function (Blueprint $table) {
            if (!Schema::hasColumn('group_drive_folders', 'google_id_enc')) {
                $table->text('google_id_enc')->nullable()->after('google_id');
            }
            if (!Schema::hasColumn('group_drive_folders', 'google_id_hash')) {
                $table->string('google_id_hash', 64)->nullable()->after('google_id_enc');
                $table->unique('google_id_hash', 'group_drive_folders_google_id_hash_unique');
            }
        });

        // Transcriptions (legacy meetings)
        Schema::table('transcriptions_laravel', function (Blueprint $table) {
            if (!Schema::hasColumn('transcriptions_laravel', 'audio_drive_id_enc')) {
                $table->text('audio_drive_id_enc')->nullable()->after('audio_drive_id');
            }
            if (!Schema::hasColumn('transcriptions_laravel', 'transcript_drive_id_enc')) {
                $table->text('transcript_drive_id_enc')->nullable()->after('transcript_drive_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('folders', function (Blueprint $table) {
            if (Schema::hasColumn('folders', 'google_id_hash')) {
                $table->dropUnique('folders_google_id_hash_unique');
                $table->dropColumn('google_id_hash');
            }
            if (Schema::hasColumn('folders', 'google_id_enc')) {
                $table->dropColumn('google_id_enc');
            }
        });

        Schema::table('subfolders', function (Blueprint $table) {
            if (Schema::hasColumn('subfolders', 'google_id_hash')) {
                $table->dropUnique('subfolders_google_id_hash_unique');
                $table->dropColumn('google_id_hash');
            }
            if (Schema::hasColumn('subfolders', 'google_id_enc')) {
                $table->dropColumn('google_id_enc');
            }
        });

        Schema::table('organization_folders', function (Blueprint $table) {
            if (Schema::hasColumn('organization_folders', 'google_id_hash')) {
                $table->dropUnique('org_folders_google_id_hash_unique');
                $table->dropColumn('google_id_hash');
            }
            if (Schema::hasColumn('organization_folders', 'google_id_enc')) {
                $table->dropColumn('google_id_enc');
            }
        });

        Schema::table('organization_subfolders', function (Blueprint $table) {
            if (Schema::hasColumn('organization_subfolders', 'google_id_hash')) {
                $table->dropUnique('org_subfolders_google_id_hash_unique');
                $table->dropColumn('google_id_hash');
            }
            if (Schema::hasColumn('organization_subfolders', 'google_id_enc')) {
                $table->dropColumn('google_id_enc');
            }
        });

        Schema::table('group_drive_folders', function (Blueprint $table) {
            if (Schema::hasColumn('group_drive_folders', 'google_id_hash')) {
                $table->dropUnique('group_drive_folders_google_id_hash_unique');
                $table->dropColumn('google_id_hash');
            }
            if (Schema::hasColumn('group_drive_folders', 'google_id_enc')) {
                $table->dropColumn('google_id_enc');
            }
        });

        Schema::table('transcriptions_laravel', function (Blueprint $table) {
            if (Schema::hasColumn('transcriptions_laravel', 'audio_drive_id_enc')) {
                $table->dropColumn('audio_drive_id_enc');
            }
            if (Schema::hasColumn('transcriptions_laravel', 'transcript_drive_id_enc')) {
                $table->dropColumn('transcript_drive_id_enc');
            }
        });
    }
};
