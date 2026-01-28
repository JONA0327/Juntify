<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->json('task_views')->nullable()->after('warn_before_minutes');
        });

        DB::table('plan_limits')->get()->each(function ($limit) {
            $role = strtolower((string) ($limit->role ?? ''));
            $views = in_array($role, ['business', 'negocios'], true)
                ? ['calendario']
                : ['calendario', 'tablero'];

            DB::table('plan_limits')
                ->where('id', $limit->id)
                ->update(['task_views' => json_encode($views)]);
        });
    }

    public function down(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->dropColumn('task_views');
        });
    }
};
