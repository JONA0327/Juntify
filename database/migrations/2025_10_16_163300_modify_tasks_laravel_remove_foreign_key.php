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
        Schema::table('tasks_laravel', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['meeting_id']);

            // Optionally add a meeting_type column to distinguish between permanent and temporary meetings
            $table->string('meeting_type', 20)->default('permanent')->after('meeting_id');
            $table->index(['meeting_id', 'meeting_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks_laravel', function (Blueprint $table) {
            // Re-add the foreign key constraint (only for permanent meetings)
            $table->foreign('meeting_id')
                ->references('id')->on('transcriptions_laravel')
                ->onDelete('cascade');

            // Drop the meeting_type column
            $table->dropIndex(['meeting_id', 'meeting_type']);
            $table->dropColumn('meeting_type');
        });
    }
};
