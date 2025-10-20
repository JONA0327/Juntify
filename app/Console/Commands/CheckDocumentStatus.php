<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;

class CheckDocumentStatus extends Command
{
    protected $signature = 'doc:check {filename?}';
    protected $description = 'Verificar estado de documento';

    public function handle()
    {
        $filename = $this->argument('filename') ?? 'Escaner';

        $doc = AiDocument::where('original_filename', 'like', "%{$filename}%")
            ->latest()
            ->first();

        if (!$doc) {
            $this->error("Documento no encontrado con nombre: {$filename}");
            return 1;
        }

        $this->info("=== ESTADO DEL DOCUMENTO ===");
        $this->line("ID: {$doc->id}");
        $this->line("Nombre: {$doc->original_filename}");
        $this->line("Usuario: {$doc->username}");
        $this->line("Estado: {$doc->processing_status}");
        $this->line("Progreso: {$doc->processing_progress}%");
        $this->line("Temporal: " . ($doc->is_temporary ? 'Sí' : 'No'));
        $this->line("Sesión: " . ($doc->session_id ?? 'Ninguna'));
        $this->line("Tamaño: " . number_format($doc->file_size) . " bytes");

        $extractedLength = strlen($doc->extracted_text ?? '');
        $this->line("Texto extraído: {$extractedLength} caracteres");

        if ($doc->processing_error) {
            $this->error("Error: {$doc->processing_error}");
        }

        if ($extractedLength > 0) {
            $preview = substr($doc->extracted_text, 0, 200);
            $this->info("Vista previa del texto:");
            $this->line($preview . "...");
        } else {
            $this->warn("⚠️  No hay texto extraído - Este es el problema");
        }

        // Verificar metadata
        if ($doc->document_metadata) {
            $this->line("\n=== METADATA ===");
            foreach ($doc->document_metadata as $key => $value) {
                if ($key === 'file_content') {
                    $this->line("file_content: " . strlen(base64_decode($value)) . " bytes (base64)");
                } else {
                    $this->line("{$key}: " . (is_array($value) ? json_encode($value) : $value));
                }
            }
        }

        return 0;
    }
}
