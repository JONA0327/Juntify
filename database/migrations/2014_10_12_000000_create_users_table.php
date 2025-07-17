<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class  extends Migration
{
    public function up()
    {
        Schema::create('users', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('username', 255)->unique();
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('password', 255);
            $table->string('roles', 200);
            $table->string('organization', 255)->default('Juntify User');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->dateTime('plan_expires_at')->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
