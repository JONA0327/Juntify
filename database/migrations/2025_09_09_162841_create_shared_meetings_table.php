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
                $table->unsignedInteger('meeting_id'); // Usar unsignedInteger para que coincida con meetings.id
                $table->foreignId('shared_by')->constrained('users')->onDelete('cascade'); // Usuario que comparte
                $table->foreignId('shared_with')->constrained('users')->onDelete('cascade'); // Usuario receptor
                $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
                $table->timestamp('shared_at')->useCurrent();
                $table->timestamp('responded_at')->nullable();
                $table->text('message')->nullable(); // Mensaje opcional al compartir
                $table->timestamps();

                // Crear foreign key manualmente con el tipo correcto
                $table->foreign('meeting_id')->references('id')->on('meetings')->onDelete('cascade');

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
