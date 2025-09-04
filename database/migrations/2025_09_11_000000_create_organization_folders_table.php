<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_folders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_id');
            $table->unsignedInteger('google_token_id');
            $table->string('google_id')->unique();
            $table->string('name');
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('google_token_id')->references('id')->on('google_tokens')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_folders');
    }
};
