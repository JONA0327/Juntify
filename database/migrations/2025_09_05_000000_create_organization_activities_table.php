<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
    // Ensure clean state when running this migration alone
    Schema::dropIfExists('organization_activities');

        Schema::create('organization_activities', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedInteger('organization_id')->nullable();
            $table->unsignedInteger('group_id')->nullable();
            $table->unsignedBigInteger('container_id')->nullable();
            $table->char('user_id', 36)->nullable();
            $table->char('target_user_id', 36)->nullable();
            $table->string('action');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('target_user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_activities');
    }
};
