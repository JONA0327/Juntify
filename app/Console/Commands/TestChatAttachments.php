<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;
use App\Models\AiChatSession;
use Carbon\Carbon;

class TestChatAttachments extends Command
{
    protected $signature = 'test:chat-attachments {email}';
    protected $description = 'Probar el sistema de archivos adjuntos temporales';

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("=== TEST DE ARCHIVOS ADJUNTOS TEMPORALES ===");
        $this->line("Email: {$email}");
        $this->line("");

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->error("Usuario no encontrado");
            return 1;
        }

        $this->info("✅ Usuario encontrado: {$user->username}");
        $this->line("");

        // 1. Verificar estado de archivos temporales
        $this->info("📁 Estado actual de archivos temporales:");
        $tempFiles = AiDocument::where('username', $user->username)
            ->where('is_temporary', true)
            ->get();

        if ($tempFiles->count() > 0) {
            $this->table(
                ['ID', 'Nombre', 'Sesión', 'Estado', 'Creado'],
                $tempFiles->map(function($doc) {
                    return [
                        $doc->id,
                        $doc->original_filename,
                        $doc->session_id ?? 'Sin sesión',
                        $doc->processing_status,
                        $doc->created_at->format('H:i:s')
                    ];
                })
            );
        } else {
            $this->line("  - No hay archivos temporales");
        }

        $this->line("");

        // 2. Verificar sesiones activas
        $this->info("💬 Sesiones de chat activas:");
        $sessions = AiChatSession::where('username', $user->username)
            ->orderBy('last_activity', 'desc')
            ->take(3)
            ->get();

        if ($sessions->count() > 0) {
            $this->table(
                ['ID', 'Título', 'Última actividad', 'Contexto'],
                $sessions->map(function($session) {
                    $contextInfo = '';
                    if (isset($session->context_data['doc_ids'])) {
                        $contextInfo .= count($session->context_data['doc_ids']) . ' docs';
                    }
                    if (isset($session->context_data['attached_files'])) {
                        $contextInfo .= ', ' . count($session->context_data['attached_files']) . ' adjuntos';
                    }

                    return [
                        $session->id,
                        $session->title,
                        $session->last_activity ? Carbon::parse($session->last_activity)->format('H:i:s') : 'N/A',
                        $contextInfo ?: 'Vacío'
                    ];
                })
            );
        } else {
            $this->line("  - No hay sesiones activas");
        }

        $this->line("");

        // 3. Estado del sistema
        $this->info("⚙️  Estado del sistema:");

        // Verificar tabla jobs
        $pendingJobs = \DB::table('jobs')->count();
        $this->line("  - Trabajos pendientes: {$pendingJobs}");

        // Verificar procesamiento de archivos
        $processingDocs = AiDocument::where('username', $user->username)
            ->where('processing_status', 'processing')
            ->count();
        $this->line("  - Documentos procesándose: {$processingDocs}");

        // Verificar completados hoy
        $completedToday = AiDocument::where('username', $user->username)
            ->where('processing_status', 'completed')
            ->whereDate('created_at', today())
            ->count();
        $this->line("  - Documentos completados hoy: {$completedToday}");

        $this->line("");

        // 4. Instrucciones
        $this->info("📋 Cómo probar:");
        $this->line("1. Ve al AI Assistant: http://127.0.0.1:8000/ai-assistant");
        $this->line("2. Haz clic en el botón de adjuntar archivo (📎)");
        $this->line("3. Sube un archivo PDF o imagen");
        $this->line("4. Verifica que aparezca en la lista de adjuntos");
        $this->line("5. Envía un mensaje preguntando sobre el documento");
        $this->line("6. El sistema debería incluir el documento en el contexto");

        $this->line("");
        $this->info("🎯 Sistema listo para pruebas de archivos adjuntos");

        return 0;
    }
}
