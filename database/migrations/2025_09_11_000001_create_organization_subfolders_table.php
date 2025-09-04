<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_subfolders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_folder_id');
            $table->string('google_id')->unique();
            $table->string('name');
            $table->timestamps();

            $table->foreign('organization_folder_id')->references('id')->on('organization_folders')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_subfolders');
    }
};
