<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('organization_folders')) {
            Schema::create('organization_folders', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('organization_id');
                $table->unsignedInteger('organization_google_token_id');
                $table->string('google_id')->nullable()->unique();
                $table->string('name');
                $table->timestamps();

                // Add FK to organizations if exists
                if (Schema::hasTable('organizations')) {
                    $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
                }
            });

            // Add FK to organization_google_tokens if that table exists
            if (Schema::hasTable('organization_google_tokens')) {
                Schema::table('organization_folders', function (Blueprint $table) {
                    $table->foreign('organization_google_token_id')
                        ->references('id')
                        ->on('organization_google_tokens')
                        ->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_folders');
    }
};
