<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Google\Client;
use Google\Service\Drive;
use Google\Service\Oauth2;
use Google\Service\Calendar;

class CheckGoogleConfig extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verificar configuración de Google Drive y Calendar';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== DIAGNÓSTICO DE CONFIGURACIÓN GOOGLE ===');
        $this->newLine();
        
        // 1. Variables de entorno
        $this->info('1. Variables de entorno:');
        $requiredConfigs = [
            'services.google.client_id' => 'GOOGLE_OAUTH_CLIENT_ID',
            'services.google.client_secret' => 'GOOGLE_OAUTH_CLIENT_SECRET', 
            'services.google.redirect' => 'GOOGLE_OAUTH_REDIRECT_URI',
            'services.google.service_account_email' => 'GOOGLE_SERVICE_ACCOUNT_EMAIL',
            'services.google.service_account_json' => 'GOOGLE_APPLICATION_CREDENTIALS',
            'services.google.api_key' => 'GOOGLE_API_KEY'
        ];

        $configStatus = true;
        foreach ($requiredConfigs as $configKey => $envName) {
            $value = config($configKey);
            $status = $value ? '✓' : '✗';
            $displayValue = $value ? (strlen($value) > 50 ? substr($value, 0, 47) . '...' : $value) : 'NO CONFIGURADO';
            $this->line("   {$status} {$envName}: {$displayValue}");
            if (!$value) $configStatus = false;
        }
        
        $this->newLine();

        // 2. Archivo de credenciales
        $this->info('2. Archivo de credenciales de Service Account:');
        $credentialsPath = config('services.google.service_account_json');
        if ($credentialsPath && file_exists($credentialsPath)) {
            $this->line("   ✓ Archivo existe: {$credentialsPath}");
            
            $credentials = json_decode(file_get_contents($credentialsPath), true);
            if ($credentials) {
                $this->line('   ✓ JSON válido');
                $this->line('   ✓ Project ID: ' . ($credentials['project_id'] ?? 'No encontrado'));
                $this->line('   ✓ Client Email: ' . ($credentials['client_email'] ?? 'No encontrado'));
            } else {
                $this->line('   ✗ JSON inválido');
                $configStatus = false;
            }
        } else {
            $this->line("   ✗ Archivo no encontrado: {$credentialsPath}");
            $configStatus = false;
        }

        $this->newLine();

        // 3. Cliente OAuth
        $this->info('3. Cliente OAuth de Google:');
        try {
            $client = new Client();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect'));
            $client->setScopes([
                Oauth2::USERINFO_EMAIL,
                Drive::DRIVE,
                Calendar::CALENDAR
            ]);
            
            $this->line('   ✓ Cliente OAuth configurado correctamente');
            $this->line('   ✓ Scopes: email, drive, calendar');
            
            $authUrl = $client->createAuthUrl();
            $this->line('   ✓ URL de autorización generada');
            
        } catch (\Exception $e) {
            $this->line('   ✗ Error al configurar cliente OAuth: ' . $e->getMessage());
            $configStatus = false;
        }

        $this->newLine();

        // 4. Service Account
        $this->info('4. Service Account:');
        try {
            if ($credentialsPath && file_exists($credentialsPath)) {
                $client = new Client();
                $client->setAuthConfig($credentialsPath);
                $client->setScopes([Drive::DRIVE]);
                
                $service = new Drive($client);
                $this->line('   ✓ Service Account configurado');
                $this->line('   ✓ Servicio Drive inicializado');
            } else {
                $this->line('   ✗ No se puede verificar Service Account');
                $configStatus = false;
            }
        } catch (\Exception $e) {
            $this->line('   ✗ Error con Service Account: ' . $e->getMessage());
            $configStatus = false;
        }

        $this->newLine();
        
        // 5. Resumen
        $this->info('=== RESUMEN ===');
        if ($configStatus) {
            $this->info('✅ CONFIGURACIÓN CORRECTA - Google Drive/Calendar debería funcionar');
            $this->newLine();
            $this->info('Para probar la conexión:');
            $this->line('1. Reinicia tu servidor Laravel si es necesario');
            $this->line('2. Ve a la sección de perfil en tu aplicación');
            $this->line('3. Haz clic en "Conectar Drive y Calendar"');
        } else {
            $this->error('❌ CONFIGURACIÓN INCOMPLETA - Revisa los errores arriba');
            $this->newLine();
            $this->info('Próximos pasos:');
            $this->line('1. Verificar que todas las variables de entorno estén configuradas');
            $this->line('2. Asegurar que el archivo de credenciales esté en la ruta correcta');
            $this->line('3. Ejecutar: php artisan config:clear');
            $this->line('4. Reiniciar el servidor');
        }

        return $configStatus ? Command::SUCCESS : Command::FAILURE;
    }
}
