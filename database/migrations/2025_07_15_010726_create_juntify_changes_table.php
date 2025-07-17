<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('juntify_changes', function (Blueprint $table) {
            $table->increments('id');
            $table->string('version', 20);
            $table->text('description');
            $table->date('change_date');
            $table->time('change_time');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }
    public function down()
    {
        Schema::dropIfExists('juntify_changes');
    }
};
