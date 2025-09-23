<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('ai_meeting_ju_caches', function (Blueprint $table) {
            // Almacena el JSON completo desencriptado (pero se guarda encriptado en BD)
            $table->longText('raw_encrypted_data')->nullable()->after('encrypted_data');
            $table->string('raw_checksum')->nullable()->after('checksum');
            $table->integer('raw_size_bytes')->nullable()->after('size_bytes');
        });
    }

    public function down(): void
    {
        Schema::table('ai_meeting_ju_caches', function (Blueprint $table) {
            $table->dropColumn(['raw_encrypted_data','raw_checksum','raw_size_bytes']);
        });
    }
};
