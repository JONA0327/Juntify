<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MercadoPagoService;
use App\Models\Plan;
use App\Models\User;

class TestMercadoPagoService extends Command
{
    protected $signature = 'test:mercadopago';
    protected $description = 'Test MercadoPago service';

    public function handle()
    {
        $this->info('Testing MercadoPago Service...');

        try {
            // Obtener usuario y plan
            $user = User::first();
            $plan = Plan::where('code', 'basico')->first();

            if (!$user || !$plan) {
                $this->error('User or plan not found');
                return;
            }

            $this->info("User: {$user->email}");
            $this->info("Plan: {$plan->name} - \${$plan->price}");

            // Probar el servicio
            $service = new MercadoPagoService();
            $result = $service->createPreferenceForPlan($plan, $user);

            if ($result['success']) {
                $this->info('✅ Preference created successfully!');
                $this->info("Preference ID: {$result['preference_id']}");
                $this->info("Init Point: {$result['init_point']}");
                $this->info("Sandbox Init Point: {$result['sandbox_init_point']}");
            } else {
                $this->error('❌ Failed to create preference:');
                $this->error($result['error']);
            }

        } catch (\Exception $e) {
            $this->error('Exception caught:');
            $this->error("Message: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
        }
    }
}
