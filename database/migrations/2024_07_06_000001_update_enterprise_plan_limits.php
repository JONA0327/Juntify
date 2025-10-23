<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!DB::table('plan_limits')->where('role', 'enterprise')->exists()) {
            return;
        }

        DB::table('plan_limits')
            ->where('role', 'enterprise')
            ->update([
                'max_meetings_per_month' => 40,
                'max_duration_minutes' => 120,
                'allow_postpone' => true,
                'warn_before_minutes' => 5,
                'updated_at' => Carbon::now(),
            ]);
    }

    public function down(): void
    {
        if (!DB::table('plan_limits')->where('role', 'enterprise')->exists()) {
            return;
        }

        DB::table('plan_limits')
            ->where('role', 'enterprise')
            ->update([
                'max_meetings_per_month' => 50,
                'updated_at' => Carbon::now(),
            ]);
    }
};
