<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('transcriptions_laravel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('username', 255);
            $table->string('meeting_name', 255);
            $table->string('transcript_drive_id', 255);
            $table->text('transcript_download_url');
            $table->string('audio_drive_id', 255);
            $table->text('audio_download_url');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('username')
                  ->references('username')->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('transcriptions_laravel');
    }
};
