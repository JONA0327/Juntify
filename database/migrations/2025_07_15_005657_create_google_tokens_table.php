<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   public function up()
    {
        Schema::create('google_tokens', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username')->unique();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->dateTime('expiry_date')->nullable();
            $table->integer('expires_in')->nullable()->comment('Duración en segundos del token');
            $table->text('scope')->nullable()->comment('Scopes autorizados del token');
            $table->string('token_type', 50)->default('Bearer')->comment('Tipo de token (Bearer)');
            $table->text('id_token')->nullable()->comment('JWT token de identidad');
            $table->timestamp('token_created_at')->nullable()->comment('Timestamp de creación del token');
            $table->text('recordings_folder_id')->nullable();
            $table->dateTime('created_at')->useCurrent();
            $table->dateTime('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }
    public function down()
    {
        Schema::dropIfExists('google_tokens');
    }
};
