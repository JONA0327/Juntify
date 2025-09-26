<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GoogleToken;
use App\Models\Subfolder;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class DriveRepairUserFolders extends Command
{
    protected $signature = 'drive:repair-user-folders {username? : (Opcional) Username específico a reparar}';
    protected $description = 'Asegura que las subcarpetas default existen para la carpeta raíz de cada usuario (Audios, Transcripciones, etc.)';

    public function handle(): int
    {
        $username = $this->argument('username');
        $query = GoogleToken::query()->whereNotNull('recordings_folder_id');
        if ($username) {
            $query->where('username', $username);
        }
        $tokens = $query->get();
        if ($tokens->isEmpty()) {
            $this->warn('No se encontraron tokens con carpeta raíz definida.');
            return self::SUCCESS;
        }

        $expected = collect(config('drive.default_subfolders', []));
        if ($expected->isEmpty()) {
            $this->warn('No hay subcarpetas default configuradas en config/drive.php');
            return self::SUCCESS;
        }

        /** @var \App\Services\GoogleServiceAccount|null $sa */
        $sa = null;
        try { $sa = app(\App\Services\GoogleServiceAccount::class); } catch (\Throwable $e) { $this->error('No se pudo inicializar ServiceAccount: '.$e->getMessage()); }
        /** @var \App\Services\GoogleDriveService $drive */
        $drive = app(\App\Services\GoogleDriveService::class);

        foreach ($tokens as $token) {
            $user = User::where('username', $token->username)->first();
            $this->line('--- Usuario: '.$token->username.' (folder '.$token->recordings_folder_id.')');
            $folderModel = Folder::where('google_token_id', $token->id)
                                 ->where('google_id', $token->recordings_folder_id)
                                 ->first();
            if (!$folderModel) {
                $folderModel = Folder::create([
                    'google_token_id' => $token->id,
                    'google_id' => $token->recordings_folder_id,
                    'name' => 'Recordings '.$token->username,
                    'parent_id' => null,
                ]);
                $this->info('Creado registro de carpeta raíz local.');
            }

            // Configurar token OAuth en drive para fallback
            try {
                if ($token->access_token) {
                    $drive->setAccessToken([
                        'access_token' => $token->access_token,
                        'refresh_token' => $token->refresh_token,
                        'expiry_date' => $token->expiry_date,
                    ]);
                }
            } catch (\Throwable $eSet) {
                Log::debug('No se pudo setAccessToken en comando', ['username' => $token->username, 'error' => $eSet->getMessage()]);
            }

            $have = Subfolder::where('folder_id', $folderModel->id)->pluck('name')->map(fn($n) => mb_strtolower($n))->all();
            $missing = $expected->filter(fn($name) => !in_array(mb_strtolower($name), $have));
            if ($missing->isEmpty()) {
                $this->info('Todas las subcarpetas están presentes.');
                continue;
            }
            $this->warn('Faltan: '.implode(', ', $missing->all()));

            foreach ($missing as $name) {
                $newId = null;
                // 1. SA directa
                if ($sa) {
                    try { $newId = $sa->createFolder($name, $folderModel->google_id); }
                    catch (\Throwable $e1) {
                        Log::debug('Fallo SA directa (command)', ['username' => $token->username, 'name' => $name, 'error' => $e1->getMessage()]);
                        // 2. SA impersonada
                        if ($user && $user->email) {
                            try { $sa->impersonate($user->email); $newId = $sa->createFolder($name, $folderModel->google_id); }
                            catch (\Throwable $e2) {
                                Log::debug('Fallo SA impersonada (command)', ['username' => $token->username, 'name' => $name, 'error' => $e2->getMessage()]);
                            } finally { try { $sa->impersonate(null); } catch (\Throwable $eR) { /* ignore */ } }
                        }
                    }
                }
                // 3. OAuth fallback
                if (!$newId) {
                    try { $newId = $drive->createFolder($name, $folderModel->google_id); }
                    catch (\Throwable $e3) {
                        Log::warning('Fallo total creando subcarpeta (command)', ['username' => $token->username, 'name' => $name, 'error' => $e3->getMessage()]);
                    }
                }
                if ($newId) {
                    Subfolder::firstOrCreate([
                        'folder_id' => $folderModel->id,
                        'google_id' => $newId,
                    ], ['name' => $name]);
                    $this->info("Creada subcarpeta $name ($newId)");
                    // Compartir con usuario y SA email
                    if ($sa && $user && $user->email) {
                        try { $sa->shareItem($newId, $user->email, 'writer'); } catch (\Throwable $eShare) { /* ignore */ }
                    }
                } else {
                    $this->error('No se pudo crear subcarpeta: '.$name);
                }
            }
        }

        $this->info('Reparación finalizada');
        return self::SUCCESS;
    }
}
