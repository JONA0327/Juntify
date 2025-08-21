<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('organization');
            $table->unsignedInteger('current_organization_id')->nullable()->after('roles');
            $table->foreign('current_organization_id')
                  ->references('id')->on('organizations')
                  ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['current_organization_id']);
            $table->dropColumn('current_organization_id');
            $table->string('organization', 255)->default('Juntify User');
        });
    }
};
