<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MercadoPagoService;
use App\Models\Plan;
use App\Models\User;

class DebugMercadoPagoPreference extends Command
{
    protected $signature = 'debug:mercadopago-preference';
    protected $description = 'Debug MercadoPago preference creation';

    public function handle()
    {
        $this->info('🔍 Debugging MercadoPago Preference Creation...');

        try {
            // Verificar configuración
            $this->info('📋 Configuration Check:');
            $this->info('- Access Token: ' . (config('mercadopago.access_token') ? 'PRESENT (TEST: ' . str_starts_with(config('mercadopago.access_token'), 'TEST-') . ')' : 'MISSING'));
            $this->info('- Public Key: ' . (config('mercadopago.public_key') ? 'PRESENT' : 'MISSING'));
            $this->info('- APP_URL: ' . config('app.url'));

            // Obtener datos de prueba
            $user = User::first();
            $plan = Plan::where('code', 'basico')->first();

            if (!$user || !$plan) {
                $this->error('❌ User or plan not found');
                return;
            }

            $this->info("\n📊 Test Data:");
            $this->info("- User: {$user->email}");
            $this->info("- Plan: {$plan->name} - \${$plan->price}");

            // Crear el servicio y preferencia
            $this->info("\n🚀 Creating preference...");
            $service = new MercadoPagoService();
            $result = $service->createPreferenceForPlan($plan, $user);

            if ($result['success']) {
                $this->info('✅ Preference created successfully!');
                $this->info("- Preference ID: {$result['preference_id']}");
                $this->info("- Init Point: {$result['init_point']}");
                $this->info("- Sandbox Init Point: {$result['sandbox_init_point']}");

                // Verificar las URLs
                $this->info("\n🔗 URL Analysis:");
                $initPointDomain = parse_url($result['init_point'], PHP_URL_HOST);
                $sandboxDomain = parse_url($result['sandbox_init_point'], PHP_URL_HOST);

                $this->info("- Production domain: {$initPointDomain}");
                $this->info("- Sandbox domain: {$sandboxDomain}");

                if ($sandboxDomain === 'sandbox.mercadopago.com.mx') {
                    $this->info('✅ Sandbox URL is correct');
                } else {
                    $this->error('❌ Sandbox URL is incorrect');
                }

                // Simular la apertura del checkout
                $this->info("\n🌐 URLs to test:");
                $this->info("Sandbox: {$result['sandbox_init_point']}");

            } else {
                $this->error('❌ Failed to create preference:');
                $this->error($result['error']);
            }

        } catch (\Exception $e) {
            $this->error('💥 Exception caught:');
            $this->error("Message: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
        }
    }
}
