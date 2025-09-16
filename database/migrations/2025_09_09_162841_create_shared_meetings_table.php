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
        // Solo crear la tabla si no existe
        if (!Schema::hasTable('shared_meetings')) {
            Schema::create('shared_meetings', function (Blueprint $table) {
                $table->id();
                // meeting_id puede apuntar a registros legacy (transcriptions_laravel)
                // o a otro identificador genérico, por eso lo almacenamos como string.
                $table->string('meeting_id', 191);
                // Users.id es UUID, definir columnas como uuid
                $table->uuid('shared_by'); // Usuario que comparte
                $table->uuid('shared_with'); // Usuario receptor
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamp('shared_at')->useCurrent();
                $table->timestamp('responded_at')->nullable();
                $table->text('message')->nullable(); // Mensaje opcional al compartir
                $table->timestamps();

                // FKs
                $table->foreign('shared_by')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('shared_with')->references('id')->on('users')->onDelete('cascade');

                // Evitar duplicados: una reunión solo se puede compartir una vez con el mismo usuario
                $table->unique(['meeting_id', 'shared_with']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shared_meetings');
    }
};
