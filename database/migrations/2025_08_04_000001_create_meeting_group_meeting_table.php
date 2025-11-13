<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('meeting_group_meeting')) {
            Schema::create('meeting_group_meeting', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('meeting_group_id');
                $table->unsignedBigInteger('meeting_id');
                $table->char('shared_by', 36)->nullable();
                $table->timestamps();

                $table->unique(['meeting_group_id', 'meeting_id'], 'meeting_group_meeting_meeting_group_id_meeting_id_unique');
                $table->foreign('meeting_group_id')->references('id')->on('meeting_groups')->onDelete('cascade');
                $table->foreign('meeting_id')->references('id')->on('transcriptions_laravel')->onDelete('cascade');
                $table->foreign('shared_by')->references('id')->on('users')->nullOnDelete();
                $table->index('meeting_id');
                $table->index('shared_by');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_group_meeting');
    }
};

