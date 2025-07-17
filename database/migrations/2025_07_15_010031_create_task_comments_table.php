<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('task_comments', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('task_id');
            $table->string('author', 100);
            $table->text('text');
            $table->timestamp('date')->useCurrent();
        });
    }
    public function down()
    {
        Schema::dropIfExists('task_comments');
    }
};
