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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meeting_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('text');
            $table->text('description')->nullable();
            $table->string('assignee')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('completed')->default(false);
            $table->string('priority')->nullable();
            $table->integer('progress')->default(0);
            $table->timestamps();

            // Índices
            $table->index('meeting_id');
            $table->index('user_id');
            $table->index(['completed', 'due_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
