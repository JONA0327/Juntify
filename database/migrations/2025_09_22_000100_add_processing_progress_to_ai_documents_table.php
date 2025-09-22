<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_documents', 'processing_progress')) {
                $table->unsignedTinyInteger('processing_progress')->nullable()->after('processing_status');
            }
            if (!Schema::hasColumn('ai_documents', 'processing_step')) {
                $table->string('processing_step', 100)->nullable()->after('processing_progress');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            if (Schema::hasColumn('ai_documents', 'processing_step')) {
                $table->dropColumn('processing_step');
            }
            if (Schema::hasColumn('ai_documents', 'processing_progress')) {
                $table->dropColumn('processing_progress');
            }
        });
    }
};
