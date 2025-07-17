<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meeting_files', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('meeting_id');
            $table->text('file_url');
            $table->string('file_name', 255);
            $table->string('file_type', 100);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->text('summary')->nullable();
            $table->text('key_points')->nullable();
            $table->string('openai_file_id', 100)->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('meeting_files');
    }
};
