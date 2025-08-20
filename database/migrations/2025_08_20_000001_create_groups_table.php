<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('id_organizacion');
            $table->string('nombre_grupo');
            $table->text('descripcion')->nullable();
            $table->integer('miembros')->default(0);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('id_organizacion')->references('id')->on('organizations')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('groups');
    }
};
