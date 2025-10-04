<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->longText('voice_base64')->nullable()->after('voice_path');
            $table->string('voice_mime', 100)->nullable()->after('voice_base64');
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropColumn(['voice_base64', 'voice_mime']);
        });
    }
};
