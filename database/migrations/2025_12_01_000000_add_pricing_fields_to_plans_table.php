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
        Schema::table('plans', function (Blueprint $table) {
            $table->decimal('monthly_price', 12, 2)->nullable()->after('price');
            $table->decimal('yearly_price', 12, 2)->nullable()->after('monthly_price');
            $table->decimal('discount_percentage', 5, 2)->default(0)->after('yearly_price');
            $table->unsignedTinyInteger('free_months')->default(0)->after('discount_percentage');
        });

        // Seed existing rows with sensible defaults
        DB::table('plans')->get()->each(function ($plan) {
            $monthlyPrice = $plan->price ?? 0;
            $yearlyPrice = $monthlyPrice * 12;

            DB::table('plans')
                ->where('id', $plan->id)
                ->update([
                    'monthly_price' => $monthlyPrice,
                    'yearly_price' => $yearlyPrice,
                    'discount_percentage' => 0,
                    'free_months' => 0,
                ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['monthly_price', 'yearly_price', 'discount_percentage', 'free_months']);
        });
    }
};
