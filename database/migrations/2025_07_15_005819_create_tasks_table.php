<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
  public function up()
    {
        Schema::create('tasks', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 255);
            $table->string('supabase_user_id', 255);
            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
            $table->unsignedInteger('meeting_id')->nullable();
            $table->string('text', 255);
            $table->text('description')->nullable();
            $table->string('assignee', 100)->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->enum('priority', ['baja','media','alta'])->default('media');
            $table->integer('progress')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }
    public function down()
    {
        Schema::dropIfExists('tasks');
    }
};
