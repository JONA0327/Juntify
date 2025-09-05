<?php

namespace App\Console\Commands;

use App\Services\GoogleTokenRefreshService;
use Illuminate\Console\Command;

class RefreshGoogleTokens extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'google:refresh-tokens';

    /**
     * The console command description.
     */
    protected $description = 'Refresh Google tokens that are about to expire';

    /**
     * Execute the console command.
     */
    public function handle(GoogleTokenRefreshService $tokenService)
    {
        $this->info('Iniciando renovación de tokens de Google...');

        $refreshed = $tokenService->refreshExpiredTokens();

        if ($refreshed > 0) {
            $this->info("✅ Se renovaron {$refreshed} tokens exitosamente");
        } else {
            $this->info("ℹ️ No había tokens que necesitaran renovación");
        }

        return 0;
    }
}
