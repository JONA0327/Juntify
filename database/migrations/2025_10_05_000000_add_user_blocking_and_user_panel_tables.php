<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'blocked_at')) {
                $table->timestamp('blocked_at')->nullable()->after('plan_expires_at');
            }
            if (!Schema::hasColumn('users', 'blocked_until')) {
                $table->timestamp('blocked_until')->nullable()->after('blocked_at');
            }
            if (!Schema::hasColumn('users', 'blocked_permanent')) {
                $table->boolean('blocked_permanent')->default(false)->after('blocked_until');
            }
            if (!Schema::hasColumn('users', 'blocked_reason')) {
                $table->text('blocked_reason')->nullable()->after('blocked_permanent');
            }
            if (!Schema::hasColumn('users', 'blocked_by')) {
                $table->char('blocked_by', 36)->nullable()->after('blocked_reason');
                $table->foreign('blocked_by')->references('id')->on('users')->nullOnDelete();
            }
        });

        Schema::create('user_panel_administrativo', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('company_name', 255);
            $table->char('administrator_id', 36);
            $table->string('panel_url', 255);
            $table->timestamps();

            $table->foreign('administrator_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique('panel_url');
        });

        Schema::create('user_panel_miembros', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('panel_id', 36);
            $table->char('user_id', 36);
            $table->string('role', 100);
            $table->timestamps();

            $table->foreign('panel_id')->references('id')->on('user_panel_administrativo')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['panel_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_panel_miembros');
        Schema::dropIfExists('user_panel_administrativo');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'blocked_by')) {
                $table->dropForeign(['blocked_by']);
                $table->dropColumn('blocked_by');
            }
            if (Schema::hasColumn('users', 'blocked_reason')) {
                $table->dropColumn('blocked_reason');
            }
            if (Schema::hasColumn('users', 'blocked_permanent')) {
                $table->dropColumn('blocked_permanent');
            }
            if (Schema::hasColumn('users', 'blocked_until')) {
                $table->dropColumn('blocked_until');
            }
            if (Schema::hasColumn('users', 'blocked_at')) {
                $table->dropColumn('blocked_at');
            }
        });
    }
};
