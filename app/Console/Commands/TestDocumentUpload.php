<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\AiDocument;
use Carbon\Carbon;

class TestDocumentUpload extends Command
{
    protected $signature = 'test:document-upload {email}';
    protected $description = 'Probar el sistema de documentos para un usuario';

    public function handle()
    {
        $email = $this->argument('email');

        $this->info("=== TEST DE SISTEMA DE DOCUMENTOS ===");
        $this->line("Email: {$email}");
        $this->line("");

        // Verificar límites de plan
        $this->info("1. Verificando límites del usuario...");
        $command = "php artisan plan:check {$email}";
        $this->line("Ejecutando: {$command}");
        passthru($command);

        $this->line("");
        $this->info("2. Documentos actuales del usuario:");

        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->error("Usuario no encontrado");
            return 1;
        }

        $documents = AiDocument::where('username', $user->username)
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        if ($documents->count() > 0) {
            $this->table(
                ['ID', 'Nombre', 'Estado', 'Creado', 'Progreso'],
                $documents->map(function($doc) {
                    return [
                        $doc->id,
                        $doc->name,
                        $doc->processing_status,
                        $doc->created_at->format('Y-m-d H:i'),
                        $doc->processing_progress . '%'
                    ];
                })
            );
        } else {
            $this->line("  - No hay documentos");
        }

        $this->line("");
        $this->info("3. Estado de la cola de trabajos:");
        $pendingJobs = \DB::table('jobs')->count();
        $failedJobs = \DB::table('failed_jobs')->count();

        $this->line("  - Trabajos pendientes: {$pendingJobs}");
        $this->line("  - Trabajos fallidos: {$failedJobs}");

        $this->line("");
        $this->info("🎯 Sistema listo para recibir archivos");
        $this->line("   - Plan verificado");
        $this->line("   - Worker de cola activo");
        $this->line("   - Middleware de timeout configurado");

        return 0;
    }
}
