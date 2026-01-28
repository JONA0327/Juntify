<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GoogleToken;
use App\Models\Folder;
use App\Models\Subfolder;
use App\Services\GoogleServiceAccount;
use App\Services\GoogleDriveService;

class SetupDriveFolders extends Command
{
    protected $signature = 'drive:setup {username}';
    protected $description = 'Crear carpeta raíz y subcarpetas de Drive para un usuario';

    public function handle()
    {
        $username = $this->argument('username');
        
        $googleToken = GoogleToken::where('username', $username)->first();
        
        if (!$googleToken) {
            $this->error("Usuario $username no tiene cuenta de Google conectada.");
            return 1;
        }

        if ($googleToken->recordings_folder_id) {
            $this->info("Usuario ya tiene carpeta raíz: {$googleToken->recordings_folder_id}");
            $existingFolder = Folder::where('google_token_id', $googleToken->id)
                ->where('google_id', $googleToken->recordings_folder_id)
                ->first();
            if ($existingFolder) {
                $this->info("Carpeta raíz: {$existingFolder->name}");
            }
        }

        $user = \App\Models\User::where('username', $username)->first();
        if (!$user) {
            $this->error("Usuario $username no encontrado");
            return 1;
        }

        $driveService = app(GoogleDriveService::class);

        // Configurar token del usuario
        $driveService->setAccessToken([
            'access_token' => $googleToken->access_token,
            'refresh_token' => $googleToken->refresh_token,
            'expiry_date' => $googleToken->expiry_date,
        ]);

        $folderId = $googleToken->recordings_folder_id;
        
        // Crear carpeta raíz si no existe
        if (!$folderId) {
            $this->info("Creando carpeta raíz...");
            $defaultRootName = config('drive.default_root_folder_name', 'Juntify Recordings');

            try {
                $folderId = $driveService->createFolder($defaultRootName);

                $folderModel = Folder::create([
                    'google_token_id' => $googleToken->id,
                    'google_id'       => $folderId,
                    'name'            => $defaultRootName,
                    'parent_id'       => null,
                ]);

                $googleToken->recordings_folder_id = $folderId;
                $googleToken->save();

                $this->info("✓ Carpeta raíz creada: $defaultRootName ($folderId)");
            } catch (\Throwable $e) {
                $this->error("Error creando carpeta raíz: " . $e->getMessage());
                return 1;
            }
        }

        // Crear subcarpetas
        $this->info("\nCreando subcarpetas...");
        $folderModel = Folder::where('google_token_id', $googleToken->id)
            ->where('google_id', $folderId)
            ->first();

        if (!$folderModel) {
            $folderModel = Folder::create([
                'google_token_id' => $googleToken->id,
                'google_id'       => $folderId,
                'name'            => 'Juntify Recordings',
                'parent_id'       => null,
            ]);
        }

        $needed = config('drive.default_subfolders', ['Audios', 'Transcripciones', 'Audios Pospuestos', 'Documentos']);

        foreach ($needed as $name) {
            // Verificar si ya existe
            $existing = Subfolder::where('folder_id', $folderModel->id)
                ->where('name', $name)
                ->first();

            if ($existing) {
                $this->info("  - $name ya existe ({$existing->google_id})");
                continue;
            }

            try {
                $subId = $driveService->createFolder($name, $folderId);

                Subfolder::create([
                    'folder_id' => $folderModel->id,
                    'google_id' => $subId,
                    'name'      => $name,
                ]);

                $this->info("  ✓ $name creada ($subId)");
            } catch (\Throwable $e) {
                $this->error("  ✗ Error creando $name: " . $e->getMessage());
            }
        }

        $this->info("\n✓ Configuración de carpetas completada");
        return 0;
    }
}
