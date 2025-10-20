<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->boolean('is_temporary')->default(false)->after('processing_status');
            $table->bigInteger('session_id')->nullable()->after('is_temporary');
            $table->index(['is_temporary', 'session_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ai_documents', function (Blueprint $table) {
            $table->dropIndex(['is_temporary', 'session_id']);
            $table->dropColumn(['is_temporary', 'session_id']);
        });
    }
};
