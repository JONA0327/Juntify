<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;
use App\Models\AiChatSession;
use App\Jobs\ProcessAiDocumentJob;

class TestTemporaryUpload extends Command
{
    protected $signature = 'test:temp-upload {username} {sessionId}';
    protected $description = 'Simular subida de archivo temporal';

    public function handle()
    {
        $username = $this->argument('username');
        $sessionId = (int) $this->argument('sessionId');

        // Verificar que la sesión existe
        $session = AiChatSession::where('id', $sessionId)->where('username', $username)->first();
        if (!$session) {
            $this->error("Sesión no encontrada: {$sessionId} para usuario {$username}");
            return 1;
        }

        // Leer archivo de prueba
        $filePath = base_path('test_document.txt');
        if (!file_exists($filePath)) {
            $this->error("Archivo de prueba no encontrado: {$filePath}");
            return 1;
        }

        $fileContent = file_get_contents($filePath);
        $fileName = 'test_document.txt';

        $this->info("=== CREANDO DOCUMENTO TEMPORAL ===");

        // Crear documento temporal
        $document = AiDocument::create([
            'username' => $username,
            'name' => 'test_document',
            'original_filename' => $fileName,
            'document_type' => 'text',
            'mime_type' => 'text/plain',
            'file_size' => strlen($fileContent),
            'drive_file_id' => 'temp_' . uniqid(),
            'drive_folder_id' => null,
            'drive_type' => 'personal',
            'processing_status' => 'pending',
            'is_temporary' => true,
            'session_id' => $sessionId,
            'extracted_text' => '',
            'document_metadata' => [
                'temporary_file' => true,
                'file_content' => base64_encode($fileContent),
                'created_in_session' => (string) $sessionId,
                'created_via' => 'test_command',
            ],
        ]);

        $this->info("✅ Documento creado con ID: {$document->id}");

        // Agregar al contexto de la sesión
        $contextData = is_array($session->context_data) ? $session->context_data : [];
        $docIds = $contextData['doc_ids'] ?? [];
        if (!is_array($docIds)) { $docIds = []; }

        if (!in_array($document->id, $docIds)) {
            $docIds[] = $document->id;
            $contextData['doc_ids'] = array_values($docIds);
            $session->context_data = $contextData;
            $session->save();

            $this->info("✅ Documento agregado al contexto de la sesión");
        }

        // Procesar documento
        $this->info("Procesando documento...");
        ProcessAiDocumentJob::dispatch($document->id);

        $this->info("✅ Job de procesamiento enviado a la cola");
        $this->info("ID del documento: {$document->id}");

        return 0;
    }
}
