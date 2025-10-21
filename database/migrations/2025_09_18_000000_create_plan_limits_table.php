<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('plan_limits', function (Blueprint $table) {
            $table->id();
            $table->string('role')->unique();
            $table->unsignedInteger('max_meetings_per_month')->nullable(); // null = ilimitadas
            $table->unsignedInteger('max_duration_minutes')->default(120); // límite por reunión
            $table->boolean('allow_postpone')->default(true);
            $table->unsignedInteger('warn_before_minutes')->default(5);
            $table->timestamps();
        });

        // Seed default limits
        $now = now();
        $rows = [
            [ 'role' => 'free',        'max_meetings_per_month' => 5,  'max_duration_minutes' => 30,  'allow_postpone' => false, 'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'basic',       'max_meetings_per_month' => 15, 'max_duration_minutes' => 60,  'allow_postpone' => false, 'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'negocios',    'max_meetings_per_month' => 30, 'max_duration_minutes' => 120, 'allow_postpone' => true,  'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'business',    'max_meetings_per_month' => 30, 'max_duration_minutes' => 120, 'allow_postpone' => true,  'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'enterprise',  'max_meetings_per_month' => 50, 'max_duration_minutes' => 120, 'allow_postpone' => true,  'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'founder',     'max_meetings_per_month' => null, 'max_duration_minutes' => 120, 'allow_postpone' => true, 'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'developer',   'max_meetings_per_month' => null, 'max_duration_minutes' => 120, 'allow_postpone' => true, 'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
            [ 'role' => 'superadmin',  'max_meetings_per_month' => null, 'max_duration_minutes' => 120, 'allow_postpone' => true, 'warn_before_minutes' => 5, 'created_at' => $now, 'updated_at' => $now ],
        ];
        DB::table('plan_limits')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_limits');
    }
};
