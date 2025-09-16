<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('meeting_shares', function (Blueprint $table) {
            // Permite compartir reuniones legacy (transcriptions_laravel) o genéricas
            $table->string('meeting_id', 191);
            $table->string('from_username', 255);
            $table->string('to_username', 255);
            $table->timestamp('created_at')->useCurrent();
            $table->primary(['meeting_id', 'from_username', 'to_username']);
        });
    }
    public function down()
    {
        Schema::dropIfExists('meeting_shares');
    }
};
