<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAnalyzersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (! Schema::hasTable('analyzers')) {
            Schema::create('analyzers', function (Blueprint $table) {
                $table->string('id', 50)->primary();

                $table->string('name', 100);
                $table->text('description')->nullable();
                $table->string('icon', 50)->nullable();

                $table->text('system_prompt')->nullable();
                $table->text('user_prompt_template')->nullable();

                $table->decimal('temperature', 3, 2)->nullable()->default(0.30);
                $table->tinyInteger('is_system')->nullable()->default(0);

                $table->timestamp('created_at')->useCurrent()->nullable();
                $table->timestamp('updated_at')->useCurrent()->nullable();

                $table->string('created_by', 100)->nullable();
                $table->string('updated_by', 100)->nullable();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('analyzers');
    }
}
