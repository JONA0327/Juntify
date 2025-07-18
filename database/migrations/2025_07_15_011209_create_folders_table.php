<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Si existe de un intento previo, la borramos:
        Schema::dropIfExists('folders');

        Schema::create('folders', function (Blueprint $table) {
            $table->increments('id');

            // Debe coincidir con el tipo de google_tokens.id (unsigned integer)
            $table->unsignedInteger('google_token_id');
            $table->foreign('google_token_id')
                  ->references('id')
                  ->on('google_tokens')
                  ->onDelete('cascade');

            $table->string('google_id')->unique();  // El ID real de la carpeta en Drive
            $table->string('name');

            // Para la recursión padre→hijo
            $table->unsignedInteger('parent_id')->nullable();
            $table->foreign('parent_id')
                  ->references('id')
                  ->on('folders')
                  ->onDelete('cascade');

            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('folders');
    }
};
