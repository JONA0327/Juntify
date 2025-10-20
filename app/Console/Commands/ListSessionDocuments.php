<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;

class ListSessionDocuments extends Command
{
    protected $signature = 'doc:list-session {sessionId}';
    protected $description = 'Listar documentos de una sesión';

    public function handle()
    {
        $sessionId = $this->argument('sessionId');

        $docs = AiDocument::where('session_id', $sessionId)->get();

        if ($docs->isEmpty()) {
            $this->warn("No hay documentos en la sesión {$sessionId}");
            return 1;
        }

        $this->info("=== DOCUMENTOS EN SESIÓN {$sessionId} ===");

        foreach ($docs as $doc) {
            $this->line("ID: {$doc->id}");
            $this->line("  Archivo: {$doc->original_filename}");
            $this->line("  Temporal: " . ($doc->is_temporary ? 'Sí' : 'No'));
            $this->line("  Estado: {$doc->processing_status}");
            $this->line("  Drive ID: {$doc->drive_file_id}");
            $this->line("  ---");
        }

        return 0;
    }
}
