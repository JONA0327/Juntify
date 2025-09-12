<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_documents', function (Blueprint $table) {
            $table->id();
            $table->string('username');
            $table->string('name');
            $table->string('original_filename');
            $table->enum('document_type', ['pdf', 'word', 'excel', 'powerpoint', 'image', 'text']);
            $table->string('mime_type');
            $table->bigInteger('file_size');
            $table->string('drive_file_id'); // ID del archivo en Google Drive
            $table->string('drive_folder_id')->nullable(); // Carpeta de Drive donde se subió
            $table->enum('drive_type', ['personal', 'organization'])->default('personal');
            $table->longText('extracted_text')->nullable(); // Texto extraído via OCR/parsing
            $table->json('ocr_metadata')->nullable(); // Metadatos del OCR (confianza, idioma, etc.)
            $table->string('processing_status')->default('pending'); // pending, processing, completed, failed
            $table->text('processing_error')->nullable();
            $table->json('document_metadata')->nullable(); // Metadatos adicionales del documento
            $table->timestamps();

            $table->foreign('username')->references('username')->on('users')->onDelete('cascade');
            $table->index(['username', 'document_type']);
            $table->index(['processing_status']);
            $table->index(['drive_file_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_documents');
    }
};
