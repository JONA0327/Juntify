<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

try {
    echo "Creando tablas faltantes...\n\n";

    // Crear tabla user_plans
    if (!Schema::hasTable('user_plans')) {
        echo "Creando tabla user_plans...\n";
        Schema::create('user_plans', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->string('plan_id', 100);
            $table->string('role', 100)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('expires_at')->nullable();
            $table->string('status', 50)->default('active');
            $table->boolean('has_unlimited_roles')->default(false);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->index(['status', 'expires_at']);
        });
        echo "✅ Tabla user_plans creada\n";
    } else {
        echo "⚠️ Tabla user_plans ya existe\n";
    }

    // Crear tabla plan_purchases
    if (!Schema::hasTable('plan_purchases')) {
        echo "Creando tabla plan_purchases...\n";
        Schema::create('plan_purchases', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('user_id');
            $table->unsignedBigInteger('user_plan_id');
            $table->string('provider', 50)->default('mercado_pago');
            $table->string('payment_id', 100)->nullable();
            $table->string('external_reference', 100)->nullable();
            $table->string('status', 50);
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 10)->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('user_plan_id')->references('id')->on('user_plans')->cascadeOnDelete();
            $table->index(['user_id', 'status']);
            $table->unique(['provider', 'payment_id']);
        });
        echo "✅ Tabla plan_purchases creada\n";
    } else {
        echo "⚠️ Tabla plan_purchases ya existe\n";
    }

    echo "\n🎉 ¡Tablas creadas exitosamente!\n";

} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
