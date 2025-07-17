<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('analyzers', function (Blueprint $table) {
            $table->string('id', 50);
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('icon', 50)->nullable();
            $table->text('system_prompt');
            $table->text('user_prompt_template');
            $table->decimal('temperature', 3, 2)->default(0.30);
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('created_by', 100)->nullable();
            $table->string('updated_by', 100)->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('analyzers');
    }
};
