<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archivos_reuniones', function (Blueprint $table) {
            if (!Schema::hasColumn('archivos_reuniones', 'drive_type')) {
                $table->string('drive_type', 30)->default('personal')->after('drive_web_link');
            }
            if (!Schema::hasColumn('archivos_reuniones', 'organization_id')) {
                $table->unsignedInteger('organization_id')->nullable()->after('drive_type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('archivos_reuniones', function (Blueprint $table) {
            if (Schema::hasColumn('archivos_reuniones', 'organization_id')) {
                $table->dropColumn('organization_id');
            }
            if (Schema::hasColumn('archivos_reuniones', 'drive_type')) {
                $table->dropColumn('drive_type');
            }
        });
    }
};
