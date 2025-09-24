<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->string('drive_file_id')->nullable()->after('file_path');
            $table->string('original_name')->nullable()->after('drive_file_id');
            $table->string('mime_type')->nullable()->after('original_name');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
            $table->string('preview_url')->nullable()->after('file_size');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['drive_file_id','original_name','mime_type','file_size','preview_url']);
        });
    }
};
