<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('meeting_content_relations', function (Blueprint $table) {
            $table->unsignedBigInteger('container_id');
            $table->unsignedBigInteger('meeting_id');
            $table->timestamps();

            $table->primary(['container_id', 'meeting_id']);
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('meeting_content_relations');
    }
};
