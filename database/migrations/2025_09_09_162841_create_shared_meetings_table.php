<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Solo crear la tabla si no existe
        if (!Schema::hasTable('shared_meetings')) {
            Schema::create('shared_meetings', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedInteger('meeting_id');
                $table->string('meeting_type', 20)->default('regular');
                $table->char('shared_by', 36);
                $table->char('shared_with', 36);
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamp('shared_at')->useCurrent();
                $table->timestamp('responded_at')->nullable();
                $table->text('message')->nullable();
                $table->json('permissions')->nullable();
                $table->timestamps();

                $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('shared_with')->references('id')->on('users')->onDelete('cascade');
                $table->index('shared_by');
                $table->index('shared_with');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_meetings');
    }
};
