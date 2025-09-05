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
        Schema::table('google_tokens', function (Blueprint $table) {
            // Campos individuales para los componentes del token de Google
            $table->integer('expires_in')->nullable()->after('expiry_date')->comment('Duración en segundos del token');
            $table->text('scope')->nullable()->after('expires_in')->comment('Scopes autorizados del token');
            $table->string('token_type', 50)->nullable()->after('scope')->default('Bearer')->comment('Tipo de token (Bearer)');
            $table->text('id_token')->nullable()->after('token_type')->comment('JWT token de identidad');
            $table->timestamp('token_created_at')->nullable()->after('id_token')->comment('Timestamp de creación del token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('google_tokens', function (Blueprint $table) {
            $table->dropColumn([
                'expires_in',
                'scope',
                'token_type',
                'id_token',
                'token_created_at'
            ]);
        });
    }
};
