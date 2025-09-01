<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meeting_content_relations', function (Blueprint $table) {
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('meeting_content_relations', function (Blueprint $table) {
            $table->dropForeign(['container_id']);
            $table->dropForeign(['meeting_id']);
        });
    }
};
