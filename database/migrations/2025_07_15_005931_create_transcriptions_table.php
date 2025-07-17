<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('transcriptions', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('meeting_id');
            $table->string('time', 50)->nullable();
            $table->string('speaker', 100)->nullable();
            $table->text('text')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->string('display_speaker', 255)->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('transcriptions');
    }
};
