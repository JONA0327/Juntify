<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('folders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('google_token_id');
            $table->string('google_id');
            $table->string('name');
            $table->unsignedInteger('parent_id')->nullable();
            $table->timestamps();

            $table->foreign('google_token_id')->references('id')->on('google_tokens')->onDelete('cascade');
            $table->foreign('parent_id')->references('id')->on('folders')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('folders');
    }
};
