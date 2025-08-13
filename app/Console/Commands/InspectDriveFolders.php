<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TranscriptionLaravel;
use App\Models\User;
use App\Services\GoogleDriveService;

class InspectDriveFolders extends Command
{
    protected $signature = 'drive:inspect-folders {username}';
    protected $description = 'Inspecciona las carpetas de Drive donde están los archivos';

    protected $googleDriveService;

    public function __construct(GoogleDriveService $googleDriveService)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
    }

    public function handle()
    {
        $username = $this->argument('username');

        $user = User::where('username', $username)->first();
        if (!$user) {
            $this->error("Usuario {$username} no encontrado");
            return 1;
        }

        // Configurar token de Google Drive
        $googleToken = $user->googleToken;
        if (!$googleToken) {
            $this->error("No se encontró token de Google para el usuario {$username}");
            return 1;
        }

        $this->googleDriveService->setAccessToken($googleToken->access_token);

        // Obtener reuniones del usuario
        $meetings = TranscriptionLaravel::where('username', $username)->get();

        $this->info("=== INSPECCIÓN DE CARPETAS DE DRIVE ===");
        $this->info("Usuario: {$username}");
        $this->info("Total de reuniones: " . $meetings->count());
        $this->line("");

        foreach ($meetings as $meeting) {
            $this->info("📁 Reunión: {$meeting->meeting_name}");
            $this->info("   ID: {$meeting->id}");
            $this->info("   Fecha: {$meeting->created_at}");

            // Inspeccionar transcripción
            $this->line("   🔍 Transcripción:");
            $this->line("     Drive ID: {$meeting->transcript_drive_id}");
            try {
                $transcriptFile = $this->googleDriveService->getFileInfo($meeting->transcript_drive_id);
                $this->line("     Nombre: " . $transcriptFile->getName());

                if ($transcriptFile->getParents()) {
                    $parentId = $transcriptFile->getParents()[0];
                    $parent = $this->googleDriveService->getFileInfo($parentId);
                    $this->line("     Carpeta: " . $parent->getName() . " (ID: {$parentId})");

                    // Si la carpeta tiene padres, mostrar la jerarquía
                    if ($parent->getParents()) {
                        $grandParentId = $parent->getParents()[0];
                        $grandParent = $this->googleDriveService->getFileInfo($grandParentId);
                        $this->line("     Carpeta padre: " . $grandParent->getName() . " (ID: {$grandParentId})");
                    }
                } else {
                    $this->line("     Carpeta: RAÍZ (Mi Drive)");
                }
            } catch (\Exception $e) {
                $this->error("     Error: " . $e->getMessage());
            }

            // Inspeccionar audio
            $this->line("   🔍 Audio:");
            $this->line("     Drive ID: {$meeting->audio_drive_id}");
            try {
                $audioFile = $this->googleDriveService->getFileInfo($meeting->audio_drive_id);
                $this->line("     Nombre: " . $audioFile->getName());

                if ($audioFile->getParents()) {
                    $parentId = $audioFile->getParents()[0];
                    $parent = $this->googleDriveService->getFileInfo($parentId);
                    $this->line("     Carpeta: " . $parent->getName() . " (ID: {$parentId})");

                    // Si la carpeta tiene padres, mostrar la jerarquía
                    if ($parent->getParents()) {
                        $grandParentId = $parent->getParents()[0];
                        $grandParent = $this->googleDriveService->getFileInfo($grandParentId);
                        $this->line("     Carpeta padre: " . $grandParent->getName() . " (ID: {$grandParentId})");
                    }
                } else {
                    $this->line("     Carpeta: RAÍZ (Mi Drive)");
                }
            } catch (\Exception $e) {
                $this->error("     Error: " . $e->getMessage());
            }

            $this->line("");
        }

        return 0;
    }
}
