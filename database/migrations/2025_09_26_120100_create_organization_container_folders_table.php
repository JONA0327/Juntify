<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si la tabla ya existe (por un intento previo manual o fallo a mitad) la dejamos y continuamos
        if (Schema::hasTable('organization_container_folders')) {
            return;
        }

        Schema::create('organization_container_folders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_id');
            $table->unsignedInteger('group_id');
            $table->unsignedBigInteger('container_id')->nullable();
            $table->unsignedInteger('organization_group_folder_id')->nullable();
            $table->string('google_id');
            $table->string('name');
            $table->string('path_cached')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('container_id')->references('id')->on('meeting_content_containers')->onDelete('cascade');
        $table->foreign('organization_group_folder_id', 'org_cont_fldr_group_folder_fk')
            ->references('id')
            ->on('organization_group_folders')
            ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_container_folders');
    }
};
