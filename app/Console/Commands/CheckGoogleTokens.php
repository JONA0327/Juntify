<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\GoogleToken;
use App\Models\OrganizationGoogleToken;
use App\Models\User;

class CheckGoogleTokens extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:tokens';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar el estado de los tokens de Google Drive/Calendar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== VERIFICACIÓN DE TOKENS DE GOOGLE ===');
        $this->newLine();
        
        // Verificar tokens personales
        $users = User::whereHas('googleToken')->with('googleToken')->get();
        
        if ($users->count() > 0) {
            $this->info('👤 TOKENS PERSONALES:');
            
            foreach ($users as $user) {
                $token = $user->googleToken;
                $hasAccess = $token && !empty($token->access_token);
                $hasRefresh = $token && !empty($token->refresh_token);
                $isExpired = $token && $token->expiry_date && $token->expiry_date->isPast();
                
                $this->newLine();
                $this->line("📧 Usuario: <info>{$user->email}</info>");
                $this->line("   🔑 Access Token: " . ($hasAccess ? '<info>✓ Presente</info>' : '<error>✗ Ausente</error>'));
                $this->line("   🔄 Refresh Token: " . ($hasRefresh ? '<info>✓ Presente</info>' : '<error>✗ Ausente</error>'));
                $this->line("   ⏰ Expiración: " . ($token->expiry_date ? $token->expiry_date->format('Y-m-d H:i:s') : 'No definida'));
                $this->line("   📅 Estado: " . ($isExpired ? '<error>🔴 EXPIRADO</error>' : '<info>🟢 VÁLIDO</info>'));
                
                if ($token->recordings_folder_id) {
                    $this->line("   📁 Carpeta Recordings: <comment>{$token->recordings_folder_id}</comment>");
                }
            }
        } else {
            $this->warn('ℹ️ No hay usuarios con tokens de Google configurados.');
            $this->line('   Para conectar Google Drive, ve a tu perfil y haz clic en "Conectar Drive y Calendar"');
        }
        
        // Verificar tokens organizacionales
        $orgTokens = OrganizationGoogleToken::with('organization')->get();
        
        if ($orgTokens->count() > 0) {
            $this->newLine();
            $this->info('🏢 TOKENS ORGANIZACIONALES:');
            
            foreach ($orgTokens as $orgToken) {
                $hasAccess = !empty($orgToken->access_token);
                $hasRefresh = !empty($orgToken->refresh_token);
                $isExpired = $orgToken->expiry_date && $orgToken->expiry_date->isPast();
                
                $this->newLine();
                $this->line("🏢 Organización: <info>{$orgToken->organization->name}</info>");
                $this->line("   🔑 Access Token: " . ($hasAccess ? '<info>✓ Presente</info>' : '<error>✗ Ausente</error>'));
                $this->line("   🔄 Refresh Token: " . ($hasRefresh ? '<info>✓ Presente</info>' : '<error>✗ Ausente</error>'));
                $this->line("   ⏰ Expiración: " . ($orgToken->expiry_date ? $orgToken->expiry_date->format('Y-m-d H:i:s') : 'No definida'));
                $this->line("   📅 Estado: " . ($isExpired ? '<error>🔴 EXPIRADO</error>' : '<info>🟢 VÁLIDO</info>'));
            }
        } else {
            if ($users->count() > 0) {
                $this->newLine();
            }
            $this->warn('ℹ️ No hay tokens organizacionales configurados.');
        }
        
        $this->newLine();
        $this->info('=== PRÓXIMOS PASOS ===');
        $this->line('1. Si no tienes tokens, ve a la aplicación web y conecta Google Drive');
        $this->line('2. Si los tokens están expirados, desconecta y vuelve a conectar');
        $this->line('3. La URL de conexión es: <comment>http://127.0.0.1:8000/auth/google/redirect</comment>');
        
        // Estado general
        $hasValidTokens = $users->filter(function($user) {
            $token = $user->googleToken;
            return $token && !empty($token->access_token) && 
                   (!$token->expiry_date || !$token->expiry_date->isPast());
        })->count() > 0;
        
        $hasValidOrgTokens = $orgTokens->filter(function($orgToken) {
            return !empty($orgToken->access_token) && 
                   (!$orgToken->expiry_date || !$orgToken->expiry_date->isPast());
        })->count() > 0;
        
        if ($hasValidTokens || $hasValidOrgTokens) {
            $this->newLine();
            $this->info('✅ Hay tokens válidos configurados - Google Drive debería funcionar');
            return Command::SUCCESS;
        } else {
            $this->newLine();
            $this->error('⚠️ No hay tokens válidos - Conecta Google Drive desde la aplicación');
            return Command::FAILURE;
        }
    }
}
