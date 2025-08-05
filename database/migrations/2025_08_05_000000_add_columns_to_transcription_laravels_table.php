<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transcription_laravels', function (Blueprint $table) {
            $table->string('meeting_name')->nullable();
            $table->string('audio_file_id')->nullable();
            $table->text('audio_file_url')->nullable();
            $table->string('transcript_file_id')->nullable();
            $table->text('transcript_file_url')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('transcription_laravels', function (Blueprint $table) {
            $table->dropColumn([
                'meeting_name',
                'audio_file_id',
                'audio_file_url',
                'transcript_file_id',
                'transcript_file_url',
            ]);
        });
    }
};
