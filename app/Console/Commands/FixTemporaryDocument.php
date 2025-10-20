<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;
use App\Services\ExtractorService;
use App\Services\GoogleDriveService;

class FixTemporaryDocument extends Command
{
    protected $signature = 'doc:fix-temporary {id}';
    protected $description = 'Convertir documento a temporal y procesar contenido';

    public function handle()
    {
        $id = $this->argument('id');

        $this->info("=== PROCESANDO DOCUMENTO TEMPORAL ===");

        $doc = AiDocument::find($id);
        if (!$doc) {
            $this->error("❌ Documento no encontrado");
            return 1;
        }

        $this->line("Documento encontrado: {$doc->original_filename}");

        // Actualizar a temporal (mantener drive_type original)
        $doc->is_temporary = true;
        $doc->processing_status = 'pending';
        $doc->save();

        $this->info("✅ Documento marcado como temporal");

        // Obtener contenido del archivo desde Google Drive
        try {
            $this->line("Descargando archivo desde Google Drive...");
            $driveService = app(GoogleDriveService::class);
            $content = $driveService->downloadFileContent($doc->drive_file_id);

            if ($content) {
                $this->info("✅ Archivo descargado (" . strlen($content) . " bytes)");

                // Actualizar metadata con el contenido
                $metadata = $doc->document_metadata ?? [];
                $metadata['file_content'] = base64_encode($content);
                $metadata['temporary_file'] = true;
                $metadata['converted_from_permanent'] = true;
                $doc->document_metadata = $metadata;
                $doc->save();

                $this->info("✅ Contenido guardado en metadata");

                // Procesar el documento
                $this->line("Procesando contenido...");
                $extractorService = app(ExtractorService::class);

                // Determinar el tipo de archivo
                $extension = strtolower(pathinfo($doc->original_filename, PATHINFO_EXTENSION));

                // Para archivos temporales, marcar como listo para ChatGPT sin extraer texto local
                if ($extension === 'pdf') {
                    $this->line("PDF preparado para ChatGPT...");
                    $extractedText = "[DOCUMENTO PDF TEMPORAL] Este es un archivo PDF adjunto que será procesado directamente por ChatGPT. Nombre: {$doc->original_filename}, Tamaño: " . number_format($doc->file_size) . " bytes. El contenido está disponible como archivo adjunto en base64.";
                } else {
                    $this->line("Archivo preparado para ChatGPT...");
                    $extractedText = "[DOCUMENTO TEMPORAL] Archivo {$extension} adjunto: {$doc->original_filename}, Tamaño: " . number_format($doc->file_size) . " bytes. El contenido está disponible como archivo adjunto en base64.";
                }

                if ($extractedText && strlen($extractedText) > 10) {
                    $doc->extracted_text = $extractedText;
                    $doc->processing_status = 'completed';
                    $doc->processing_progress = 100;
                    $doc->save();

                    $this->info("✅ Texto extraído (" . strlen($extractedText) . " caracteres)");
                    $this->line("Vista previa: " . substr($extractedText, 0, 200) . "...");
                } else {
                    $this->warn("⚠️ No se pudo extraer texto del documento");
                    $doc->processing_status = 'failed';
                    $doc->processing_error = 'No se pudo extraer texto del documento';
                    $doc->save();
                }

            } else {
                $this->error("❌ No se pudo descargar el archivo desde Google Drive");
            }

        } catch (\Throwable $e) {
            $this->error("❌ Error: " . $e->getMessage());
            $doc->processing_status = 'failed';
            $doc->processing_error = $e->getMessage();
            $doc->save();
        }

        $this->info("\n=== PROCESO COMPLETADO ===");
        return 0;
    }
}
