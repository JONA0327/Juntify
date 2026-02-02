<?php

namespace App\Console\Commands;

use App\Models\Folder;
use App\Models\OrganizationFolder;
use App\Models\Subfolder;
use App\Models\OrganizationSubfolder;
use App\Models\GoogleToken;
use App\Services\GoogleServiceAccount;
use App\Services\GoogleDriveService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ShareExistingFolders extends Command
{
    protected $signature = 'drive:share-existing-folders';
    protected $description = 'Comparte todas las carpetas existentes con la cuenta de servicio';

    public function handle()
    {
        $serviceEmail = config('services.google.service_account_email');
        
        if (!$serviceEmail) {
            $this->error('No se ha configurado GOOGLE_SERVICE_ACCOUNT_EMAIL');
            return 1;
        }

        $this->info("Compartiendo carpetas con: {$serviceEmail}");
        $this->newLine();

        // Compartir carpetas personales
        $this->info('📁 Procesando carpetas personales...');
        $personalFolders = Folder::whereNull('parent_id')->get();
        
        foreach ($personalFolders as $folder) {
            $this->processPersonalFolder($folder, $serviceEmail);
        }

        // Compartir carpetas de organizaciones
        $this->info('🏢 Procesando carpetas de organizaciones...');
        $orgFolders = OrganizationFolder::all();
        
        foreach ($orgFolders as $folder) {
            $this->processOrganizationFolder($folder, $serviceEmail);
        }

        $this->newLine();
        $this->info('✅ Proceso completado');
        return 0;
    }

    private function processPersonalFolder(Folder $folder, string $serviceEmail)
    {
        $this->line("  Carpeta: {$folder->name} ({$folder->google_id})");
        
        try {
            $token = GoogleToken::find($folder->google_token_id);
            if (!$token || !$token->hasValidAccessToken()) {
                $this->warn("    ⚠️  Token no válido, omitiendo");
                return;
            }

            $driveService = app(GoogleDriveService::class);
            $driveService->setAccessToken($token->getTokenArray());
            
            // Compartir carpeta raíz
            try {
                $driveService->shareFolder($folder->google_id, $serviceEmail);
                $this->info("    ✓ Carpeta raíz compartida");
            } catch (\Throwable $e) {
                $this->warn("    ✗ No se pudo compartir: " . $e->getMessage());
            }

            // Compartir subcarpetas
            $subfolders = Subfolder::where('folder_id', $folder->id)->get();
            foreach ($subfolders as $subfolder) {
                try {
                    $driveService->shareFolder($subfolder->google_id, $serviceEmail);
                    $this->info("    ✓ Subcarpeta '{$subfolder->name}' compartida");
                } catch (\Throwable $e) {
                    $this->warn("    ✗ Subcarpeta '{$subfolder->name}': " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("    ✗ Error: " . $e->getMessage());
        }
    }

    private function processOrganizationFolder(OrganizationFolder $folder, string $serviceEmail)
    {
        $this->line("  Carpeta org: {$folder->name} ({$folder->google_id})");
        
        try {
            $token = $folder->organizationGoogleToken;
            if (!$token || !$token->hasValidAccessToken()) {
                $this->warn("    ⚠️  Token no válido, omitiendo");
                return;
            }

            $driveService = app(GoogleDriveService::class);
            $driveService->setAccessToken([
                'access_token' => $token->access_token,
                'refresh_token' => $token->refresh_token,
                'expiry_date' => $token->expiry_date,
            ]);
            
            // Compartir carpeta raíz
            try {
                $driveService->shareFolder($folder->google_id, $serviceEmail);
                $this->info("    ✓ Carpeta raíz compartida");
            } catch (\Throwable $e) {
                $this->warn("    ✗ No se pudo compartir: " . $e->getMessage());
            }

            // Compartir subcarpetas
            $subfolders = OrganizationSubfolder::where('organization_folder_id', $folder->id)->get();
            foreach ($subfolders as $subfolder) {
                try {
                    $driveService->shareFolder($subfolder->google_id, $serviceEmail);
                    $this->info("    ✓ Subcarpeta '{$subfolder->name}' compartida");
                } catch (\Throwable $e) {
                    $this->warn("    ✗ Subcarpeta '{$subfolder->name}': " . $e->getMessage());
                }
            }

        } catch (\Throwable $e) {
            $this->error("    ✗ Error: " . $e->getMessage());
        }
    }
}
