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
            $table->unsignedBigInteger('current_organization_id')->nullable();
            $table->string('username', 255);
            $table->string('full_name', 255);
            $table->string('email', 255);
            $table->string('password', 255);
            $table->string('roles', 200);
            $table->timestamp('legal_accepted_at')->nullable();
            $table->timestamps();
            $table->dateTime('plan_expires_at')->nullable();
            $table->timestamp('blocked_at')->nullable();
            $table->timestamp('blocked_until')->nullable();
            $table->boolean('blocked_permanent')->default(false);
            $table->text('blocked_reason')->nullable();
            $table->char('blocked_by', 36)->nullable();
            $table->string('plan', 50)->default('free');
            $table->string('plan_code', 50)->default('free');
            $table->boolean('is_role_protected')->default(false);

            $table->unique('username');
            $table->unique('email');
            $table->index('username', 'idx_username');
            $table->index('email', 'idx_email');
            $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
        });
    }
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
