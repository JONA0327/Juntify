<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_group_user', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('meeting_group_id');
            $table->char('user_id', 36);
            $table->timestamps();

            $table->unique(['meeting_group_id', 'user_id'], 'meeting_group_user_meeting_group_id_user_id_unique');
            $table->foreign('meeting_group_id')->references('id')->on('meeting_groups')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_group_user');
    }
};
