<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('meeting_content_containers', function (Blueprint $table) {
            $table->string('drive_folder_id')->nullable()->after('group_id');
            $table->json('metadata')->nullable()->after('drive_folder_id');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_content_containers', function (Blueprint $table) {
            $table->dropColumn(['metadata', 'drive_folder_id']);
        });
    }
};
