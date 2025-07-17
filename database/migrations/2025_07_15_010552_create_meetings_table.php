<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->increments('id');
            $table->string('title', 255);
            $table->dateTime('date');
            $table->string('duration', 50)->nullable();
            $table->integer('participants')->default(0);
            $table->text('summary')->nullable();
            $table->string('recordings_folder_id', 255)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('username', 255)->nullable();
            $table->json('speaker_map')->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('meetings');
    }
};
