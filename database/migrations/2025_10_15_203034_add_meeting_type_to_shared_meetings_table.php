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
        Schema::table('shared_meetings', function (Blueprint $table) {
            $table->string('meeting_type', 20)->default('regular')->after('meeting_id')->comment('regular or temporary');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shared_meetings', function (Blueprint $table) {
            $table->dropColumn('meeting_type');
        });
    }
};
