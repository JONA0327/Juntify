<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
     public function up()
    {
        // Create the relations table if it does not already exist.
        if (!Schema::hasTable('meeting_content_relations')) {
            Schema::create('meeting_content_relations', function (Blueprint $table) {
                $table->unsignedBigInteger('container_id');
                $table->unsignedBigInteger('meeting_id');
                $table->timestamps();

                $table->primary(['container_id', 'meeting_id']);
            });

            // Add foreign keys only if the referenced tables exist to avoid FK creation errors
            if (Schema::hasTable('meeting_content_containers') && Schema::hasTable('transcriptions_laravel')) {
                Schema::table('meeting_content_relations', function (Blueprint $table) {
                    $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
                    $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
                });
            }
        } else {
            // Table already exists, try to add FKs if possible (skip on failure)
            if (Schema::hasTable('meeting_content_containers') && Schema::hasTable('transcriptions_laravel')) {
                try {
                    Schema::table('meeting_content_relations', function (Blueprint $table) {
                        // Prevent duplicate foreign key creation if they already exist
                        // Some DB engines will error if FK names collide; we rely on Laravel to handle existing constraints.
                        $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
                        $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
                    });
                } catch (\Exception $e) {
                    // Ignore errors adding foreign keys to avoid stopping migrations
                }
            }
        }
    }
    public function down()
    {
        Schema::dropIfExists('meeting_content_relations');
    }
};
