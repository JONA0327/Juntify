<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organization_group_folders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('organization_id');
            $table->unsignedInteger('group_id');
            $table->unsignedInteger('organization_folder_id')->nullable(); // referencia opcional al folder 'Documentos' padre directo
            $table->string('google_id')->unique();
            $table->string('name'); // nombre carpeta en Drive (slug + id sugerido)
            $table->string('path_cached')->nullable();
            $table->timestamps();

            $table->foreign('organization_id')->references('id')->on('organizations')->onDelete('cascade');
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('organization_folder_id')->references('id')->on('organization_folders')->onDelete('set null');

            $table->unique(['group_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('organization_group_folders');
    }
};
