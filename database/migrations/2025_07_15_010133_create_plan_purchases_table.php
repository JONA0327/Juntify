<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('plan_purchases', function (Blueprint $table) {
            $table->increments('id');
            $table->string('username', 255);
            $table->string('plan_name', 255);
            $table->string('billing_cycle', 50);
            $table->decimal('price', 10, 2);
            $table->timestamp('purchased_at')->useCurrent();
            $table->dateTime('expires_at')->nullable();
        });
    }
    public function down()
    {
        Schema::dropIfExists('plan_purchases');
    }
};
