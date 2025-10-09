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
        Schema::dropIfExists('password_backup_users');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::create('password_backup_users', function (Blueprint $table) {
            $table->id();
            $table->string('email')->unique();
            $table->string('username')->nullable();
            $table->string('full_name')->nullable();
            $table->text('password_backup')->nullable();
            $table->string('error_type')->default('usuario_no_existe_en_backup');
            $table->text('notas')->nullable();
            $table->timestamp('fecha_backup')->nullable();
            $table->boolean('notified')->default(false);
            $table->timestamp('notification_sent_at')->nullable();
            $table->boolean('password_updated')->default(false);
            $table->timestamp('password_updated_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'notified']);
            $table->index(['email', 'password_updated']);
        });
    }
};
