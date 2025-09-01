<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedBigInteger('remitente');
            $table->unsignedBigInteger('emisor');
            $table->string('status')->default('pending');
            $table->text('message');
            $table->string('type');
            $table->timestamps();
            $table->foreign('remitente')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('emisor')->references('id')->on('users')->onDelete('cascade');
        });
    }
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
