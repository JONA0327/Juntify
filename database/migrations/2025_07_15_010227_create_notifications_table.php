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
            $table->char('user_id', 36)->nullable();
            $table->char('remitente', 36)->nullable();
            $table->char('emisor', 36)->nullable();
            $table->string('status')->default('pending');
            $table->text('message');
            $table->string('type');
            $table->string('title');
            $table->json('data')->nullable();
            $table->boolean('read')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->char('from_user_id', 36)->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('remitente')->references('id')->on('users')->nullOnDelete();
            $table->foreign('emisor')->references('id')->on('users')->nullOnDelete();
            $table->foreign('from_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('remitente');
            $table->index('emisor');
            $table->index('from_user_id');
            $table->index('user_id');
        });
    }
    public function down()
    {
        Schema::dropIfExists('notifications');
    }
};
