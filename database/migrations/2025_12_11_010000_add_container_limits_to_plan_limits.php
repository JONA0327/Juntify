<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->unsignedInteger('max_containers_personal')->nullable()->after('warn_before_minutes');
            $table->unsignedInteger('max_meetings_per_container_personal')->nullable()->after('max_containers_personal');
            $table->unsignedInteger('max_containers_org')->nullable()->after('max_meetings_per_container_personal');
            $table->unsignedInteger('max_meetings_per_container_org')->nullable()->after('max_containers_org');
        });

        $defaults = [
            'free' => [
                'max_containers_personal' => 0,
                'max_meetings_per_container_personal' => 0,
                'max_containers_org' => 0,
                'max_meetings_per_container_org' => 0,
            ],
            'basic' => [
                'max_containers_personal' => 3,
                'max_meetings_per_container_personal' => 10,
                'max_containers_org' => 50,
                'max_meetings_per_container_org' => 100,
            ],
            'negocios' => [
                'max_containers_personal' => 10,
                'max_meetings_per_container_personal' => 10,
                'max_containers_org' => 50,
                'max_meetings_per_container_org' => 100,
            ],
            'business' => [
                'max_containers_personal' => 10,
                'max_meetings_per_container_personal' => 10,
                'max_containers_org' => 50,
                'max_meetings_per_container_org' => 100,
            ],
            'enterprise' => [
                'max_containers_personal' => 10,
                'max_meetings_per_container_personal' => 15,
                'max_containers_org' => null,
                'max_meetings_per_container_org' => 10,
            ],
            'founder' => [
                'max_containers_personal' => null,
                'max_meetings_per_container_personal' => null,
                'max_containers_org' => 50,
                'max_meetings_per_container_org' => 100,
            ],
            'developer' => [
                'max_containers_personal' => null,
                'max_meetings_per_container_personal' => null,
                'max_containers_org' => 50,
                'max_meetings_per_container_org' => 100,
            ],
            'superadmin' => [
                'max_containers_personal' => null,
                'max_meetings_per_container_personal' => null,
                'max_containers_org' => null,
                'max_meetings_per_container_org' => null,
            ],
        ];

        foreach ($defaults as $role => $values) {
            DB::table('plan_limits')->where('role', $role)->update($values);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plan_limits', function (Blueprint $table) {
            $table->dropColumn([
                'max_containers_personal',
                'max_meetings_per_container_personal',
                'max_containers_org',
                'max_meetings_per_container_org',
            ]);
        });
    }
};
