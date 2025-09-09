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
        Schema::table('notifications', function (Blueprint $table) {
            // Solo agregar columnas si no existen
            if (!Schema::hasColumn('notifications', 'from_user_id')) {
                $table->foreignId('from_user_id')->nullable()->constrained('users')->onDelete('cascade')->after('user_id');
            }
            if (!Schema::hasColumn('notifications', 'type')) {
                $table->string('type')->after('from_user_id');
            }
            if (!Schema::hasColumn('notifications', 'title')) {
                $table->string('title')->after('type');
            }
            if (!Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->after('title');
            }
            if (!Schema::hasColumn('notifications', 'read')) {
                $table->boolean('read')->default(false)->after('data');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'from_user_id')) {
                $table->dropForeign(['from_user_id']);
                $table->dropColumn('from_user_id');
            }
            if (Schema::hasColumn('notifications', 'type')) {
                $table->dropColumn('type');
            }
            if (Schema::hasColumn('notifications', 'title')) {
                $table->dropColumn('title');
            }
            if (Schema::hasColumn('notifications', 'message')) {
                $table->dropColumn('message');
            }
            if (Schema::hasColumn('notifications', 'read')) {
                $table->dropColumn('read');
            }
        });
    }
};
