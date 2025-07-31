<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('analyzers', function (Blueprint $table) {
            if (Schema::hasColumn('analyzers', 'userprotmp')) {
                $table->dropColumn('userprotmp');
            }
        });
    }

    public function down()
    {
        Schema::table('analyzers', function (Blueprint $table) {
            if (!Schema::hasColumn('analyzers', 'userprotmp')) {
                $table->text('userprotmp')->nullable()->after('user_prompt_template');
            }
        });
    }
};
