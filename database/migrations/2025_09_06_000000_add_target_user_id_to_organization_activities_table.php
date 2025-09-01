<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organization_activities', function (Blueprint $table) {
            $table->uuid('target_user_id')->nullable()->after('user_id');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('organization_activities', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropColumn('target_user_id');
        });
    }
};
