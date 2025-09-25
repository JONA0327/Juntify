<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_drive_folders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('organization_subfolder_id');
            $table->string('google_id')->unique();
            $table->string('name');
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('organization_subfolder_id')->references('id')->on('organization_subfolders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_drive_folders');
    }
};

