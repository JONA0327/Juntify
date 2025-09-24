<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Throwable;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('user_subscriptions')) {
            Schema::create('user_subscriptions', function (Blueprint $table) {
                $table->id();
                // users.id es UUID -> usamos uuid para respetar tipo y evitar error 150
                $table->uuid('user_id');
                $table->unsignedBigInteger('plan_id');
                $table->string('status', 32)->default('pending'); // pending, active, cancelled, expired
                $table->dateTime('starts_at')->nullable();
                $table->dateTime('ends_at')->nullable();
                $table->dateTime('cancelled_at')->nullable();
                $table->string('external_reference', 191)->nullable()->index(); // vinculo a pago
                $table->json('meta')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('plan_id')->references('id')->on('plans')->onDelete('cascade');
                $table->unique(['user_id','plan_id','status'], 'user_plan_status_unique');
            });
        } else {
            // Tabla ya existe: asegurar compatibilidad (idempotente)
            Schema::table('user_subscriptions', function (Blueprint $table) {
                // Asegurar columnas clave
                if (!Schema::hasColumn('user_subscriptions','user_id')) {
                    $table->uuid('user_id')->after('id');
                }
                if (!Schema::hasColumn('user_subscriptions','plan_id')) {
                    $table->unsignedBigInteger('plan_id')->after('user_id');
                }
                if (!Schema::hasColumn('user_subscriptions','status')) {
                    $table->string('status',32)->default('pending');
                }
                if (!Schema::hasColumn('user_subscriptions','starts_at')) { $table->dateTime('starts_at')->nullable(); }
                if (!Schema::hasColumn('user_subscriptions','ends_at')) { $table->dateTime('ends_at')->nullable(); }
                if (!Schema::hasColumn('user_subscriptions','cancelled_at')) { $table->dateTime('cancelled_at')->nullable(); }
                if (!Schema::hasColumn('user_subscriptions','external_reference')) { $table->string('external_reference',191)->nullable()->index(); }
                if (!Schema::hasColumn('user_subscriptions','meta')) { $table->json('meta')->nullable(); }
                if (!Schema::hasColumn('user_subscriptions','created_at')) { $table->timestamps(); }
            });
            // Ajustar tipo de user_id si no es char(36)
            try {
                $connection = Schema::getConnection()->getDoctrineSchemaManager();
                $columns = $connection->listTableColumns('user_subscriptions');
                if (isset($columns['user_id'])) {
                    $type = (string) $columns['user_id']->getType();
                    $length = $columns['user_id']->getLength();
                    if ($type !== 'guid' && $length !== 36) {
                        DB::statement("ALTER TABLE user_subscriptions MODIFY user_id CHAR(36) NOT NULL");
                    }
                }
            } catch (Throwable $e) {
                // Silencioso: si Doctrine no está o falla, continuar.
            }
            // Intentar añadir claves foráneas si faltan
            try { DB::statement("ALTER TABLE user_subscriptions ADD CONSTRAINT user_subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE"); } catch (Throwable $e) {}
            try { DB::statement("ALTER TABLE user_subscriptions ADD CONSTRAINT user_subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES plans(id) ON DELETE CASCADE"); } catch (Throwable $e) {}
            try { DB::statement("ALTER TABLE user_subscriptions ADD UNIQUE user_plan_status_unique (user_id,plan_id,status)"); } catch (Throwable $e) {}
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_subscriptions');
    }
};
