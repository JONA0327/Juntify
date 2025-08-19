<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('container_meetings', function (Blueprint $table) {
            $table->unsignedInteger('container_id');
            $table->unsignedBigInteger('meeting_id');
            $table->primary(['container_id', 'meeting_id']);
        });
    }
    public function down()
    {
        Schema::dropIfExists('container_meetings');
    }
};
