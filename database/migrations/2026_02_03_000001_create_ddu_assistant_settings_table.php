<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La conexión de base de datos que debe usar la migración.
     */
    protected $connection = 'juntify_panels';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::connection('juntify_panels')->create('ddu_assistant_settings', function (Blueprint $table) {
            $table->id();
            $table->uuid('user_id')->unique(); // Referencia a users en juntify_new (sin FK por ser otra BD)
            $table->text('openai_api_key')->nullable(); // Encriptada
            $table->boolean('enable_drive_calendar')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('juntify_panels')->dropIfExists('ddu_assistant_settings');
    }
};
